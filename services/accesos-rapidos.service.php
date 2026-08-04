<?php
class AccesosRapidos
{
    public static function sincronizar()
    {
        $db = Flight::db();
        try {
            $id_usuario = Flight::request()->data['id_usuario'];
            $accesos = Flight::request()->data['accesos'];

            if (!$id_usuario || !is_array($accesos) || empty($accesos)) {
                Flight::json(array('error' => 'Datos incompletos'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO accesos_rapidos (id, id_tenant, id_usuario, ruta, label, icono, conteo, ultima_visita)
                VALUES (:id, :id_tenant, :id_usuario, :ruta, :label, :icono, :conteo, NOW())
                ON DUPLICATE KEY UPDATE
                conteo = conteo + :conteo_update,
                label = :label_update,
                icono = :icono_update,
                ultima_visita = NOW()");

            foreach ($accesos as $acceso) {
                if (!isset($acceso['ruta']) || !isset($acceso['conteo'])) continue;

                $ruta = $acceso['ruta'];
                $label = isset($acceso['label']) ? $acceso['label'] : $ruta;
                $icono = isset($acceso['icono']) ? $acceso['icono'] : '📌';
                $conteo = intval($acceso['conteo']);

                $idAcceso = Uuid::generar();
                $sentence->bindValue(':id', $idAcceso);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_usuario', $id_usuario);
                $sentence->bindParam(':ruta', $ruta);
                $sentence->bindParam(':label', $label);
                $sentence->bindParam(':icono', $icono);
                $sentence->bindParam(':conteo', $conteo);
                $sentence->bindParam(':conteo_update', $conteo);
                $sentence->bindParam(':label_update', $label);
                $sentence->bindParam(':icono_update', $icono);
                $sentence->execute();
            }

            Flight::json(array('message' => 'Accesos sincronizados correctamente'));
        } catch (Exception $e) {
            error_log("Error en sincronizar accesos: " . $e->getMessage());
            Flight::json(array('error' => 'Error al sincronizar accesos'), 500);
        }
    }

    public static function getTop()
    {
        $db = Flight::db();
        try {
            $id_usuario = Flight::request()->query['id_usuario'];
            $limite = isset(Flight::request()->query['limite']) ? intval(Flight::request()->query['limite']) : 6;

            if (!$id_usuario) {
                Flight::json(array('error' => 'id_usuario es requerido'), 400);
                return;
            }

            // Primero los fijos, luego los más usados
            $sentence = $db->prepare("SELECT id, ruta, label, icono, conteo, es_fijo, ultima_visita
                FROM accesos_rapidos
                WHERE id_usuario = :id_usuario
                AND id_tenant = :id_tenant
                ORDER BY es_fijo DESC, conteo DESC, ultima_visita DESC
                LIMIT :limite");
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->bindParam(':limite', $limite, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getTop accesos: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener accesos'), 500);
        }
    }

    public static function toggleFijo()
    {
        $db = Flight::db();
        try {
            $id = Flight::request()->data['id'];
            $es_fijo = Flight::request()->data['es_fijo'];

            if (!$id) {
                Flight::json(array('error' => 'id es requerido'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE accesos_rapidos SET es_fijo = :es_fijo WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':es_fijo', $es_fijo);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'es_fijo' => $es_fijo, 'message' => 'Acceso actualizado'));
        } catch (Exception $e) {
            error_log("Error en toggleFijo: " . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar acceso'), 500);
        }
    }

    public static function fijar()
    {
        $db = Flight::db();
        try {
            $id_usuario = Flight::request()->data['id_usuario'];
            $ruta = Flight::request()->data['ruta'];
            $label = isset(Flight::request()->data['label']) ? Flight::request()->data['label'] : $ruta;
            $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : '📌';

            if (!$id_usuario || !$ruta) {
                Flight::json(array('error' => 'Datos incompletos'), 400);
                return;
            }

            $idAcceso = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO accesos_rapidos (id, id_tenant, id_usuario, ruta, label, icono, conteo, es_fijo, ultima_visita)
                VALUES (:id, :id_tenant, :id_usuario, :ruta, :label, :icono, 0, 1, NOW())
                ON DUPLICATE KEY UPDATE
                es_fijo = 1,
                label = :label_update,
                icono = :icono_update,
                ultima_visita = NOW()");
            $sentence->bindValue(':id', $idAcceso);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->bindParam(':ruta', $ruta);
            $sentence->bindParam(':label', $label);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':label_update', $label);
            $sentence->bindParam(':icono_update', $icono);
            $sentence->execute();

            $getId = $db->prepare("SELECT id FROM accesos_rapidos WHERE id_usuario = :id_usuario AND ruta = :ruta AND id_tenant = :id_tenant");
            $getId->bindParam(':id_usuario', $id_usuario);
            $getId->bindParam(':ruta', $ruta);
            $getId->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getId->execute();
            $row = $getId->fetch();
            $id = $row ? $row['id'] : null;

            Flight::json(array('id' => $id, 'message' => 'Acceso fijado correctamente'));
        } catch (Exception $e) {
            error_log("Error en fijar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al fijar acceso'), 500);
        }
    }
}