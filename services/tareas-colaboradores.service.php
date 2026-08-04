<?php
class TareasColaboradores
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    tc.id, tc.id_colaborador, tc.id_estudiante, tc.id_tipo_tarea,
                    IFNULL(ttt.nombre, '') AS nombre_tipo_tarea,
                    tc.descripcion, tc.fecha_limite, tc.hora_inicio, tc.hora_fin,
                    tc.id_estado, etc.nombre AS nombre_estado, etc.color AS color_estado,
                    tc.origen, tc.id_historial_origen, tc.observaciones,
                    tc.id_usuario_registro, tc.fecha_registro,
                    TRIM(CONCAT(IFNULL(pc.primer_nombre,''), ' ', IFNULL(pc.primer_apellido,''))) AS nombre_colaborador,
                    TRIM(CONCAT(IFNULL(pe.primer_nombre,''), ' ', IFNULL(pe.segundo_nombre,''), ' ', IFNULL(pe.primer_apellido,''), ' ', IFNULL(pe.segundo_apellido,''))) AS nombre_estudiante
                FROM tareas_colaboradores tc
                INNER JOIN estados_tareas_colaboradores etc ON tc.id_estado = etc.id
                INNER JOIN colaboradores c ON tc.id_colaborador = c.id
                INNER JOIN personas pc ON c.id_persona = pc.id
                LEFT JOIN tipos_tareas_colaboradores ttt ON tc.id_tipo_tarea = ttt.id
                LEFT JOIN estudiantes e ON tc.id_estudiante = e.id
                LEFT JOIN personas pe ON e.id_persona = pe.id
                WHERE tc.id_tenant = :id_tenant
                ORDER BY tc.fecha_registro DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tareas'), 500);
        }
    }

    public static function getByColaborador($id_colaborador)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    tc.id, tc.id_colaborador, tc.id_estudiante, tc.id_tipo_tarea,
                    IFNULL(ttt.nombre, '') AS nombre_tipo_tarea,
                    tc.descripcion, tc.fecha_limite, tc.hora_inicio, tc.hora_fin,
                    tc.id_estado, etc.nombre AS nombre_estado, etc.color AS color_estado,
                    tc.origen, tc.id_historial_origen, tc.observaciones,
                    tc.id_usuario_registro, tc.fecha_registro,
                    TRIM(CONCAT(IFNULL(pe.primer_nombre,''), ' ', IFNULL(pe.segundo_nombre,''), ' ', IFNULL(pe.primer_apellido,''), ' ', IFNULL(pe.segundo_apellido,''))) AS nombre_estudiante
                FROM tareas_colaboradores tc
                INNER JOIN estados_tareas_colaboradores etc ON tc.id_estado = etc.id
                LEFT JOIN tipos_tareas_colaboradores ttt ON tc.id_tipo_tarea = ttt.id
                LEFT JOIN estudiantes e ON tc.id_estudiante = e.id
                LEFT JOIN personas pe ON e.id_persona = pe.id
                WHERE tc.id_colaborador = :id_colaborador
                AND tc.id_tenant = :id_tenant
                ORDER BY tc.id_estado ASC, tc.fecha_limite ASC, tc.fecha_registro DESC
            ");
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::getByColaborador: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tareas del colaborador'), 500);
        }
    }

    /**
     * Obtiene las tareas cuya fecha_limite cae dentro del mes/anio indicado.
     * Pensado para el calendario de colaboradores. Las tareas sin fecha_limite
     * se excluyen porque no tienen dónde ubicarse en el calendario.
     */
    public static function getTareasPorMes()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $mes = Flight::request()->query['mes'];
            $anio = Flight::request()->query['anio'];

            $sentence = $db->prepare("
                SELECT 
                    tc.id, tc.id_colaborador, tc.id_estudiante, tc.id_tipo_tarea,
                    IFNULL(ttt.nombre, '') AS nombre_tipo_tarea,
                    tc.descripcion, tc.fecha_limite, tc.hora_inicio, tc.hora_fin,
                    tc.id_estado, etc.nombre AS nombre_estado, etc.color AS color_estado,
                    tc.origen, tc.id_historial_origen, tc.observaciones,
                    tc.id_usuario_registro, tc.fecha_registro,
                    TRIM(CONCAT(IFNULL(pc.primer_nombre,''), ' ', IFNULL(pc.primer_apellido,''))) AS nombre_colaborador,
                    TRIM(CONCAT(IFNULL(pe.primer_nombre,''), ' ', IFNULL(pe.segundo_nombre,''), ' ', IFNULL(pe.primer_apellido,''), ' ', IFNULL(pe.segundo_apellido,''))) AS nombre_estudiante
                FROM tareas_colaboradores tc
                INNER JOIN estados_tareas_colaboradores etc ON tc.id_estado = etc.id
                INNER JOIN colaboradores c ON tc.id_colaborador = c.id
                INNER JOIN personas pc ON c.id_persona = pc.id
                LEFT JOIN tipos_tareas_colaboradores ttt ON tc.id_tipo_tarea = ttt.id
                LEFT JOIN estudiantes e ON tc.id_estudiante = e.id
                LEFT JOIN personas pe ON e.id_persona = pe.id
                WHERE tc.fecha_limite IS NOT NULL
                  AND MONTH(tc.fecha_limite) = :mes
                  AND YEAR(tc.fecha_limite) = :anio
                  AND tc.id_tenant = :id_tenant
                ORDER BY tc.fecha_limite ASC, tc.hora_inicio ASC
            ");
            $sentence->bindParam(':mes', $mes, PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::getTareasPorMes: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tareas del mes'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    tc.id, tc.id_colaborador, tc.id_estudiante, tc.id_tipo_tarea,
                    IFNULL(ttt.nombre, '') AS nombre_tipo_tarea,
                    tc.descripcion, tc.fecha_limite, tc.hora_inicio, tc.hora_fin,
                    tc.id_estado, etc.nombre AS nombre_estado,
                    tc.origen, tc.id_historial_origen, tc.observaciones,
                    tc.id_usuario_registro, tc.fecha_registro,
                    TRIM(CONCAT(IFNULL(pe.primer_nombre,''), ' ', IFNULL(pe.segundo_nombre,''), ' ', IFNULL(pe.primer_apellido,''), ' ', IFNULL(pe.segundo_apellido,''))) AS nombre_estudiante
                FROM tareas_colaboradores tc
                INNER JOIN estados_tareas_colaboradores etc ON tc.id_estado = etc.id
                LEFT JOIN tipos_tareas_colaboradores ttt ON tc.id_tipo_tarea = ttt.id
                LEFT JOIN estudiantes e ON tc.id_estudiante = e.id
                LEFT JOIN personas pe ON e.id_persona = pe.id
                WHERE tc.id = :id
                AND tc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tarea'), 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $id_estado = isset($data['id_estado']) ? $data['id_estado'] : 1;
            $id_estudiante = isset($data['id_estudiante']) ? $data['id_estudiante'] : null;
            $id_tipo_tarea = isset($data['id_tipo_tarea']) ? $data['id_tipo_tarea'] : null;
            $fecha_limite = isset($data['fecha_limite']) ? $data['fecha_limite'] : null;
            $hora_inicio = isset($data['hora_inicio']) ? $data['hora_inicio'] : null;
            $hora_fin = isset($data['hora_fin']) ? $data['hora_fin'] : null;
            $origen = isset($data['origen']) ? $data['origen'] : 'manual';
            $id_historial_origen = isset($data['id_historial_origen']) ? $data['id_historial_origen'] : null;
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;
            $id_usuario_registro = isset($data['id_usuario_registro']) ? $data['id_usuario_registro'] : null;

            $sentence = $db->prepare("
                INSERT INTO tareas_colaboradores (
                    id, id_tenant, id_colaborador, id_estudiante, id_tipo_tarea, descripcion, fecha_limite,
                    hora_inicio, hora_fin, id_estado, origen,
                    id_historial_origen, observaciones, id_usuario_registro
                ) VALUES (
                    :id, :id_tenant, :id_colaborador, :id_estudiante, :id_tipo_tarea, :descripcion, :fecha_limite,
                    :hora_inicio, :hora_fin, :id_estado, :origen,
                    :id_historial_origen, :observaciones, :id_usuario_registro
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_colaborador', $data['id_colaborador']);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_tipo_tarea', $id_tipo_tarea);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':fecha_limite', $fecha_limite);
            $sentence->bindParam(':hora_inicio', $hora_inicio);
            $sentence->bindParam(':hora_fin', $hora_fin);
            $sentence->bindParam(':id_estado', $id_estado, PDO::PARAM_INT);
            $sentence->bindParam(':origen', $origen);
            $sentence->bindParam(':id_historial_origen', $id_historial_origen);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);

            $sentence->execute();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::new: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear tarea: ' . $e->getMessage()), 500);
        }
    }

    public static function crearMasivo()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $tareas = $data['tareas'];
            if (!is_array($tareas) || count($tareas) === 0) {
                Flight::json(array('error' => 'No se recibieron tareas'), 400);
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO tareas_colaboradores (
                    id, id_tenant, id_colaborador, id_estudiante, id_tipo_tarea, descripcion, fecha_limite,
                    hora_inicio, hora_fin, id_estado, origen, observaciones, id_usuario_registro
                ) VALUES (
                    :id, :id_tenant, :id_colaborador, :id_estudiante, :id_tipo_tarea, :descripcion, :fecha_limite,
                    :hora_inicio, :hora_fin, 1, 'manual', :observaciones, :id_usuario_registro
                )
            ");

            $db->beginTransaction();
            $count = 0;

            foreach ($tareas as $t) {
                $id_estudiante = isset($t['id_estudiante']) ? $t['id_estudiante'] : null;
                $id_tipo_tarea = isset($t['id_tipo_tarea']) ? $t['id_tipo_tarea'] : null;
                $hora_inicio = isset($t['hora_inicio']) ? $t['hora_inicio'] : null;
                $hora_fin = isset($t['hora_fin']) ? $t['hora_fin'] : null;
                $observaciones = isset($t['observaciones']) ? $t['observaciones'] : null;
                $id_usuario_registro = isset($t['id_usuario_registro']) ? $t['id_usuario_registro'] : null;

                $idTarea = Uuid::generar();
                $sentence->bindValue(':id', $idTarea);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_colaborador', $t['id_colaborador']);
                $sentence->bindParam(':id_estudiante', $id_estudiante);
                $sentence->bindParam(':id_tipo_tarea', $id_tipo_tarea);
                $sentence->bindParam(':descripcion', $t['descripcion']);
                $sentence->bindParam(':fecha_limite', $t['fecha_limite']);
                $sentence->bindParam(':hora_inicio', $hora_inicio);
                $sentence->bindParam(':hora_fin', $hora_fin);
                $sentence->bindParam(':observaciones', $observaciones);
                $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);
                $sentence->execute();
                $count++;
            }

            $db->commit();
            Flight::json(array('creadas' => $count));
        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            error_log('Error en TareasColaboradores::crearMasivo: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear tareas masivas: ' . $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $id_estudiante = isset($data['id_estudiante']) ? $data['id_estudiante'] : null;
            $id_tipo_tarea = isset($data['id_tipo_tarea']) ? $data['id_tipo_tarea'] : null;
            $fecha_limite = isset($data['fecha_limite']) ? $data['fecha_limite'] : null;
            $hora_inicio = isset($data['hora_inicio']) ? $data['hora_inicio'] : null;
            $hora_fin = isset($data['hora_fin']) ? $data['hora_fin'] : null;
            $observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;

            $sentence = $db->prepare("
                UPDATE tareas_colaboradores SET
                    id_estudiante = :id_estudiante,
                    id_tipo_tarea = :id_tipo_tarea,
                    descripcion = :descripcion,
                    fecha_limite = :fecha_limite,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    id_estado = :id_estado,
                    observaciones = :observaciones
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_tipo_tarea', $id_tipo_tarea);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':fecha_limite', $fecha_limite);
            $sentence->bindParam(':hora_inicio', $hora_inicio);
            $sentence->bindParam(':hora_fin', $hora_fin);
            $sentence->bindParam(':id_estado', $data['id_estado'], PDO::PARAM_INT);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();

            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::replace: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar tarea'), 500);
        }
    }

    /**
     * Actualiza únicamente el estado de una tarea (cambio rápido desde el calendario).
     * Recibe { id, id_estado }.
     */
    public static function cambiarEstado()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            if (!isset($data['id']) || !isset($data['id_estado'])) {
                Flight::json(array('error' => 'Faltan datos: id e id_estado son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE tareas_colaboradores 
                SET id_estado = :id_estado 
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_estado', $data['id_estado'], PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::cambiarEstado: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al cambiar el estado de la tarea'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $check = $db->prepare("SELECT origen FROM tareas_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $check->bindParam(':id', $data['id']);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();
            $tarea = $check->fetch(PDO::FETCH_ASSOC);

            if ($tarea && $tarea['origen'] !== 'manual') {
                Flight::json(array('error' => 'Solo se pueden eliminar tareas creadas manualmente'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM tareas_colaboradores WHERE id = :id AND origen = 'manual' AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $data['id']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en TareasColaboradores::delete: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar tarea'), 500);
        }
    }
}