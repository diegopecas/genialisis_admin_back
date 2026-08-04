<?php 
class ClasificacionProductosServicios
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, codigo, icono from clasificacion_productos_servicios where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, codigo, icono from clasificacion_productos_servicios where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $id = Uuid::generar();
        $sentence = $db->prepare("insert into clasificacion_productos_servicios(id, id_tenant, nombre, icono) values (:id, :id_tenant, :nombre, :icono)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $sentence = $db->prepare("update clasificacion_productos_servicios set nombre = :nombre, icono = :icono where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from clasificacion_productos_servicios where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
