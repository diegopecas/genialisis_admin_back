<?php
class ReportesPago
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.id_acudiente,
                rp.id_persona_reporta,
                rp.id_colaborador_recibio,
                rp.id_tipo_pago,
                rp.valor,
                rp.fecha_pago,
                rp.observaciones,
                rp.id_pago_recibido,
                rp.id_documento_persona,
                rp.estado,
                rp.fecha_registro,
                rp.fecha_asociacion,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido)) AS nombre_estudiante,
                TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_acudiente,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio,
                dp.nombre_archivo AS comprobante_nombre,
                dp.ruta_archivo AS comprobante_ruta
            FROM reportes_pago rp
            INNER JOIN estudiantes est ON est.id = rp.id_estudiante
            INNER JOIN personas pe ON pe.id = est.id_persona
            INNER JOIN acudientes acu ON acu.id = rp.id_acudiente
            INNER JOIN personas pa ON pa.id = acu.id_persona
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            LEFT JOIN documentos_personas dp ON dp.id = rp.id_documento_persona
            WHERE rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $response = self::agregarHorasHabiles($response, $db);

        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.id_acudiente,
                rp.id_persona_reporta,
                rp.id_colaborador_recibio,
                rp.id_tipo_pago,
                rp.valor,
                rp.fecha_pago,
                rp.observaciones,
                rp.id_pago_recibido,
                rp.estado,
                rp.fecha_registro,
                rp.fecha_asociacion,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido)) AS nombre_estudiante,
                TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_acudiente,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio
            FROM reportes_pago rp
            INNER JOIN estudiantes est ON est.id = rp.id_estudiante
            INNER JOIN personas pe ON pe.id = est.id_persona
            INNER JOIN acudientes acu ON acu.id = rp.id_acudiente
            INNER JOIN personas pa ON pa.id = acu.id_persona
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            WHERE rp.id = :id AND rp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.id_acudiente,
                rp.id_persona_reporta,
                rp.id_colaborador_recibio,
                rp.id_tipo_pago,
                rp.valor,
                rp.fecha_pago,
                rp.observaciones,
                rp.id_pago_recibido,
                rp.id_documento_persona,
                rp.estado,
                rp.fecha_registro,
                rp.fecha_asociacion,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta,
                dp.nombre_archivo AS comprobante_nombre,
                dp.ruta_archivo AS comprobante_ruta
            FROM reportes_pago rp
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            LEFT JOIN documentos_personas dp ON dp.id = rp.id_documento_persona
            WHERE rp.id_estudiante = :id_estudiante AND rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro DESC
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $response = self::agregarHorasHabiles($response, $db);

        Flight::json($response);
    }

    public static function getByPersonaReporta($id_persona)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.id_acudiente,
                rp.id_colaborador_recibio,
                rp.id_tipo_pago,
                rp.valor,
                rp.fecha_pago,
                rp.observaciones,
                rp.id_pago_recibido,
                rp.estado,
                rp.fecha_registro,
                rp.fecha_asociacion,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido)) AS nombre_estudiante,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio
            FROM reportes_pago rp
            INNER JOIN estudiantes est ON est.id = rp.id_estudiante
            INNER JOIN personas pe ON pe.id = est.id_persona
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            WHERE rp.id_persona_reporta = :id_persona AND rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro DESC
        ");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener reportes pendientes (sin pago asociado)
     * Usado para el informe de control
     */
    public static function getPendientes()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.id_acudiente,
                rp.id_colaborador_recibio,
                rp.id_tipo_pago,
                rp.valor,
                rp.fecha_pago,
                rp.observaciones,
                rp.fecha_registro,
                rp.estado,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido)) AS nombre_estudiante,
                TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_acudiente,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio
            FROM reportes_pago rp
            INNER JOIN estudiantes est ON est.id = rp.id_estudiante
            INNER JOIN personas pe ON pe.id = est.id_persona
            INNER JOIN acudientes acu ON acu.id = rp.id_acudiente
            INNER JOIN personas pa ON pa.id = acu.id_persona
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            WHERE rp.estado = 'pendiente' AND rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $response = self::agregarHorasHabiles($response, $db);

        Flight::json($response);
    }

    /**
     * Obtener reportes pendientes por colaborador
     */
    public static function getPendientesByColaborador($id_colaborador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.id_estudiante,
                rp.valor,
                rp.fecha_pago,
                rp.fecha_registro,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre, pe.primer_apellido, pe.segundo_apellido)) AS nombre_estudiante,
                TRIM(CONCAT_WS(' ', pa.primer_nombre, pa.segundo_nombre, pa.primer_apellido, pa.segundo_apellido)) AS nombre_acudiente
            FROM reportes_pago rp
            INNER JOIN estudiantes est ON est.id = rp.id_estudiante
            INNER JOIN personas pe ON pe.id = est.id_persona
            INNER JOIN acudientes acu ON acu.id = rp.id_acudiente
            INNER JOIN personas pa ON pa.id = acu.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            WHERE rp.id_colaborador_recibio = :id_colaborador
              AND rp.estado = 'pendiente'
              AND rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro ASC
        ");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $response = self::agregarHorasHabiles($response, $db);

        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id_estudiante = Flight::request()->data['id_estudiante'];
            $id_persona_reporta = Flight::request()->data['id_persona_reporta'];
            $id_colaborador_recibio = Flight::request()->data['id_colaborador_recibio'];
            $id_tipo_pago = Flight::request()->data['id_tipo_pago'];
            $valor = Flight::request()->data['valor'];
            $fecha_pago = Flight::request()->data['fecha_pago'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            // Buscar id_acudiente a partir de id_persona e id_estudiante
            $stmtAcudiente = $db->prepare("
                SELECT id FROM acudientes 
                WHERE id_persona = :id_persona 
                  AND id_estudiante = :id_estudiante 
                  AND activo = 1 
                  AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $stmtAcudiente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtAcudiente->bindParam(':id_persona', $id_persona_reporta);
            $stmtAcudiente->bindParam(':id_estudiante', $id_estudiante);
            $stmtAcudiente->execute();
            $acudiente = $stmtAcudiente->fetch(PDO::FETCH_ASSOC);

            if (!$acudiente) {
                $db->rollback();
                Flight::json(['error' => 'No se encontró registro de acudiente para esta persona y estudiante'], 400);
                return;
            }

            $id_acudiente = $acudiente['id'];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO reportes_pago 
                    (id, id_tenant, id_estudiante, id_acudiente, id_persona_reporta, id_colaborador_recibio, id_tipo_pago, valor, fecha_pago, observaciones, estado, fecha_registro) 
                VALUES 
                    (:id, :id_tenant, :id_estudiante, :id_acudiente, :id_persona_reporta, :id_colaborador_recibio, :id_tipo_pago, :valor, :fecha_pago, :observaciones, 'pendiente', NOW())
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_acudiente', $id_acudiente);
            $sentence->bindParam(':id_persona_reporta', $id_persona_reporta);
            $sentence->bindParam(':id_colaborador_recibio', $id_colaborador_recibio);
            $sentence->bindParam(':id_tipo_pago', $id_tipo_pago);
            $sentence->bindParam(':valor', $valor);
            $sentence->bindParam(':fecha_pago', $fecha_pago);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();
            $id = $idNew;

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en ReportesPago::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Asociar un reporte de pago a un pago recibido
     */
    public static function asociarPago()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $id_pago_recibido = Flight::request()->data['id_pago_recibido'];

            $sentence = $db->prepare("
                UPDATE reportes_pago 
                SET id_pago_recibido = :id_pago_recibido, 
                    estado = 'asociado', 
                    fecha_asociacion = NOW() 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
            $sentence->execute();

            $db->commit();
            Flight::json(array('id' => $id, 'message' => 'Reporte asociado correctamente'));
        } catch (Exception $e) {
            $db->rollback();
            error_log("Error en ReportesPago::asociarPago: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener reportes asociados a un pago recibido
     */
    public static function getByPagoRecibido($id_pago_recibido)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.valor,
                rp.fecha_pago,
                rp.fecha_registro,
                rp.estado,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta
            FROM reportes_pago rp
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            WHERE rp.id_pago_recibido = :id_pago_recibido AND rp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener reportes pendientes de un estudiante (para asociar desde el sistema institucional)
     */
    public static function getPendientesByEstudiante($id_estudiante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rp.id,
                rp.valor,
                rp.fecha_pago,
                rp.id_tipo_pago,
                rp.observaciones,
                rp.id_documento_persona,
                rp.fecha_registro,
                tp.nombre AS nombre_tipo_pago,
                TRIM(CONCAT_WS(' ', pr.primer_nombre, pr.segundo_nombre, pr.primer_apellido, pr.segundo_apellido)) AS nombre_persona_reporta,
                TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.segundo_nombre, pc.primer_apellido, pc.segundo_apellido)) AS nombre_colaborador_recibio,
                dp.nombre_archivo AS comprobante_nombre,
                dp.ruta_archivo AS comprobante_ruta
            FROM reportes_pago rp
            INNER JOIN personas pr ON pr.id = rp.id_persona_reporta
            INNER JOIN colaboradores col ON col.id = rp.id_colaborador_recibio
            INNER JOIN personas pc ON pc.id = col.id_persona
            INNER JOIN tipos_pagos tp ON tp.id = rp.id_tipo_pago
            LEFT JOIN documentos_personas dp ON dp.id = rp.id_documento_persona
            WHERE rp.id_estudiante = :id_estudiante
              AND rp.estado = 'pendiente'
              AND rp.id_tenant = :id_tenant
            ORDER BY rp.fecha_registro ASC
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $response = self::agregarHorasHabiles($response, $db);

        Flight::json($response);
    }

    public static function delete($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("DELETE FROM reportes_pago WHERE id = :id AND estado = 'pendiente' AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() > 0) {
                Flight::json(["success" => true, "message" => "Reporte eliminado correctamente"]);
            } else {
                Flight::json(["success" => false, "message" => "No se encontró el reporte o ya está asociado"], 404);
            }
        } catch (Exception $e) {
            Flight::json(["success" => false, "error" => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener tipos de pago visibles en el portal de padres
     */
    public static function getTiposPagoPortal()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM tipos_pagos WHERE visible_portal_padres = 1 AND id_tenant = :id_tenant ORDER BY nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Obtener colaboradores activos para el select del portal de padres
     */
    public static function getColaboradoresActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                c.id,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                car.nombre AS nombre_cargo
            FROM colaboradores c
            INNER JOIN personas p ON p.id = c.id_persona
            LEFT JOIN cargos car ON car.id = c.id_cargo
            WHERE c.activo = 1
            AND c.id_tenant = :id_tenant
            ORDER BY p.primer_nombre, p.primer_apellido
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Actualizar id_documento_persona de un reporte
     * PUT /reportes-pago/documento
     */
    public static function actualizarDocumento()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $id_documento_persona = Flight::request()->data['id_documento_persona'];

            $sentence = $db->prepare("
                UPDATE reportes_pago 
                SET id_documento_persona = :id_documento_persona 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_documento_persona', $id_documento_persona);
            $sentence->execute();

            Flight::json(array('id' => $id, 'id_documento_persona' => $id_documento_persona));
        } catch (Exception $e) {
            error_log('Error en actualizarDocumento: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Agrega horas hábiles a un array de reportes.
     * Carga el calendario UNA sola vez para todos los registros.
     */
    private static function agregarHorasHabiles($registros, $db)
    {
        if (empty($registros)) {
            return $registros;
        }

        // Encontrar la fecha más antigua de todos los registros
        $fechaMin = null;
        foreach ($registros as $reg) {
            $fecha = substr($reg['fecha_registro'], 0, 10);
            if ($fechaMin === null || $fecha < $fechaMin) {
                $fechaMin = $fecha;
            }
        }

        $ahora = date('Y-m-d H:i:s');
        $hoy = date('Y-m-d');

        // Cargar calendario hábil UNA sola vez para todo el rango
        $calendarioHabil = Calendarios::obtenerCalendarioHabil($fechaMin, $hoy, $db);

        // Calcular para cada registro
        foreach ($registros as &$reg) {
            $tiempo = Calendarios::calcularTiempoHabil($reg['fecha_registro'], $ahora, $calendarioHabil);
            $reg['horas_habiles'] = $tiempo['total_horas'];
            $reg['tiempo_habil_texto'] = $tiempo['texto'];
            $reg['dias_habiles'] = $tiempo['dias'];
            $reg['horas_habiles_restantes'] = $tiempo['horas'];
        }
        unset($reg);

        return $registros;
    }
}