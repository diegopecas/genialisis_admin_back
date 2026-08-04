<?php
class TiposTareasColaboradores
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, activo FROM tipos_tareas_colaboradores WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY nombre");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TiposTareasColaboradores::getAll: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tipos de tareas'), 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, activo FROM tipos_tareas_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log('Error en TiposTareasColaboradores::getById: ' . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener tipo de tarea'), 500);
        }
    }
}