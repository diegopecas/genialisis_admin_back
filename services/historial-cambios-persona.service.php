<?php
class HistorialCambiosPersona
{
    public static function getByPersona($id_persona)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
                h.*,
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) AS nombre_usuario
            FROM historial_cambios_persona h
            LEFT JOIN usuarios u ON h.id_usuario = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            WHERE h.id_persona = :id_persona
            AND h.id_tenant = :id_tenant
            ORDER BY h.fecha_cambio DESC");

            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en HistorialCambiosPersona::getByPersona: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el historial de cambios'), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $id_usuario = Flight::request()->data['id_usuario'];
            $cambios = Flight::request()->data['cambios'];
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

            if (!$id_persona || !$id_usuario) {
                Flight::json(array('error' => 'Faltan datos requeridos (id_persona, id_usuario)'), 400);
                return;
            }

            if (!$cambios || !is_array($cambios) || count($cambios) === 0) {
                Flight::json(array('error' => 'No hay cambios para registrar'), 400);
                return;
            }

            $insertados = 0;
            $idHist = null;

            foreach ($cambios as $cambio) {
                $campo = isset($cambio['campo']) ? $cambio['campo'] : null;
                $valor_anterior = isset($cambio['valor_anterior']) ? $cambio['valor_anterior'] : null;
                $valor_nuevo = isset($cambio['valor_nuevo']) ? $cambio['valor_nuevo'] : null;

                $idHist = Uuid::generar();
                $sentence = $db->prepare("INSERT INTO historial_cambios_persona 
                    (id, id_tenant, id_persona, id_usuario, campo_modificado, valor_anterior, valor_nuevo, ip_address)
                    VALUES (:id, :id_tenant, :id_persona, :id_usuario, :campo_modificado, :valor_anterior, :valor_nuevo, :ip_address)");

                $sentence->bindValue(':id', $idHist);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_persona', $id_persona);
                $sentence->bindParam(':id_usuario', $id_usuario);
                $sentence->bindParam(':campo_modificado', $campo);
                $sentence->bindParam(':valor_anterior', $valor_anterior);
                $sentence->bindParam(':valor_nuevo', $valor_nuevo);
                $sentence->bindParam(':ip_address', $ip_address);
                $sentence->execute();

                $insertados++;
            }

            $id = $idHist;

            error_log("HistorialCambiosPersona::new - Registrados $insertados cambios para persona $id_persona");

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Historial registrado correctamente',
                'registros' => $insertados
            ));
        } catch (Exception $e) {
            error_log("Error en HistorialCambiosPersona::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al registrar el historial de cambios'), 500);
        }
    }
}