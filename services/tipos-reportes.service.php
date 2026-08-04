<?php
class TiposReportes
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id, codigo, nombre, activo
                FROM tipos_reportes
                WHERE id_tenant = :id_tenant
                  AND activo = 1
                ORDER BY nombre ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en TiposReportes::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los tipos de reporte'), 500);
        }
    }
}
