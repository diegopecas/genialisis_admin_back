<?php
class MediosPagoFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                WHERE m.id_tenant = :id_tenant
                ORDER BY m.orden, m.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                WHERE m.id = :id
                AND m.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByTipo($id_tipo) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT m.*, t.nombre as tipo_nombre 
                FROM medios_pago_financieros m
                INNER JOIN tipos_medios_pago_financieros t ON m.id_tipo_medio_pago = t.id
                WHERE m.id_tipo_medio_pago = :id_tipo
                AND m.id_tenant = :id_tenant
                ORDER BY m.orden, m.nombre
            ");
            $sentence->bindParam(':id_tipo', $id_tipo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros getByTipo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO medios_pago_financieros 
                (id, id_tenant, nombre, id_tipo_medio_pago, numero_cuenta, icono, color, descripcion, orden) 
                VALUES (:id, :id_tenant, :nombre, :id_tipo_medio_pago, :numero_cuenta, :icono, :color, :descripcion, :orden)
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_medio_pago', $data['id_tipo_medio_pago']);
            $sentence->bindParam(':numero_cuenta', $data['numero_cuenta']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);

            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE medios_pago_financieros 
                SET nombre = :nombre, id_tipo_medio_pago = :id_tipo_medio_pago, numero_cuenta = :numero_cuenta, 
                    icono = :icono, color = :color, descripcion = :descripcion, orden = :orden
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_medio_pago', $data['id_tipo_medio_pago']);
            $sentence->bindParam(':numero_cuenta', $data['numero_cuenta']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM medios_pago_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en medios_pago_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}