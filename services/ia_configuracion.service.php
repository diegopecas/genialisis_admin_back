<?php
class IaConfiguracion
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, clave, valor, descripcion, fecha_actualizacion
            FROM ia_configuracion
            WHERE id_tenant = :id_tenant
            ORDER BY id
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
            SELECT id, clave, valor, descripcion, fecha_actualizacion
            FROM ia_configuracion
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function replace()
    {
        $db = Flight::db();
        $data = Flight::request()->data;

        $id = $data['id'];
        $valor = isset($data['valor']) ? $data['valor'] : null;
        $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;

        $sentence = $db->prepare("
            UPDATE ia_configuracion SET 
                valor = :valor,
                descripcion = :descripcion
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':valor', $valor);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }
}