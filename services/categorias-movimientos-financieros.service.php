<?php
class CategoriasMovimientosFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT c.*, t.nombre as tipo_movimiento_nombre, t.icono as tipo_movimiento_icono,
                cl.nombre as clasificacion_nombre
                FROM categorias_movimientos_financieros c
                INNER JOIN tipos_movimientos_financieros t ON c.id_tipo_movimiento = t.id
                LEFT JOIN clasificacion_productos_servicios cl ON c.id_clasificacion_productos_servicios = cl.id
                WHERE c.id_tenant = :id_tenant
                ORDER BY c.orden, c.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT c.*, t.nombre as tipo_movimiento_nombre, t.icono as tipo_movimiento_icono,
                cl.nombre as clasificacion_nombre
                FROM categorias_movimientos_financieros c
                INNER JOIN tipos_movimientos_financieros t ON c.id_tipo_movimiento = t.id
                LEFT JOIN clasificacion_productos_servicios cl ON c.id_clasificacion_productos_servicios = cl.id
                WHERE c.id = :id
                AND c.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByTipoMovimiento($id_tipo) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT c.*, t.nombre as tipo_movimiento_nombre, t.icono as tipo_movimiento_icono,
                cl.nombre as clasificacion_nombre
                FROM categorias_movimientos_financieros c
                INNER JOIN tipos_movimientos_financieros t ON c.id_tipo_movimiento = t.id
                LEFT JOIN clasificacion_productos_servicios cl ON c.id_clasificacion_productos_servicios = cl.id
                WHERE c.id_tipo_movimiento = :id_tipo
                AND c.id_tenant = :id_tenant
                ORDER BY c.orden, c.nombre
            ");
            $sentence->bindParam(':id_tipo', $id_tipo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros getByTipoMovimiento: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO categorias_movimientos_financieros 
                (id, id_tenant, nombre, id_tipo_movimiento, id_clasificacion_productos_servicios, icono, color, descripcion, orden) 
                VALUES (:id, :id_tenant, :nombre, :id_tipo_movimiento, :id_clasificacion_productos_servicios, :icono, :color, :descripcion, :orden)
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;
            $id = Uuid::generar();

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_movimiento', $data['id_tipo_movimiento']);
            $sentence->bindParam(':id_clasificacion_productos_servicios', $data['id_clasificacion_productos_servicios']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);

            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE categorias_movimientos_financieros 
                SET nombre = :nombre, id_tipo_movimiento = :id_tipo_movimiento, 
                    id_clasificacion_productos_servicios = :id_clasificacion_productos_servicios,
                    icono = :icono, color = :color, descripcion = :descripcion, orden = :orden
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $icono = $data['icono'] ?? '?';
            $color = $data['color'] ?? '#808080';
            $orden = $data['orden'] ?? 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':id_tipo_movimiento', $data['id_tipo_movimiento']);
            $sentence->bindParam(':id_clasificacion_productos_servicios', $data['id_clasificacion_productos_servicios']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':color', $color);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM categorias_movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en categorias_movimientos_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}