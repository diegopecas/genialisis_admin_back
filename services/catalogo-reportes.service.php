<?php
class CatalogoReportes
{
    // Todos los reportes activos del tenant.
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cr.id, cr.nombre, cr.ruta, cr.id_tipo_reporte,
                       cr.reporte_ente_control, cr.orden, cr.activo,
                       tr.codigo AS codigo_tipo_reporte,
                       tr.nombre AS nombre_tipo_reporte
                FROM catalogo_reportes cr
                LEFT JOIN tipos_reportes tr ON tr.id = cr.id_tipo_reporte
                WHERE cr.id_tenant = :id_tenant
                  AND cr.activo = 1
                ORDER BY cr.orden ASC, cr.nombre ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener el catálogo de reportes'), 500);
        }
    }

    // Solo los reportes marcados como expuestos a entes de control.
    // Es la lista que se ofrece al configurar los recursos de un ente.
    public static function getParaEntesControl()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cr.id, cr.nombre, cr.ruta, cr.id_tipo_reporte, cr.orden,
                       tr.codigo AS codigo_tipo_reporte,
                       tr.nombre AS nombre_tipo_reporte
                FROM catalogo_reportes cr
                LEFT JOIN tipos_reportes tr ON tr.id = cr.id_tipo_reporte
                WHERE cr.id_tenant = :id_tenant
                  AND cr.activo = 1
                  AND cr.reporte_ente_control = 1
                ORDER BY cr.orden ASC, cr.nombre ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::getParaEntesControl: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los reportes'), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cr.id, cr.nombre, cr.ruta, cr.id_tipo_reporte,
                       cr.reporte_ente_control, cr.orden, cr.activo
                FROM catalogo_reportes cr
                WHERE cr.id = :id AND cr.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                Flight::json(array('error' => 'No se encontró el reporte'), 404);
                return;
            }
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::getById: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener el reporte'), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $nombre = Flight::request()->data['nombre'] ?? null;
            $ruta = Flight::request()->data['ruta'] ?? null;
            $id_tipo_reporte = Flight::request()->data['id_tipo_reporte'] ?? null;
            $reporte_ente_control = Flight::request()->data['reporte_ente_control'] ?? 0;
            $orden = Flight::request()->data['orden'] ?? 0;

            if (!$nombre || !$ruta) {
                Flight::json(array('error' => 'Nombre y ruta son obligatorios'), 400);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO catalogo_reportes
                    (id, id_tenant, nombre, ruta, id_tipo_reporte, reporte_ente_control, orden, activo)
                VALUES
                    (:id, :id_tenant, :nombre, :ruta, :id_tipo_reporte, :reporte_ente_control, :orden, 1)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':ruta', $ruta);
            $sentence->bindParam(':id_tipo_reporte', $id_tipo_reporte);
            $sentence->bindParam(':reporte_ente_control', $reporte_ente_control);
            $sentence->bindParam(':orden', $orden);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $id = Flight::request()->data['id'] ?? null;
            $nombre = Flight::request()->data['nombre'] ?? null;
            $ruta = Flight::request()->data['ruta'] ?? null;
            $id_tipo_reporte = Flight::request()->data['id_tipo_reporte'] ?? null;
            $reporte_ente_control = Flight::request()->data['reporte_ente_control'] ?? 0;
            $orden = Flight::request()->data['orden'] ?? 0;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del reporte'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE catalogo_reportes
                SET nombre = :nombre,
                    ruta = :ruta,
                    id_tipo_reporte = :id_tipo_reporte,
                    reporte_ente_control = :reporte_ente_control,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':ruta', $ruta);
            $sentence->bindParam(':id_tipo_reporte', $id_tipo_reporte);
            $sentence->bindParam(':reporte_ente_control', $reporte_ente_control);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del reporte'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE catalogo_reportes SET activo = 0
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el reporte'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'mensaje' => 'Reporte eliminado'));
        } catch (Exception $e) {
            error_log("Error en CatalogoReportes::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
