<?php 
class Grupos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, calificable, orden from grupos where id_tenant = :id_tenant order by orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, nombre, icono, color, calificable, orden from grupos where id = :id and id_tenant = :id_tenant");
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
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $calificable = Flight::request()->data['calificable'];
        $orden = Flight::request()->data['orden'];
        
        $sentence = $db->prepare("insert into grupos(id, id_tenant, nombre, icono, color, calificable, orden) values (:id, :id_tenant, :nombre, :icono, :color, :calificable, :orden)");
        $idNew = Uuid::generar();
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':calificable', $calificable);
        $sentence->bindParam(':orden', $orden);
        $sentence->execute();
        $id = $idNew;
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = Flight::request()->data['icono'];
        $color = Flight::request()->data['color'];
        $calificable = Flight::request()->data['calificable'];
        $orden = Flight::request()->data['orden'];
        
        $sentence = $db->prepare("update grupos set nombre = :nombre, icono = :icono, color = :color, calificable = :calificable, orden = :orden where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':color', $color);
        $sentence->bindParam(':calificable', $calificable);
        $sentence->bindParam(':orden', $orden);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from grupos where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }
    
}