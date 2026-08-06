<?php

/**
 * Asignación masiva de configuración de procesos de limpieza a áreas físicas
 * (tabla areas_fisicas_x_procesos_limpieza).
 *
 * Este service cubre la pantalla de "Configuración de Aseo" que permite aplicar
 * de una sola vez el tiempo, los días, la prioridad y las veces por día a varias
 * áreas para un proceso. El CRUD individual (si existe) vive en otro lado; aquí
 * solo está lo masivo y la consulta del estado actual.
 */
class AreasFisicasXProcesosLimpiezaConfig
{
    /**
     * Devuelve todas las áreas activas con su configuración para el proceso dado
     * (si la tienen). Las que no están configuradas vienen con los campos en null.
     */
    public static function getConfiguracion()
    {
        $db = Flight::db();

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        if (!$id_proceso) {
            Flight::json(array('error' => 'Falta el parámetro requerido (id_proceso)'), 400);
            return;
        }

        $sentence = $db->prepare("
            SELECT
                af.id as id_area_fisica,
                af.nombre as area,
                af.ubicacion,
                axp.id as id_config,
                axp.tiempo_estimado_minutos,
                axp.hora_sugerida,
                axp.prioridad,
                axp.veces_por_dia,
                axp.id_periodicidad,
                per.nombre as periodicidad,
                axp.lunes, axp.martes, axp.miercoles, axp.jueves,
                axp.viernes, axp.sabado, axp.domingo,
                CASE WHEN axp.id IS NULL THEN 0 ELSE 1 END as tiene_config
            FROM areas_fisicas af
            LEFT JOIN areas_fisicas_x_procesos_limpieza axp
                ON axp.id_area_fisica = af.id
                AND axp.id_tipo_proceso_limpieza = :id_proceso
                AND axp.id_tenant = :id_tenant
            LEFT JOIN periodicidad per ON axp.id_periodicidad = per.id
            WHERE af.activo = 1
                AND af.id_tenant = :id_tenant2
            ORDER BY af.nombre
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json($sentence->fetchAll());
    }

    /**
     * Aplica en lote la configuración a las áreas indicadas para un proceso.
     * Sobrescribe la configuración existente de las áreas seleccionadas (unique
     * area+proceso -> ON DUPLICATE KEY UPDATE). Todo en una transacción.
     */
    public static function asignarLote()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_proceso = Flight::request()->data['id_proceso'] ?? null;
            $areas = Flight::request()->data['areas'] ?? array();
            $config = Flight::request()->data['config'] ?? array();

            if (!$id_proceso) {
                throw new Exception('Debe indicar el proceso');
            }
            if (!is_array($areas) || count($areas) == 0) {
                throw new Exception('Debe seleccionar al menos un área');
            }

            // Valores de la configuración, con defaults seguros
            $tiempo = isset($config['tiempo_estimado_minutos']) ? (int) $config['tiempo_estimado_minutos'] : 0;
            $prioridad = isset($config['prioridad']) ? (int) $config['prioridad'] : 1;
            $veces_por_dia = isset($config['veces_por_dia']) ? (int) $config['veces_por_dia'] : 1;
            $hora_sugerida = !empty($config['hora_sugerida']) ? $config['hora_sugerida'] : null;
            $id_periodicidad = !empty($config['id_periodicidad']) ? (int) $config['id_periodicidad'] : null;

            $lunes = !empty($config['lunes']) ? 1 : 0;
            $martes = !empty($config['martes']) ? 1 : 0;
            $miercoles = !empty($config['miercoles']) ? 1 : 0;
            $jueves = !empty($config['jueves']) ? 1 : 0;
            $viernes = !empty($config['viernes']) ? 1 : 0;
            $sabado = !empty($config['sabado']) ? 1 : 0;
            $domingo = !empty($config['domingo']) ? 1 : 0;

            if ($tiempo < 0) {
                throw new Exception('El tiempo estimado no puede ser negativo');
            }
            if ($veces_por_dia < 1) {
                throw new Exception('Las veces por día deben ser al menos 1');
            }

            $sql = "
                INSERT INTO areas_fisicas_x_procesos_limpieza (
                    id, id_tenant,
                    id_area_fisica, id_tipo_proceso_limpieza,
                    tiempo_estimado_minutos, hora_sugerida, prioridad, veces_por_dia,
                    id_periodicidad,
                    lunes, martes, miercoles, jueves, viernes, sabado, domingo,
                    activo
                ) VALUES (
                    :id, :id_tenant,
                    :id_area, :id_proceso,
                    :tiempo, :hora_sugerida, :prioridad, :veces_por_dia,
                    :id_periodicidad,
                    :lunes, :martes, :miercoles, :jueves, :viernes, :sabado, :domingo,
                    1
                )
                ON DUPLICATE KEY UPDATE
                    tiempo_estimado_minutos = VALUES(tiempo_estimado_minutos),
                    hora_sugerida = VALUES(hora_sugerida),
                    prioridad = VALUES(prioridad),
                    veces_por_dia = VALUES(veces_por_dia),
                    id_periodicidad = VALUES(id_periodicidad),
                    lunes = VALUES(lunes),
                    martes = VALUES(martes),
                    miercoles = VALUES(miercoles),
                    jueves = VALUES(jueves),
                    viernes = VALUES(viernes),
                    sabado = VALUES(sabado),
                    domingo = VALUES(domingo),
                    activo = 1
            ";

            $sentence = $db->prepare($sql);

            foreach ($areas as $id_area) {
                $sentence->bindValue(':id', Uuid::generar());
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':id_area', $id_area);
                $sentence->bindValue(':id_proceso', $id_proceso);
                $sentence->bindValue(':tiempo', $tiempo, PDO::PARAM_INT);
                $sentence->bindValue(':hora_sugerida', $hora_sugerida);
                $sentence->bindValue(':prioridad', $prioridad, PDO::PARAM_INT);
                $sentence->bindValue(':veces_por_dia', $veces_por_dia, PDO::PARAM_INT);
                $sentence->bindValue(':id_periodicidad', $id_periodicidad, $id_periodicidad === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $sentence->bindValue(':lunes', $lunes, PDO::PARAM_INT);
                $sentence->bindValue(':martes', $martes, PDO::PARAM_INT);
                $sentence->bindValue(':miercoles', $miercoles, PDO::PARAM_INT);
                $sentence->bindValue(':jueves', $jueves, PDO::PARAM_INT);
                $sentence->bindValue(':viernes', $viernes, PDO::PARAM_INT);
                $sentence->bindValue(':sabado', $sabado, PDO::PARAM_INT);
                $sentence->bindValue(':domingo', $domingo, PDO::PARAM_INT);
                $sentence->execute();
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'total_aplicadas' => count($areas)
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en AreasFisicasXProcesosLimpiezaConfig::asignarLote: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Quita la configuración de un proceso para las áreas indicadas (las desactiva).
     * Útil para dejar áreas fuera de un proceso en lote.
     */
    public static function quitarLote()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_proceso = Flight::request()->data['id_proceso'] ?? null;
            $areas = Flight::request()->data['areas'] ?? array();

            if (!$id_proceso) {
                throw new Exception('Debe indicar el proceso');
            }
            if (!is_array($areas) || count($areas) == 0) {
                throw new Exception('Debe seleccionar al menos un área');
            }

            $placeholders = implode(',', array_fill(0, count($areas), '?'));

            $sentence = $db->prepare("
                UPDATE areas_fisicas_x_procesos_limpieza
                SET activo = 0
                WHERE id_tipo_proceso_limpieza = ?
                    AND id_tenant = ?
                    AND id_area_fisica IN ($placeholders)
            ");
            $sentence->execute(array_merge([$id_proceso, TenantContext::id()], array_values($areas)));

            $db->commit();

            Flight::json(array(
                'success' => true,
                'total_quitadas' => $sentence->rowCount()
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en AreasFisicasXProcesosLimpiezaConfig::quitarLote: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }
}