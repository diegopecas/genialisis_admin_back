<?php
class HistorialRecordatoriosPago
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_estudiante, id_persona_acudiente, telefono_usado, nombre_destinatario, tipo_recordatorio, monto_notificado, compromiso, fecha_compromiso, id_usuario, fecha_envio FROM historial_recordatorios_pago WHERE id_tenant = :id_tenant ORDER BY fecha_envio DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll historial_recordatorios_pago: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener historial', 'detalles' => $e->getMessage()], 500);
        }
    }

    public static function getByEstudiante($idEstudiante)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    hrp.id, 
                    hrp.id_estudiante, 
                    hrp.id_persona_acudiente, 
                    hrp.telefono_usado, 
                    hrp.nombre_destinatario, 
                    hrp.tipo_recordatorio, 
                    hrp.monto_notificado, 
                    hrp.compromiso,
                    hrp.fecha_compromiso,
                    hrp.id_usuario, 
                    hrp.fecha_envio,
                    TRIM(CONCAT_WS(' ', pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido)) AS nombre_usuario
                FROM historial_recordatorios_pago hrp
                LEFT JOIN usuarios u ON u.id = hrp.id_usuario
                LEFT JOIN personas pu ON pu.id = u.id_persona
                WHERE hrp.id_estudiante = :id_estudiante AND hrp.id_tenant = :id_tenant
                ORDER BY hrp.fecha_envio DESC
            ");
            $sentence->bindParam(':id_estudiante', $idEstudiante);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByEstudiante historial_recordatorios_pago: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al obtener historial del estudiante', 'detalles' => $e->getMessage()], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $idNew = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO historial_recordatorios_pago (id, id_tenant, id_estudiante, id_persona_acudiente, telefono_usado, nombre_destinatario, tipo_recordatorio, monto_notificado, id_usuario, fecha_envio) 
                VALUES (:id, :id_tenant, :id_estudiante, :id_persona_acudiente, :telefono_usado, :nombre_destinatario, :tipo_recordatorio, :monto_notificado, :id_usuario, NOW())
            ");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $data['id_estudiante']);
            $sentence->bindParam(':id_persona_acudiente', $data['id_persona_acudiente']);
            $sentence->bindParam(':telefono_usado', $data['telefono_usado']);
            $sentence->bindParam(':nombre_destinatario', $data['nombre_destinatario']);
            $sentence->bindParam(':tipo_recordatorio', $data['tipo_recordatorio']);
            $sentence->bindParam(':monto_notificado', $data['monto_notificado']);
            $sentence->bindParam(':id_usuario', $data['id_usuario']);
            $sentence->execute();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en new historial_recordatorios_pago: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al registrar recordatorio', 'detalles' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data->getData();

            $id = $data['id'];
            $compromiso = isset($data['compromiso']) ? $data['compromiso'] : null;
            $fecha_compromiso = isset($data['fecha_compromiso']) ? $data['fecha_compromiso'] : null;

            $sentence = $db->prepare("
                UPDATE historial_recordatorios_pago SET
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
            error_log('Error en replace historial_recordatorios_pago: ' . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error al actualizar compromiso', 'detalles' => $e->getMessage()], 500);
        }
    }
}