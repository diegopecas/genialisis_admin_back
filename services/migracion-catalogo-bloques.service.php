<?php
/**
 * Tabla migracion_catalogo_bloques.
 *
 * Los bloques del proceso y su orden. Estan parametrizados porque el
 * orden depende de las dependencias del modelo de Genialisis, y ese
 * modelo cambia.
 */
class MigracionCatalogoBloques
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, codigo, nombre, descripcion, orden, activo
                                      FROM migracion_catalogo_bloques
                                      WHERE id_tenant = :id_tenant
                                      ORDER BY orden");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloques::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, codigo, nombre, descripcion, orden, activo
                                      FROM migracion_catalogo_bloques
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloques::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $data = Flight::request()->data;

            $codigo = isset($data['codigo']) ? trim($data['codigo']) : '';
            $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
            $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

            if ($codigo === '' || $nombre === '') {
                Flight::json(array('error' => 'El código y el nombre del bloque son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO migracion_catalogo_bloques
                (id, id_tenant, codigo, nombre, descripcion, orden, activo)
                VALUES (:id, :id_tenant, :codigo, :nombre, :descripcion, :orden, :activo)");
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->execute();
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloques::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id = $data['id'];
            $codigo = isset($data['codigo']) ? trim($data['codigo']) : '';
            $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
            $descripcion = isset($data['descripcion']) ? $data['descripcion'] : null;
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

            $sentence = $db->prepare("UPDATE migracion_catalogo_bloques SET
                                        codigo = :codigo,
                                        nombre = :nombre,
                                        descripcion = :descripcion,
                                        orden = :orden,
                                        activo = :activo
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':descripcion', $descripcion);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloques::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM migracion_catalogo_bloques
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloques::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Bloques activos, para sembrarlos al crear una sesion.
     *
     * @return array
     */
    public static function obtenerActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, orden
                                  FROM migracion_catalogo_bloques
                                  WHERE id_tenant = :id_tenant AND activo = 1
                                  ORDER BY orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
