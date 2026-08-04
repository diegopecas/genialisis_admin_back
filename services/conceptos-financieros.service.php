<?php
class ConceptosFinancieros {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cf.*, 
                cm.nombre as categoria_nombre, cm.icono as categoria_icono, cm.color as categoria_color,
                cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono
                FROM conceptos_financieros cf
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE cf.id_tenant = :id_tenant
                ORDER BY cm.orden, cf.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cf.*, 
                cm.nombre as categoria_nombre, cm.icono as categoria_icono, cm.color as categoria_color,
                cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono
                FROM conceptos_financieros cf
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE cf.id = :id
                AND cf.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByCategoria($id_categoria) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cf.*, 
                cm.nombre as categoria_nombre, cm.icono as categoria_icono, cm.color as categoria_color,
                cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono
                FROM conceptos_financieros cf
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE cf.id_categoria_movimiento_financiero = :id_categoria
                AND cf.id_tenant = :id_tenant
                ORDER BY cf.nombre
            ");
            $sentence->bindParam(':id_categoria', $id_categoria);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros getByCategoria: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByTipoMovimiento($id_tipo) {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cf.*, 
                cm.nombre as categoria_nombre, cm.icono as categoria_icono, cm.color as categoria_color,
                cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono
                FROM conceptos_financieros cf
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE cm.id_tipo_movimiento = :id_tipo
                AND cf.id_tenant = :id_tenant
                ORDER BY cm.orden, cf.nombre
            ");
            $sentence->bindParam(':id_tipo', $id_tipo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros getByTipoMovimiento: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                INSERT INTO conceptos_financieros 
                (id, id_tenant, id_categoria_movimiento_financiero, nombre, icono, requiere_detalle, descripcion) 
                VALUES (:id, :id_tenant, :id_categoria_movimiento_financiero, :nombre, :icono, :requiere_detalle, :descripcion)
            ");

            $icono = $data['icono'] ?? '?';
            $requiere_detalle = $data['requiere_detalle'] ?? 0;
            $id = Uuid::generar();

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_categoria_movimiento_financiero', $data['id_categoria_movimiento_financiero']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':requiere_detalle', $requiere_detalle);
            $sentence->bindParam(':descripcion', $data['descripcion']);

            $sentence->execute();
            
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace() {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE conceptos_financieros 
                SET id_categoria_movimiento_financiero = :id_categoria_movimiento_financiero, 
                    nombre = :nombre, icono = :icono, requiere_detalle = :requiere_detalle, descripcion = :descripcion
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $icono = $data['icono'] ?? '?';
            $requiere_detalle = $data['requiere_detalle'] ?? 0;

            $sentence->bindParam(':id', $data['id']);
            $sentence->bindParam(':id_categoria_movimiento_financiero', $data['id_categoria_movimiento_financiero']);
            $sentence->bindParam(':nombre', $data['nombre']);
            $sentence->bindParam(':icono', $icono);
            $sentence->bindParam(':requiere_detalle', $requiere_detalle);
            $sentence->bindParam(':descripcion', $data['descripcion']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete() {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM conceptos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en conceptos_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}