<?php
class TarifasPlanes
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_plan, tg.id_producto_implementacion, tg.id_producto_suscripcion, tg.anio,
                   g.nombre AS nombre_plan,
                   pm.nombre AS nombre_implementacion, tg.valor_implementacion,
                   pp.nombre AS nombre_suscripcion, tg.valor_suscripcion
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
            WHERE tg.id_tenant = :id_tenant
            ORDER BY g.orden, tg.anio DESC
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
            SELECT tg.id, tg.id_plan, tg.id_producto_implementacion, tg.id_producto_suscripcion, tg.anio,
                   g.nombre AS nombre_plan,
                   pm.nombre AS nombre_implementacion, tg.valor_implementacion,
                   pp.nombre AS nombre_suscripcion, tg.valor_suscripcion
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
            WHERE tg.id = :id AND tg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPlan($idPlan)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_plan, tg.id_producto_implementacion, tg.id_producto_suscripcion, tg.anio,
                   g.nombre AS nombre_plan,
                   pm.nombre AS nombre_implementacion, tg.valor_implementacion,
                   pp.nombre AS nombre_suscripcion, tg.valor_suscripcion
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
            WHERE tg.id_plan = :id_plan AND tg.id_tenant = :id_tenant
            ORDER BY tg.anio DESC
        ");
        $sentence->bindParam(':id_plan', $idPlan);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPlanAnio($idPlan, $anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_plan, tg.id_producto_implementacion, tg.id_producto_suscripcion, tg.anio,
                   g.nombre AS nombre_plan,
                   pm.nombre AS nombre_implementacion, tg.valor_implementacion,
                   pp.nombre AS nombre_suscripcion, tg.valor_suscripcion
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
            WHERE tg.id_plan = :id_plan AND tg.anio = :anio AND tg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_plan', $idPlan);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_plan, tg.id_producto_implementacion, tg.id_producto_suscripcion, tg.anio,
                   g.nombre AS nombre_plan, g.orden,
                   pm.nombre AS nombre_implementacion, tg.valor_implementacion,
                   pp.nombre AS nombre_suscripcion, tg.valor_suscripcion
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
            WHERE tg.anio = :anio AND tg.id_tenant = :id_tenant
            ORDER BY g.orden
        ");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            
            $id_plan = Flight::request()->data['id_plan'];
            $id_producto_implementacion = Flight::request()->data['id_producto_implementacion'];
            $id_producto_suscripcion = Flight::request()->data['id_producto_suscripcion'];
            $valor_implementacion = isset(Flight::request()->data['valor_implementacion']) ? Flight::request()->data['valor_implementacion'] : 0;
            $valor_suscripcion = isset(Flight::request()->data['valor_suscripcion']) ? Flight::request()->data['valor_suscripcion'] : 0;
            $anio = Flight::request()->data['anio'];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tarifas_planes 
                                      (id, id_tenant, id_plan, id_producto_implementacion, id_producto_suscripcion, valor_implementacion, valor_suscripcion, anio) 
                                      VALUES (:id, :id_tenant, :id_plan, :id_producto_implementacion, :id_producto_suscripcion, :valor_implementacion, :valor_suscripcion, :anio)");
            
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':id_producto_implementacion', $id_producto_implementacion);
            $sentence->bindParam(':id_producto_suscripcion', $id_producto_suscripcion);
            $sentence->bindParam(':valor_implementacion', $valor_implementacion);
            $sentence->bindParam(':valor_suscripcion', $valor_suscripcion);
            $sentence->bindParam(':anio', $anio);
            
            $sentence->execute();
            $id = $idNew;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TarifasPlanes::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            
            $id = Flight::request()->data['id'];
            $id_plan = Flight::request()->data['id_plan'];
            $id_producto_implementacion = Flight::request()->data['id_producto_implementacion'];
            $id_producto_suscripcion = Flight::request()->data['id_producto_suscripcion'];
            $valor_implementacion = isset(Flight::request()->data['valor_implementacion']) ? Flight::request()->data['valor_implementacion'] : 0;
            $valor_suscripcion = isset(Flight::request()->data['valor_suscripcion']) ? Flight::request()->data['valor_suscripcion'] : 0;
            $anio = Flight::request()->data['anio'];

            $sentence = $db->prepare("UPDATE tarifas_planes SET 
                                      id_plan = :id_plan,
                                      id_producto_implementacion = :id_producto_implementacion,
                                      id_producto_suscripcion = :id_producto_suscripcion,
                                      valor_implementacion = :valor_implementacion,
                                      valor_suscripcion = :valor_suscripcion,
                                      anio = :anio
                                      WHERE id = :id AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':id_producto_implementacion', $id_producto_implementacion);
            $sentence->bindParam(':id_producto_suscripcion', $id_producto_suscripcion);
            $sentence->bindParam(':valor_implementacion', $valor_implementacion);
            $sentence->bindParam(':valor_suscripcion', $valor_suscripcion);
            $sentence->bindParam(':anio', $anio);
            
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en TarifasPlanes::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM tarifas_planes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}