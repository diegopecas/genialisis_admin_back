<?php 
class TiposAcudiente
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono FROM tipos_acudiente");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, icono FROM tipos_acudiente WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $sentence = $db->prepare("INSERT INTO tipos_acudiente(nombre, icono) VALUES (:nombre, :icono)");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $icono = isset(Flight::request()->data['icono']) ? Flight::request()->data['icono'] : null;
        $sentence = $db->prepare("UPDATE tipos_acudiente SET nombre = :nombre, icono = :icono WHERE id = :id");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':icono', $icono);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM tipos_acudiente WHERE id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        self::getById($id);
    }
}