<?php
/**
 * Tabla migracion_esquema_cache.
 *
 * Lee el esquema y los catalogos de la base destino EN VIVO, desde
 * INFORMATION_SCHEMA. Nadie genera dumps: si el modelo cambia, el
 * asistente se entera solo.
 *
 * El hash del esquema tambien es la verificacion de version: si el
 * destino no es el mismo con el que se abrio la sesion, no se ejecuta.
 */
class MigracionEsquemaCache
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT c.id, c.id_conexion, c.hash_esquema, c.fecha,
                                             x.nombre AS nombre_conexion, x.base_datos, x.ambiente
                                      FROM migracion_esquema_cache c
                                      INNER JOIN migracion_conexiones x ON c.id_conexion = x.id
                                      WHERE c.id_tenant = :id_tenant
                                      ORDER BY c.fecha DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_conexion, hash_esquema, esquema_json, catalogos_json, fecha
                                      FROM migracion_esquema_cache
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Borra el cache de una conexion para forzar una relectura.
     */
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM migracion_esquema_cache WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Esquema del destino para un bloque, o completo si no se indica.
     */
    public static function getEsquema()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            $codigo_bloque = isset(Flight::request()->query['codigo_bloque']) ? Flight::request()->query['codigo_bloque'] : null;

            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($id_sesion);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }

            Flight::json(self::leerEsquema($sesion['id_conexion'], $codigo_bloque));
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::getEsquema - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Contenido de los catalogos del destino. Contra esto el asistente
     * normaliza los grados sucios y los parentescos.
     */
    public static function getCatalogos()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($id_sesion);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }

            Flight::json(self::leerCatalogos($sesion));
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::getCatalogos - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Compara el esquema actual del destino contra el de la sesion.
     */
    public static function getVerificacion()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($id_sesion);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }

            $resultado = self::verificarVersion($sesion);

            if (!$resultado['coincide']) {
                $resultado['mensaje'] = 'El esquema de la base destino cambió desde que se abrió la sesión. '
                    . 'Revisa el modelo antes de ejecutar: los scripts pueden referirse a campos que ya no existen.';
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::getVerificacion - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Lee columnas y llaves foraneas de las tablas del bloque.
     * Las tablas salen del catalogo, no del codigo.
     *
     * @return array
     */
    public static function leerEsquema($id_conexion, $codigo_bloque = null)
    {
        $conexion = MigracionConexiones::obtener($id_conexion);
        if (!$conexion) {
            throw new Exception('La conexión destino no existe');
        }

        $tablas = MigracionCatalogoBloquesTablas::obtenerTablas($codigo_bloque);
        if (empty($tablas)) {
            throw new Exception('El catálogo de bloques no tiene tablas configuradas');
        }

        $destino = MigracionConexiones::pdoDestino($id_conexion);
        $bd = $conexion['base_datos'];
        $marcadores = implode(',', array_fill(0, count($tablas), '?'));

        $sentence = $destino->prepare("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                                              COLUMN_KEY, EXTRA, COLUMN_COMMENT
                                       FROM information_schema.COLUMNS
                                       WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$marcadores})
                                       ORDER BY TABLE_NAME, ORDINAL_POSITION");
        $sentence->execute(array_merge(array($bd), $tablas));

        $esquema = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tabla = $fila['TABLE_NAME'];
            if (!isset($esquema[$tabla])) {
                $esquema[$tabla] = array('tabla' => $tabla, 'tiene_tenant' => false, 'columnas' => array(), 'fk' => array());
            }
            if ($fila['COLUMN_NAME'] === 'id_tenant') {
                $esquema[$tabla]['tiene_tenant'] = true;
            }
            $esquema[$tabla]['columnas'][] = array(
                'nombre' => $fila['COLUMN_NAME'],
                'tipo' => $fila['COLUMN_TYPE'],
                'nulo' => $fila['IS_NULLABLE'] === 'YES',
                'defecto' => $fila['COLUMN_DEFAULT'],
                'llave' => $fila['COLUMN_KEY'],
                'extra' => $fila['EXTRA'],
                'comentario' => $fila['COLUMN_COMMENT']
            );
        }

        // El orden de escritura depende de las llaves foraneas.
        $sentence = $destino->prepare("SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                                       FROM information_schema.KEY_COLUMN_USAGE
                                       WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                                         AND TABLE_NAME IN ({$marcadores})");
        $sentence->execute(array_merge(array($bd), $tablas));

        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fk) {
            $tabla = $fk['TABLE_NAME'];
            if (!isset($esquema[$tabla])) {
                continue;
            }
            $esquema[$tabla]['fk'][] = array(
                'columna' => $fk['COLUMN_NAME'],
                'tabla_referida' => $fk['REFERENCED_TABLE_NAME'],
                'columna_referida' => $fk['REFERENCED_COLUMN_NAME']
            );
        }

        $hash = hash('sha256', json_encode($esquema));

        $resultado = array(
            'base_datos' => $bd,
            'ambiente' => $conexion['ambiente'],
            'hash_esquema' => $hash,
            'total_tablas' => count($esquema),
            'esquema' => array_values($esquema)
        );

        self::guardarCache($id_conexion, $hash, $resultado);

        return $resultado;
    }

    /**
     * Los catalogos globales se leen completos; los del tenant se filtran
     * por su id_tenant.
     *
     * @return array
     */
    public static function leerCatalogos($sesion)
    {
        $conexion = MigracionConexiones::obtener($sesion['id_conexion']);
        if (!$conexion) {
            throw new Exception('La conexión destino no existe');
        }

        $destino = MigracionConexiones::pdoDestino($sesion['id_conexion']);
        $bd = $conexion['base_datos'];

        // Las tablas del bloque de catalogos salen del catalogo, mas
        // cualquier tabla del destino cuyo nombre sea de tipos o estados.
        $configuradas = MigracionCatalogoBloquesTablas::obtenerTablas('catalogos');

        $sentence = $destino->prepare("SELECT t.TABLE_NAME,
                                              MAX(CASE WHEN c.COLUMN_NAME = 'id_tenant' THEN 1 ELSE 0 END) AS tiene_tenant
                                       FROM information_schema.TABLES t
                                       INNER JOIN information_schema.COLUMNS c
                                            ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                                       WHERE t.TABLE_SCHEMA = :bd
                                         AND (t.TABLE_NAME LIKE 'tipos\\_%' OR t.TABLE_NAME LIKE 'estados\\_%')
                                       GROUP BY t.TABLE_NAME
                                       ORDER BY t.TABLE_NAME");
        $sentence->bindValue(':bd', $bd);
        $sentence->execute();

        $tablas = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $tablas[$fila['TABLE_NAME']] = (int)$fila['tiene_tenant'] === 1;
        }

        foreach ($configuradas as $tabla) {
            if (!isset($tablas[$tabla])) {
                $tablas[$tabla] = null;
            }
        }
        ksort($tablas);

        $catalogos = array();
        foreach ($tablas as $tabla => $tieneTenant) {
            // Si no se sabe todavia si lleva id_tenant, se pregunta.
            if ($tieneTenant === null) {
                $tieneTenant = self::tieneColumnaTenant($destino, $bd, $tabla);
            }

            try {
                if ($tieneTenant && $sesion['id_tenant_destino']) {
                    $q = $destino->prepare("SELECT * FROM `{$tabla}` WHERE id_tenant = :id_tenant LIMIT 300");
                    $q->bindValue(':id_tenant', (int)$sesion['id_tenant_destino'], PDO::PARAM_INT);
                } else {
                    $q = $destino->prepare("SELECT * FROM `{$tabla}` LIMIT 300");
                }
                $q->execute();
                $filas = $q->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // Que un catalogo no se pueda leer no debe tumbar la carga
                // del contexto completo.
                error_log("MigracionEsquemaCache - no se pudo leer el catálogo {$tabla}: " . $e->getMessage());
                continue;
            }

            $catalogos[] = array(
                'tabla' => $tabla,
                'global' => !$tieneTenant,
                'total' => count($filas),
                'filas' => $filas
            );
        }

        return array(
            'base_datos' => $bd,
            'id_tenant_destino' => $sesion['id_tenant_destino'],
            'total_catalogos' => count($catalogos),
            'catalogos' => $catalogos
        );
    }

    /**
     * @return array ['coincide' => bool, ...]
     */
    public static function verificarVersion($sesion)
    {
        $actual = self::leerEsquema($sesion['id_conexion']);

        if (empty($sesion['hash_esquema'])) {
            // Primera vez: se fija el hash de referencia.
            MigracionSesiones::guardarHashEsquema($sesion['id'], $actual['hash_esquema']);
            return array('coincide' => true, 'hash' => $actual['hash_esquema']);
        }

        return array(
            'coincide' => $sesion['hash_esquema'] === $actual['hash_esquema'],
            'hash_sesion' => $sesion['hash_esquema'],
            'hash_destino' => $actual['hash_esquema']
        );
    }

    private static function tieneColumnaTenant($destino, $bd, $tabla)
    {
        $sentence = $destino->prepare("SELECT COUNT(*) AS total FROM information_schema.COLUMNS
                                       WHERE TABLE_SCHEMA = :bd AND TABLE_NAME = :tabla AND COLUMN_NAME = 'id_tenant'");
        $sentence->bindValue(':bd', $bd);
        $sentence->bindValue(':tabla', $tabla);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);
        return $fila && (int)$fila['total'] > 0;
    }

    private static function guardarCache($id_conexion, $hash, $datos)
    {
        try {
            $db = Flight::db();
            $json = json_encode($datos, JSON_UNESCAPED_UNICODE);

            $sentence = $db->prepare("INSERT INTO migracion_esquema_cache
                (id, id_tenant, id_conexion, hash_esquema, esquema_json, fecha)
                VALUES (:id, :id_tenant, :id_conexion, :hash, :json, NOW())
                ON DUPLICATE KEY UPDATE hash_esquema = :hash2, esquema_json = :json2, fecha = NOW()");
            $sentence->bindValue(':id', Uuid::generar());
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_conexion', $id_conexion);
            $sentence->bindValue(':hash', $hash);
            $sentence->bindValue(':hash2', $hash);
            $sentence->bindValue(':json', $json);
            $sentence->bindValue(':json2', $json);
            $sentence->execute();
        } catch (Exception $e) {
            error_log("MigracionEsquemaCache::guardarCache - " . $e->getMessage());
        }
    }
}
