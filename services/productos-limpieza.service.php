<?php
class ProductosLimpieza
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pl.*, 
            p.nombre nombre_producto,
            tpl.nombre as tipo_limpieza
            FROM productos_limpieza pl
            INNER JOIN productos p ON pl.id_producto = p.id
            LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
            WHERE pl.id_tenant = :id_tenant
            ORDER BY pl.id DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pl.*, 
        p.nombre nombre_producto,
        p.imagen imagen_producto,
        p.descripcion descripcion_producto,
        p.precio_unitario,
        um.nombre unidad_medida,
        um.abreviatura abreviatura_unidad,
        tpl.nombre as tipo_limpieza
        FROM productos_limpieza pl
        INNER JOIN productos p ON pl.id_producto = p.id
        LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
        LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
        WHERE pl.id = :id
        AND pl.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_limpieza = Flight::request()->data['id_tipo_producto_limpieza'];
        $componentes = Flight::request()->data['componentes'];
        $modo_uso = Flight::request()->data['modo_uso'];
        $id = Uuid::generar();

        $sentence = $db->prepare("INSERT INTO productos_limpieza(
        id,
        id_tenant,
        id_producto,
        id_tipo_producto_limpieza,
        componentes,
        modo_uso
    ) VALUES (
        :id,
        :id_tenant,
        :id_producto,
        :id_tipo_producto_limpieza,
        :componentes,
        :modo_uso
    )");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_limpieza', $id_tipo_producto_limpieza);
        $sentence->bindParam(':componentes', $componentes);
        $sentence->bindParam(':modo_uso', $modo_uso);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_limpieza = Flight::request()->data['id_tipo_producto_limpieza'];
        $componentes = Flight::request()->data['componentes'];
        $modo_uso = Flight::request()->data['modo_uso'];

        $sentence = $db->prepare("UPDATE productos_limpieza SET 
        id_producto = :id_producto,
        id_tipo_producto_limpieza = :id_tipo_producto_limpieza,
        componentes = :componentes,
        modo_uso = :modo_uso
        WHERE id = :id
        AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_limpieza', $id_tipo_producto_limpieza);
        $sentence->bindParam(':componentes', $componentes);
        $sentence->bindParam(':modo_uso', $modo_uso);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM productos_limpieza WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getProductosDisponiblesParaLimpieza()
    {
        error_log("getProductosDisponiblesParaLimpieza");
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.*, u.abreviatura abreviatura_unidad, tp.nombre tipo_producto_nombre
            FROM productos p
            LEFT JOIN unidades_medida u ON p.id_unidad_medida = u.id
            LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id AND tp.id_tenant = p.id_tenant
            WHERE tp.codigo = 'LIMPIEZA' 
            AND p.activo = 1
            AND p.id_tenant = :id_tenant
            AND p.id NOT IN (
                SELECT id_producto FROM productos_limpieza WHERE id_tenant = :id_tenant_sub
            )
            ORDER BY p.nombre ASC
        ");
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}