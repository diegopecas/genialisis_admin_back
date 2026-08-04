<?php
class MovimientosProductosDetalle
{
    public static function getByMovimiento($id_movimiento)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                mpd.*,
                p.nombre AS producto_nombre,
                p.descripcion AS producto_descripcion,
                p.stock_actual,
                um.nombre AS unidad_medida,
                um.abreviatura
            FROM movimientos_productos_detalle mpd
            INNER JOIN productos p ON mpd.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE mpd.id_movimiento = :id_movimiento
            AND mpd.id_tenant = :id_tenant
            ORDER BY mpd.id
        ");
        $sentence->bindParam(':id_movimiento', $id_movimiento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                mpd.*,
                p.nombre AS producto_nombre,
                um.abreviatura
            FROM movimientos_productos_detalle mpd
            INNER JOIN productos p ON mpd.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE mpd.id = :id
            AND mpd.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        // Verificar que el movimiento asociado esté en estado REGISTRADO
        $sentence = $db->prepare("
            SELECT mp.id_estado, emp.nombre as estado
            FROM movimientos_productos_detalle mpd
            INNER JOIN movimientos_productos mp ON mpd.id_movimiento = mp.id
            INNER JOIN estados_movimientos_productos emp ON mp.id_estado = emp.id
            WHERE mpd.id = :id
            AND mpd.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();
        
        if ($result['estado'] !== 'REGISTRADO') {
            Flight::json(array('error' => 'Solo se pueden eliminar detalles de movimientos en estado REGISTRADO'), 400);
            return;
        }
        
        $sentence = $db->prepare("DELETE FROM movimientos_productos_detalle WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}