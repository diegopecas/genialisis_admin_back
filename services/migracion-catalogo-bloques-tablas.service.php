<?php
/**
 * Tabla migracion_catalogo_bloques_tablas.
 *
 * Que tablas del destino toca cada bloque y en que orden se escriben.
 * Ese orden es el que usa el asistente para proponer, y su inverso es el
 * que usa el deshacer.
 */
class MigracionCatalogoBloquesTablas
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $id_catalogo_bloque = isset(Flight::request()->query['id_catalogo_bloque'])
                ? Flight::request()->query['id_catalogo_bloque'] : null;

            if ($id_catalogo_bloque) {
                $sentence = $db->prepare("SELECT t.id, t.id_catalogo_bloque, t.tabla, t.orden, t.activo,
                                                 b.codigo AS codigo_bloque, b.nombre AS nombre_bloque
                                          FROM migracion_catalogo_bloques_tablas t
                                          INNER JOIN migracion_catalogo_bloques b ON t.id_catalogo_bloque = b.id
                                          WHERE t.id_tenant = :id_tenant AND t.id_catalogo_bloque = :id_catalogo_bloque
                                          ORDER BY t.orden");
                $sentence->bindParam(':id_catalogo_bloque', $id_catalogo_bloque);
            } else {
                $sentence = $db->prepare("SELECT t.id, t.id_catalogo_bloque, t.tabla, t.orden, t.activo,
                                                 b.codigo AS codigo_bloque, b.nombre AS nombre_bloque
                                          FROM migracion_catalogo_bloques_tablas t
                                          INNER JOIN migracion_catalogo_bloques b ON t.id_catalogo_bloque = b.id
                                          WHERE t.id_tenant = :id_tenant
                                          ORDER BY b.orden, t.orden");
            }
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloquesTablas::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_catalogo_bloque, tabla, orden, activo
                                      FROM migracion_catalogo_bloques_tablas
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloquesTablas::getById - " . $e->getMessage());
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

            $id_catalogo_bloque = isset($data['id_catalogo_bloque']) ? $data['id_catalogo_bloque'] : null;
            $tabla = isset($data['tabla']) ? trim($data['tabla']) : '';
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

            if (!$id_catalogo_bloque || $tabla === '') {
                Flight::json(array('error' => 'El bloque y el nombre de la tabla son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO migracion_catalogo_bloques_tablas
                (id, id_tenant, id_catalogo_bloque, tabla, orden, activo)
                VALUES (:id, :id_tenant, :id_catalogo_bloque, :tabla, :orden, :activo)");
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_catalogo_bloque', $id_catalogo_bloque);
            $sentence->bindParam(':tabla', $tabla);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->execute();
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloquesTablas::new - " . $e->getMessage());
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
            $id_catalogo_bloque = isset($data['id_catalogo_bloque']) ? $data['id_catalogo_bloque'] : null;
            $tabla = isset($data['tabla']) ? trim($data['tabla']) : '';
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $activo = isset($data['activo']) ? (int)$data['activo'] : 1;

            $sentence = $db->prepare("UPDATE migracion_catalogo_bloques_tablas SET
                                        id_catalogo_bloque = :id_catalogo_bloque,
                                        tabla = :tabla,
                                        orden = :orden,
                                        activo = :activo
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_catalogo_bloque', $id_catalogo_bloque);
            $sentence->bindParam(':tabla', $tabla);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindValue(':activo', $activo, PDO::PARAM_INT);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloquesTablas::replace - " . $e->getMessage());
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

            $sentence = $db->prepare("DELETE FROM migracion_catalogo_bloques_tablas
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionCatalogoBloquesTablas::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Tablas activas de un bloque, por su codigo, en orden de escritura.
     *
     * @param string|null $codigoBloque null trae las de todos los bloques
     * @return array lista de nombres de tabla
     */
    public static function obtenerTablas($codigoBloque = null)
    {
        $db = Flight::db();

        if ($codigoBloque) {
            $sentence = $db->prepare("SELECT t.tabla
                                      FROM migracion_catalogo_bloques_tablas t
                                      INNER JOIN migracion_catalogo_bloques b ON t.id_catalogo_bloque = b.id
                                      WHERE t.id_tenant = :id_tenant AND t.activo = 1 AND b.codigo = :codigo
                                      ORDER BY t.orden");
            $sentence->bindParam(':codigo', $codigoBloque);
        } else {
            $sentence = $db->prepare("SELECT DISTINCT t.tabla
                                      FROM migracion_catalogo_bloques_tablas t
                                      INNER JOIN migracion_catalogo_bloques b ON t.id_catalogo_bloque = b.id
                                      WHERE t.id_tenant = :id_tenant AND t.activo = 1
                                      ORDER BY b.orden, t.orden");
        }
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $tablas = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tablas[] = $fila['tabla'];
        }
        return $tablas;
    }
}
