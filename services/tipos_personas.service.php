<?php
class TiposPersonas
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, codigo, nombre, descripcion, activo
            FROM tipos_personas
            WHERE activo = 1
            AND id_tenant = :id_tenant
            ORDER BY id
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}