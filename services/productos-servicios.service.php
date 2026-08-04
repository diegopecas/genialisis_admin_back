<?php
class ProductosServicios
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, cp.codigo AS categoria_codigo, 
               cl.nombre AS nombre_clasificacion, cl.codigo AS clasificacion_codigo, 
               pc.nombre AS nombre_periodicidad
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        WHERE ps.id_tenant = :id_tenant
        ORDER BY ps.nombre
    ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, cp.codigo AS categoria_codigo, 
               cl.nombre AS nombre_clasificacion, cl.codigo AS clasificacion_codigo, 
               pc.nombre AS nombre_periodicidad
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        WHERE ps.id = :id
        AND ps.id_tenant = :id_tenant
        ORDER BY ps.nombre
    ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByClasificacion($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT ps.*, 
               cp.nombre AS nombre_categoria, cp.codigo AS categoria_codigo, 
               cl.nombre AS nombre_clasificacion, cl.codigo AS clasificacion_codigo, 
               pc.nombre AS nombre_periodicidad
        FROM productos_servicios ps
        LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
        LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
        LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        WHERE ps.id_clasificacion_productos_servicios = :id
        AND ps.id_tenant = :id_tenant
        ORDER BY ps.nombre
    ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getCatalogoDisponibles()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT ps.id,
                   ps.nombre,
                   ps.detalles,
                   ps.id_clasificacion_productos_servicios,
                   ps.id_categoria_productos_servicios,
                   ps.id_periodicidad_cobro,
                   ps.valor_sugerido,
                   cl.nombre AS nombre_clasificacion,
                   cl.codigo AS clasificacion_codigo,
                   cl.icono AS icono_clasificacion,
                   cp.nombre AS nombre_categoria,
                   cp.codigo AS categoria_codigo,
                   pc.nombre AS nombre_periodicidad
            FROM productos_servicios ps
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN categoria_productos_servicios cp ON cp.id = ps.id_categoria_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
            WHERE ps.disponible = 1
            AND ps.id_tenant = :id_tenant
            ORDER BY cl.nombre, ps.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            $nombre = $request->data->nombre;
            $detalles = $request->data->detalles;
            $id_clasificacion = $request->data->id_clasificacion_productos_servicios;
            $id_categoria = $request->data->id_categoria_productos_servicios;
            $id_periodicidad = $request->data->id_periodicidad_cobro;
            $valor_sugerido = $request->data->valor_sugerido;
            $disponible = $request->data->disponible;
            $anio = $request->data->anio;

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO productos_servicios(id, id_tenant, nombre, detalles, id_clasificacion_productos_servicios, id_categoria_productos_servicios, id_periodicidad_cobro, valor_sugerido, disponible, anio) 
            VALUES (:id, :id_tenant, :nombre, :detalles, :id_clas, :id_cat, :id_period, :valor, :disponible, :anio)");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':detalles', $detalles);
            $sentence->bindParam(':id_clas', $id_clasificacion);
            $sentence->bindParam(':id_cat', $id_categoria);
            $sentence->bindParam(':id_period', $id_periodicidad);
            $sentence->bindParam(':valor', $valor_sugerido);
            $sentence->bindParam(':disponible', $disponible);
            $sentence->bindParam(':anio', $anio);

            $sentence->execute();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            
            $id = $request->data->id;
            $nombre = $request->data->nombre;
            $detalles = $request->data->detalles;
            $id_clasificacion = $request->data->id_clasificacion_productos_servicios;
            $id_categoria = $request->data->id_categoria_productos_servicios;
            $id_periodicidad = $request->data->id_periodicidad_cobro;
            $valor_sugerido = $request->data->valor_sugerido;
            $disponible = $request->data->disponible;
            $anio = $request->data->anio;

            $sentence = $db->prepare("UPDATE productos_servicios SET 
                nombre = :nombre,
                detalles = :detalles,
                id_clasificacion_productos_servicios = :id_clas,
                id_categoria_productos_servicios = :id_cat,
                id_periodicidad_cobro = :id_period,
                valor_sugerido = :valor,
                disponible = :disponible,
                anio = :anio
                WHERE id = :id
                AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':detalles', $detalles);
            $sentence->bindParam(':id_clas', $id_clasificacion);
            $sentence->bindParam(':id_cat', $id_categoria);
            $sentence->bindParam(':id_period', $id_periodicidad);
            $sentence->bindParam(':valor', $valor_sugerido);
            $sentence->bindParam(':disponible', $disponible);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $request = Flight::request();
            $id = $request->data->id;
            
            $sentence = $db->prepare("DELETE FROM productos_servicios WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en ProductosServicios::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}