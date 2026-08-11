<?php
/**
 * Tabla migracion_bloques.
 *
 * Los bloques de una sesion, copiados del catalogo al crearla. Guardan el
 * estado de avance de cada uno.
 */
class MigracionBloques
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;

            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT id, id_sesion, codigo, nombre, orden, estado, fecha_actualizacion
                                      FROM migracion_bloques
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                      ORDER BY orden");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionBloques::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, codigo, nombre, orden, estado, fecha_actualizacion
                                      FROM migracion_bloques
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionBloques::getById - " . $e->getMessage());
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

            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $codigo = isset($data['codigo']) ? trim($data['codigo']) : '';
            $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $estado = isset($data['estado']) ? $data['estado'] : 'pendiente';

            if (!$id_sesion || $codigo === '' || $nombre === '') {
                Flight::json(array('error' => 'La sesión, el código y el nombre del bloque son obligatorios'), 400);
                return;
            }

            $sentence = $db->prepare("INSERT INTO migracion_bloques
                (id, id_tenant, id_sesion, codigo, nombre, orden, estado)
                VALUES (:id, :id_tenant, :id_sesion, :codigo, :nombre, :orden, :estado)");
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindParam(':codigo', $codigo);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':estado', $estado);
            $sentence->execute();
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionBloques::new - " . $e->getMessage());
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
            $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
            $orden = isset($data['orden']) ? (int)$data['orden'] : 0;
            $estado = isset($data['estado']) ? $data['estado'] : 'pendiente';

            $sentence = $db->prepare("UPDATE migracion_bloques SET
                                        nombre = :nombre,
                                        orden = :orden,
                                        estado = :estado
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);
            $sentence->bindParam(':estado', $estado);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionBloques::replace - " . $e->getMessage());
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

            $sentence = $db->prepare("DELETE FROM migracion_bloques WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionBloques::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Marca un bloque como validado despues de que la persona reviso en
     * pantalla que los datos quedaron bien.
     */
    public static function validar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.ejecutar');

            $db = Flight::db();
            $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del bloque'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE migracion_bloques SET estado = 'validado'
                                      WHERE id = :id AND id_tenant = :id_tenant AND estado = 'ejecutado'");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'Solo se puede validar un bloque que ya se ejecutó'), 400);
                return;
            }

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionBloques::validar - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Copia el catalogo de bloques a una sesion recien creada.
     * Recibe la conexion porque se llama dentro de la transaccion que crea
     * la sesion.
     */
    public static function sembrar($db, $id_sesion)
    {
        $bloques = MigracionCatalogoBloques::obtenerActivos();

        $sentence = $db->prepare("INSERT INTO migracion_bloques
            (id, id_tenant, id_sesion, codigo, nombre, orden, estado)
            VALUES (:id, :id_tenant, :id_sesion, :codigo, :nombre, :orden, 'pendiente')");

        foreach ($bloques as $bloque) {
            $sentence->bindValue(':id', Uuid::generar());
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_sesion', $id_sesion);
            $sentence->bindValue(':codigo', $bloque['codigo']);
            $sentence->bindValue(':nombre', $bloque['nombre']);
            $sentence->bindValue(':orden', (int)$bloque['orden'], PDO::PARAM_INT);
            $sentence->execute();
        }
    }

    /**
     * Id del bloque de una sesion por su codigo.
     *
     * @return string|null
     */
    public static function obtenerIdPorCodigo($id_sesion, $codigo)
    {
        if (!$codigo) {
            return null;
        }

        $db = Flight::db();
        $sentence = $db->prepare("SELECT id FROM migracion_bloques
                                  WHERE id_sesion = :id_sesion AND codigo = :codigo AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':codigo', $codigo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        return $fila ? $fila['id'] : null;
    }

    /**
     * Cambia el estado de un bloque. Lo usan los servicios de scripts y
     * de ejecuciones cuando el bloque avanza o se deshace.
     */
    public static function cambiarEstado($id_bloque, $estado)
    {
        if (!$id_bloque) {
            return;
        }

        $db = Flight::db();
        $sentence = $db->prepare("UPDATE migracion_bloques SET estado = :estado
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':estado', $estado);
        $sentence->bindValue(':id', $id_bloque);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    public static function cambiarEstadoPorCodigo($id_sesion, $codigo, $estado)
    {
        $db = Flight::db();
        $sentence = $db->prepare("UPDATE migracion_bloques SET estado = :estado
                                  WHERE id_sesion = :id_sesion AND codigo = :codigo AND id_tenant = :id_tenant");
        $sentence->bindValue(':estado', $estado);
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':codigo', $codigo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    /**
     * Resumen de estados para el contexto del asistente.
     *
     * @return array
     */
    public static function obtenerDeSesion($id_sesion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, codigo, nombre, orden, estado
                                  FROM migracion_bloques
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                  ORDER BY orden");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
