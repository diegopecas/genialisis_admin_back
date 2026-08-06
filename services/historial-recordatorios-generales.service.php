<?php
class HistorialRecordatoriosGenerales
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hrg.id,
                    hrg.id_estudiante,
                    hrg.id_persona_acudiente,
                    hrg.telefono_usado,
                    hrg.nombre_destinatario,
                    hrg.tipo_recordatorio,
                    hrg.medio_envio,
                    hrg.compromiso,
                    hrg.fecha_compromiso,
                    hrg.id_usuario,
                    hrg.fecha_envio
                FROM historial_recordatorios_generales hrg
                WHERE hrg.id_tenant = :id_tenant
                ORDER BY hrg.fecha_envio DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HistorialRecordatoriosGenerales::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener historial de recordatorios generales'), 500);
        }
    }

    public static function getByEstudiante($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hrg.id,
                    hrg.id_estudiante,
                    hrg.id_persona_acudiente,
                    hrg.telefono_usado,
                    hrg.nombre_destinatario,
                    hrg.tipo_recordatorio,
                    hrg.medio_envio,
                    hrg.compromiso,
                    hrg.fecha_compromiso,
                    hrg.id_usuario,
                    hrg.fecha_envio
                FROM historial_recordatorios_generales hrg
                WHERE hrg.id_estudiante = :id_estudiante AND hrg.id_tenant = :id_tenant
                ORDER BY hrg.fecha_envio DESC
            ");
            $sentence->bindParam(':id_estudiante', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en HistorialRecordatoriosGenerales::getByEstudiante: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener historial por estudiante'), 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $id_estudiante = $request->data['id_estudiante'];
            $id_persona_acudiente = isset($request->data['id_persona_acudiente']) ? $request->data['id_persona_acudiente'] : null;
            $telefono_usado = isset($request->data['telefono_usado']) ? $request->data['telefono_usado'] : null;
            $nombre_destinatario = isset($request->data['nombre_destinatario']) ? $request->data['nombre_destinatario'] : null;
            $tipo_recordatorio = $request->data['tipo_recordatorio'];
            $medio_envio = isset($request->data['medio_envio']) ? $request->data['medio_envio'] : 'whatsapp';
            $compromiso = isset($request->data['compromiso']) ? $request->data['compromiso'] : null;
            $fecha_compromiso = isset($request->data['fecha_compromiso']) ? $request->data['fecha_compromiso'] : null;
            $id_usuario = isset($request->data['id_usuario']) ? $request->data['id_usuario'] : null;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO historial_recordatorios_generales (
                    id, id_tenant, id_estudiante, id_persona_acudiente, telefono_usado, nombre_destinatario,
                    tipo_recordatorio, medio_envio, compromiso, fecha_compromiso, id_usuario
                ) VALUES (
                    :id, :id_tenant, :id_estudiante, :id_persona_acudiente, :telefono_usado, :nombre_destinatario,
                    :tipo_recordatorio, :medio_envio, :compromiso, :fecha_compromiso, :id_usuario
                )
            ");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':id_persona_acudiente', $id_persona_acudiente);
            $sentence->bindParam(':telefono_usado', $telefono_usado);
            $sentence->bindParam(':nombre_destinatario', $nombre_destinatario);
            $sentence->bindParam(':tipo_recordatorio', $tipo_recordatorio);
            $sentence->bindParam(':medio_envio', $medio_envio);
            $sentence->bindParam(':compromiso', $compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);
            $sentence->bindParam(':id_usuario', $id_usuario);

            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en HistorialRecordatoriosGenerales::new: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear registro de historial'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $id = $request->data['id'];
            $compromiso = isset($request->data['compromiso']) ? $request->data['compromiso'] : null;
            $fecha_compromiso = isset($request->data['fecha_compromiso']) ? $request->data['fecha_compromiso'] : null;

            $sentence = $db->prepare("
                UPDATE historial_recordatorios_generales SET
                    compromiso = :compromiso,
                    fecha_compromiso = :fecha_compromiso
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':compromiso', $compromiso);
            $sentence->bindParam(':fecha_compromiso', $fecha_compromiso);

            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en HistorialRecordatoriosGenerales::replace: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar registro de historial'), 500);
        }
    }
}