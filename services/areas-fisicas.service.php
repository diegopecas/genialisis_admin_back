<?php
class AreasFisicas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT af.*, 
                   COUNT(DISTINCT pmaf.id_producto_mobiliario) as total_mobiliario,
                   SUM(pmaf.cantidad) as total_unidades,
                   COUNT(DISTINCT afpl.id) as total_procesos_limpieza
            FROM areas_fisicas af
            LEFT JOIN productos_mobiliario_x_areas_fisicas pmaf ON af.id = pmaf.id_area
            LEFT JOIN areas_fisicas_x_procesos_limpieza afpl ON af.id = afpl.id_area_fisica AND afpl.activo = 1
            WHERE af.id_tenant = :id_tenant
            GROUP BY af.id
            ORDER BY af.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM areas_fisicas 
            WHERE activo = 1 AND id_tenant = :id_tenant
            ORDER BY nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT * FROM areas_fisicas 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'] ?? null;
        $ubicacion = Flight::request()->data['ubicacion'] ?? null;
        $capacidad = Flight::request()->data['capacidad'] ?? null;
        $mobiliario_general = Flight::request()->data['mobiliario_general'] ?? null;

        $sentence = $db->prepare("
            INSERT INTO areas_fisicas(
                id,
                id_tenant,
                nombre,
                descripcion,
                ubicacion,
                capacidad,
                mobiliario_general,
                activo,
                fecha_registro
            ) VALUES (
                :id,
                :id_tenant,
                :nombre,
                :descripcion,
                :ubicacion,
                :capacidad,
                :mobiliario_general,
                1,
                NOW()
            )
        ");

        $idArea = Uuid::generar();
        $sentence->bindValue(':id', $idArea);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':ubicacion', $ubicacion);
        $sentence->bindParam(':capacidad', $capacidad);
        $sentence->bindParam(':mobiliario_general', $mobiliario_general);
        $sentence->execute();

        $id = $idArea;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'] ?? null;
        $ubicacion = Flight::request()->data['ubicacion'] ?? null;
        $capacidad = Flight::request()->data['capacidad'] ?? null;
        $mobiliario_general = Flight::request()->data['mobiliario_general'] ?? null;
        $activo = Flight::request()->data['activo'] ?? 1;

        $sentence = $db->prepare("
            UPDATE areas_fisicas SET 
                nombre = :nombre,
                descripcion = :descripcion,
                ubicacion = :ubicacion,
                capacidad = :capacidad,
                mobiliario_general = :mobiliario_general,
                activo = :activo
            WHERE id = :id AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':ubicacion', $ubicacion);
        $sentence->bindParam(':capacidad', $capacidad);
        $sentence->bindParam(':mobiliario_general', $mobiliario_general);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // Verificar si tiene mobiliario asignado
        $sentence = $db->prepare("
            SELECT COUNT(*) as total 
            FROM productos_mobiliario_x_areas_fisicas 
            WHERE id_area = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();

        if ($result['total'] > 0) {
            Flight::json(array('error' => 'No se puede eliminar el área porque tiene mobiliario asignado'), 400);
            return;
        }

        // Verificar si tiene procesos de limpieza asignados
        $sentence = $db->prepare("
            SELECT COUNT(*) as total 
            FROM areas_fisicas_x_procesos_limpieza 
            WHERE id_area_fisica = :id AND activo = 1 AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();

        if ($result['total'] > 0) {
            Flight::json(array('error' => 'No se puede eliminar el área porque tiene procesos de limpieza asignados'), 400);
            return;
        }

        // Eliminar área (soft delete)
        $sentence = $db->prepare("UPDATE areas_fisicas SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    // Obtener mobiliario asignado a un área
    public static function getMobiliarioAsignado($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                pmaf.id,
                pmaf.id_producto_mobiliario,
                pmaf.cantidad,
                pmaf.orden_limpieza,
                pm.numero_serie,
                pm.fecha_adquisicion,
                p.nombre as nombre_producto,
                tpm.nombre as tipo_mobiliario,
                mp.id as id_movimiento,
                mp.fecha_movimiento as fecha_asignacion
            FROM productos_mobiliario_x_areas_fisicas pmaf
            INNER JOIN productos_mobiliario pm ON pmaf.id_producto_mobiliario = pm.id
            INNER JOIN productos p ON pm.id_producto = p.id
            LEFT JOIN tipos_producto_mobiliario tpm ON pm.id_tipo_producto_mobiliario = tpm.id
            LEFT JOIN movimientos_productos mp ON mp.id = (
                SELECT MAX(mp2.id) 
                FROM movimientos_productos mp2
                INNER JOIN movimientos_productos_detalle mpd ON mp2.id = mpd.id_movimiento
                WHERE mpd.id_producto = p.id 
                AND mp2.id_estado IN (2,3)
                AND mp2.id_concepto_movimiento = (
                    SELECT id FROM conceptos_movimiento 
                    WHERE nombre LIKE '%Asignación a Área Física%' 
                    LIMIT 1
                )
            )
            WHERE pmaf.id_area = :id_area AND pmaf.id_tenant = :id_tenant
            ORDER BY pmaf.orden_limpieza, p.nombre
        ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // ===== MÉTODOS PARA PROCESOS DE LIMPIEZA =====

    // Obtener procesos de limpieza asignados a un área
    public static function getProcesosLimpieza($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            afpl.*,
            tpl.nombre as nombre_proceso,
            tpl.descripcion as descripcion_proceso,
            p.nombre as periodicidad_nombre,
            p.codigo as periodicidad_codigo,
            p.dias_intervalo,
            -- Calcular carga semanal
            (
                (afpl.lunes + afpl.martes + afpl.miercoles + afpl.jueves + 
                 afpl.viernes + afpl.sabado + afpl.domingo) * 
                afpl.tiempo_estimado_minutos * afpl.veces_por_dia
            ) as carga_semanal_minutos
        FROM areas_fisicas_x_procesos_limpieza afpl
        INNER JOIN tipos_proceso_limpieza tpl ON afpl.id_tipo_proceso_limpieza = tpl.id
        LEFT JOIN periodicidad p ON afpl.id_periodicidad = p.id
        WHERE afpl.id_area_fisica = :id_area AND afpl.id_tenant = :id_tenant
        -- Removemos el filtro de activo para traer todos
        ORDER BY afpl.activo DESC, afpl.prioridad DESC, tpl.nombre
    ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Agregar método para reactivar
    public static function activarProcesoLimpieza($id)
    {
        $db = Flight::db();

        try {
            $sentence = $db->prepare("
            UPDATE areas_fisicas_x_procesos_limpieza 
            SET activo = 1 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('mensaje' => 'Proceso activado correctamente'));
        } catch (Exception $e) {
            Flight::json(array('error' => 'Error al activar proceso: ' . $e->getMessage()), 500);
        }
    }

    // Asignar proceso de limpieza a un área
    public static function asignarProcesoLimpieza()
    {
        $db = Flight::db();

        // Obtener los datos del request correctamente
        $request = Flight::request();
        $data = $request->data->getData(); // Obtener el array de datos

        try {
            // Verificar si ya existe esta asignación
            $checkSentence = $db->prepare("
                SELECT id FROM areas_fisicas_x_procesos_limpieza 
                WHERE id_area_fisica = :id_area 
                AND id_tipo_proceso_limpieza = :id_tipo_proceso
                AND activo = 1
                AND id_tenant = :id_tenant
            ");
            $checkSentence->bindParam(':id_area', $data['id_area_fisica']);
            $checkSentence->bindParam(':id_tipo_proceso', $data['id_tipo_proceso_limpieza']);
            $checkSentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkSentence->execute();

            if ($checkSentence->fetch()) {
                Flight::json(array('error' => 'Este proceso ya está asignado a esta área'), 400);
                return;
            }

            // Insertar nueva asignación
            $sentence = $db->prepare("
                INSERT INTO areas_fisicas_x_procesos_limpieza (
                    id,
                    id_tenant,
                    id_area_fisica,
                    id_tipo_proceso_limpieza,
                    id_periodicidad,
                    tiempo_estimado_minutos,
                    hora_sugerida,
                    prioridad,
                    lunes,
                    martes,
                    miercoles,
                    jueves,
                    viernes,
                    sabado,
                    domingo,
                    veces_por_dia,
                    activo
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_area_fisica,
                    :id_tipo_proceso_limpieza,
                    :id_periodicidad,
                    :tiempo_estimado_minutos,
                    :hora_sugerida,
                    :prioridad,
                    :lunes,
                    :martes,
                    :miercoles,
                    :jueves,
                    :viernes,
                    :sabado,
                    :domingo,
                    :veces_por_dia,
                    1
                )
            ");

            // Bind de parámetros
            $idAFPL = Uuid::generar();
            $sentence->bindValue(':id', $idAFPL);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_area_fisica', $data['id_area_fisica']);
            $sentence->bindParam(':id_tipo_proceso_limpieza', $data['id_tipo_proceso_limpieza']);
            $sentence->bindParam(':id_periodicidad', $data['id_periodicidad']);
            $sentence->bindParam(':tiempo_estimado_minutos', $data['tiempo_estimado_minutos']);

            // Manejar campos opcionales
            $hora_sugerida = isset($data['hora_sugerida']) ? $data['hora_sugerida'] : null;
            $prioridad = isset($data['prioridad']) ? $data['prioridad'] : 2;
            $lunes = isset($data['lunes']) ? $data['lunes'] : 0;
            $martes = isset($data['martes']) ? $data['martes'] : 0;
            $miercoles = isset($data['miercoles']) ? $data['miercoles'] : 0;
            $jueves = isset($data['jueves']) ? $data['jueves'] : 0;
            $viernes = isset($data['viernes']) ? $data['viernes'] : 0;
            $sabado = isset($data['sabado']) ? $data['sabado'] : 0;
            $domingo = isset($data['domingo']) ? $data['domingo'] : 0;
            $veces_por_dia = isset($data['veces_por_dia']) ? $data['veces_por_dia'] : 1;

            $sentence->bindParam(':hora_sugerida', $hora_sugerida);
            $sentence->bindParam(':prioridad', $prioridad);
            $sentence->bindParam(':lunes', $lunes);
            $sentence->bindParam(':martes', $martes);
            $sentence->bindParam(':miercoles', $miercoles);
            $sentence->bindParam(':jueves', $jueves);
            $sentence->bindParam(':viernes', $viernes);
            $sentence->bindParam(':sabado', $sabado);
            $sentence->bindParam(':domingo', $domingo);
            $sentence->bindParam(':veces_por_dia', $veces_por_dia);

            $sentence->execute();
            $id = $idAFPL;

            Flight::json(array('id' => $id, 'mensaje' => 'Proceso asignado correctamente'));
        } catch (Exception $e) {
            Flight::json(array('error' => 'Error al asignar proceso: ' . $e->getMessage()), 500);
        }
    }

    // Actualizar proceso de limpieza asignado
    public static function actualizarProcesoLimpieza()
    {
        $db = Flight::db();

        // Obtener los datos del request correctamente
        $request = Flight::request();
        $data = $request->data->getData();

        try {
            $sentence = $db->prepare("
                UPDATE areas_fisicas_x_procesos_limpieza SET
                    id_periodicidad = :id_periodicidad,
                    tiempo_estimado_minutos = :tiempo_estimado_minutos,
                    hora_sugerida = :hora_sugerida,
                    prioridad = :prioridad,
                    lunes = :lunes,
                    martes = :martes,
                    miercoles = :miercoles,
                    jueves = :jueves,
                    viernes = :viernes,
                    sabado = :sabado,
                    domingo = :domingo,
                    veces_por_dia = :veces_por_dia
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            // Bind de parámetros
            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_periodicidad', $data['id_periodicidad']);
            $sentence->bindParam(':tiempo_estimado_minutos', $data['tiempo_estimado_minutos']);

            // Manejar campos opcionales
            $hora_sugerida = isset($data['hora_sugerida']) ? $data['hora_sugerida'] : null;
            $prioridad = isset($data['prioridad']) ? $data['prioridad'] : 2;
            $lunes = isset($data['lunes']) ? $data['lunes'] : 0;
            $martes = isset($data['martes']) ? $data['martes'] : 0;
            $miercoles = isset($data['miercoles']) ? $data['miercoles'] : 0;
            $jueves = isset($data['jueves']) ? $data['jueves'] : 0;
            $viernes = isset($data['viernes']) ? $data['viernes'] : 0;
            $sabado = isset($data['sabado']) ? $data['sabado'] : 0;
            $domingo = isset($data['domingo']) ? $data['domingo'] : 0;
            $veces_por_dia = isset($data['veces_por_dia']) ? $data['veces_por_dia'] : 1;

            $sentence->bindParam(':hora_sugerida', $hora_sugerida);
            $sentence->bindParam(':prioridad', $prioridad);
            $sentence->bindParam(':lunes', $lunes);
            $sentence->bindParam(':martes', $martes);
            $sentence->bindParam(':miercoles', $miercoles);
            $sentence->bindParam(':jueves', $jueves);
            $sentence->bindParam(':viernes', $viernes);
            $sentence->bindParam(':sabado', $sabado);
            $sentence->bindParam(':domingo', $domingo);
            $sentence->bindParam(':veces_por_dia', $veces_por_dia);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            Flight::json(array('mensaje' => 'Proceso actualizado correctamente'));
        } catch (Exception $e) {
            Flight::json(array('error' => 'Error al actualizar proceso: ' . $e->getMessage()), 500);
        }
    }

    // Eliminar proceso de limpieza (soft delete)
    // En areas-fisicas.service.php - Método eliminarProcesoLimpieza
    public static function eliminarProcesoLimpieza($id)
    {
        $db = Flight::db();

        try {
            $sentence = $db->prepare("
            DELETE FROM areas_fisicas_x_procesos_limpieza 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('mensaje' => 'Proceso eliminado correctamente'));
        } catch (Exception $e) {
            Flight::json(array('error' => 'Error al eliminar proceso: ' . $e->getMessage()), 500);
        }
    }

    // Agregar nuevo método para inactivar
    public static function inactivarProcesoLimpieza($id)
    {
        $db = Flight::db();

        try {
            $sentence = $db->prepare("
            UPDATE areas_fisicas_x_procesos_limpieza 
            SET activo = 0 
            WHERE id = :id AND id_tenant = :id_tenant
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('mensaje' => 'Proceso inactivado correctamente'));
        } catch (Exception $e) {
            Flight::json(array('error' => 'Error al inactivar proceso: ' . $e->getMessage()), 500);
        }
    }

    // Obtener resumen de carga de trabajo
    public static function getCargaTrabajo($id_area)
    {
        $db = Flight::db();

        // Carga por día de la semana
        $sentence = $db->prepare("
            SELECT 
                SUM(lunes * tiempo_estimado_minutos * veces_por_dia) as carga_lunes,
                SUM(martes * tiempo_estimado_minutos * veces_por_dia) as carga_martes,
                SUM(miercoles * tiempo_estimado_minutos * veces_por_dia) as carga_miercoles,
                SUM(jueves * tiempo_estimado_minutos * veces_por_dia) as carga_jueves,
                SUM(viernes * tiempo_estimado_minutos * veces_por_dia) as carga_viernes,
                SUM(sabado * tiempo_estimado_minutos * veces_por_dia) as carga_sabado,
                SUM(domingo * tiempo_estimado_minutos * veces_por_dia) as carga_domingo,
                SUM((lunes + martes + miercoles + jueves + viernes + sabado + domingo) 
                    * tiempo_estimado_minutos * veces_por_dia) as carga_semanal_total
            FROM areas_fisicas_x_procesos_limpieza
            WHERE id_area_fisica = :id_area 
            AND activo = 1 AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json($response);
    }
    // ===== MÉTODOS PARA ELEMENTOS FÍSICOS =====

    public static function getElementosFisicosAsignados($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                SELECT 
                                    efaf.*,
                                    ef.nombre as nombre_elemento,
                                    ef.descripcion as descripcion_elemento,
                                    ef.material,
                                    ce.nombre as condicion_nombre,
                                    ce.color as condicion_color,
                                    um.nombre as unidad_medida,
                                    um.abreviatura
                                FROM elementos_fisicos_x_areas_fisicas efaf
                                INNER JOIN elementos_fisicos ef ON efaf.id_elemento_fisico = ef.id
                                LEFT JOIN condiciones_elemento ce ON efaf.id_condicion = ce.id
                                LEFT JOIN unidades_medida um ON ef.id_unidad_medida = um.id
                                WHERE efaf.id_area_fisica = :id_area AND efaf.id_tenant = :id_tenant
                                ORDER BY ef.nombre
                            ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getCondicionesElemento()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT * FROM condiciones_elemento 
        WHERE activo = 1 
        ORDER BY orden, nombre
    ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function asignarElementoFisico()
    {
        $db = Flight::db();
        $data = Flight::request()->data->getData();

        try {
            // Verificar si ya existe esta asignación
            $checkStmt = $db->prepare("
            SELECT id 
            FROM elementos_fisicos_x_areas_fisicas 
            WHERE id_elemento_fisico = :id_elemento 
            AND id_area_fisica = :id_area
            AND id_tenant = :id_tenant
        ");
            $checkStmt->bindParam(':id_elemento', $data['id_elemento_fisico']);
            $checkStmt->bindParam(':id_area', $data['id_area_fisica']);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $existe = $checkStmt->fetch();

            if ($existe) {
                Flight::json(['error' => 'Este elemento ya está asignado a esta área'], 400);
                return;
            }

            // Si no existe, crear nueva asignación
            $stmt = $db->prepare("
            INSERT INTO elementos_fisicos_x_areas_fisicas (
                id,
                id_tenant,
                id_elemento_fisico,
                id_area_fisica,
                cantidad,
                id_condicion,
                observaciones,
                fecha_ultima_inspeccion
            ) VALUES (
                :id,
                :id_tenant,
                :id_elemento_fisico,
                :id_area_fisica,
                :cantidad,
                :id_condicion,
                :observaciones,
                :fecha_inspeccion
            )
        ");

            $idEFAF = Uuid::generar();
            $stmt->bindValue(':id', $idEFAF);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_elemento_fisico', $data['id_elemento_fisico']);
            $stmt->bindParam(':id_area_fisica', $data['id_area_fisica']);
            $stmt->bindParam(':cantidad', $data['cantidad']);
            $stmt->bindParam(':id_condicion', $data['id_condicion']);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            $stmt->bindParam(':fecha_inspeccion', $data['fecha_ultima_inspeccion']);
            $stmt->execute();

            $id = $idEFAF;
            Flight::json(['id' => $id, 'mensaje' => 'Elemento asignado correctamente']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al asignar elemento: ' . $e->getMessage()], 500);
        }
    }

    public static function actualizarAsignacionElemento()
    {
        $db = Flight::db();
        $data = Flight::request()->data->getData();

        try {
            $stmt = $db->prepare("
            UPDATE elementos_fisicos_x_areas_fisicas 
            SET cantidad = :cantidad,
                id_condicion = :id_condicion,
                observaciones = :observaciones,
                fecha_ultima_inspeccion = :fecha_inspeccion
            WHERE id = :id AND id_tenant = :id_tenant
        ");

            $stmt->bindParam(':cantidad', $data['cantidad']);
            $stmt->bindParam(':id_condicion', $data['id_condicion']);
            $stmt->bindParam(':observaciones', $data['observaciones']);
            $stmt->bindParam(':fecha_inspeccion', $data['fecha_ultima_inspeccion']);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['mensaje' => 'Asignación actualizada correctamente']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    public static function eliminarAsignacionElemento($id)
    {
        $db = Flight::db();

        try {
            $stmt = $db->prepare("DELETE FROM elementos_fisicos_x_areas_fisicas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['mensaje' => 'Asignación eliminada correctamente']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
    public static function getDisponiblesParaArea($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                    SELECT 
                                        ef.id,
                                        ef.nombre,
                                        ef.descripcion,
                                        ef.material,
                                        um.nombre as unidad_medida,
                                        um.abreviatura
                                    FROM elementos_fisicos ef
                                    LEFT JOIN unidades_medida um ON ef.id_unidad_medida = um.id
                                    WHERE ef.id NOT IN (
                                        SELECT id_elemento_fisico 
                                        FROM elementos_fisicos_x_areas_fisicas 
                                        WHERE id_area_fisica = :id_area
                                    )
                                    AND ef.id_tenant = :id_tenant
                                    ORDER BY ef.nombre
                                ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    public static function getElementosFisicosDisponibles($id_area)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                    SELECT 
                                        ef.id,
                                        ef.nombre,
                                        ef.descripcion,
                                        ef.material,
                                        um.nombre as unidad_medida,
                                        um.abreviatura
                                    FROM elementos_fisicos ef
                                    LEFT JOIN unidades_medida um ON ef.id_unidad_medida = um.id
                                    WHERE ef.id NOT IN (
                                        SELECT id_elemento_fisico 
                                        FROM elementos_fisicos_x_areas_fisicas 
                                        WHERE id_area_fisica = :id_area
                                    )
                                    AND ef.id_tenant = :id_tenant
                                    ORDER BY ef.nombre
    ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}