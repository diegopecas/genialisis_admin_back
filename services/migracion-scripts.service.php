<?php
/**
 * Tabla migracion_scripts.
 *
 * Los scripts que propone el asistente. Aqui vive el validador, la
 * previsualizacion y la ejecucion, porque es la tabla que los guarda.
 *
 * El validador es lo que hace que dejar a una IA generando SQL ejecutable
 * no sea imprudente: sin filtro de id_tenant no pasa nada, y DDL no pasa
 * nunca. Ver self::validar().
 */
class MigracionScripts
{
    /**
     * Un script de cargue siembra datos; no cambia el modelo ni toca
     * permisos del motor.
     */
    const PROHIBIDAS = array(
        'DROP', 'TRUNCATE', 'ALTER', 'CREATE', 'RENAME', 'GRANT', 'REVOKE',
        'LOAD DATA', 'INTO OUTFILE', 'INTO DUMPFILE', 'SET FOREIGN_KEY_CHECKS',
        'SET SQL_MODE', 'SHUTDOWN', 'FLUSH', 'LOCK TABLES', 'HANDLER', 'CALL',
        'PREPARE', 'EXECUTE', 'DEALLOCATE'
    );

    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("SELECT s.id, s.id_sesion, s.id_bloque, s.titulo, s.resumen_json, s.validado,
                                             s.motivo_rechazo, s.estado, s.fecha_creacion,
                                             b.codigo AS codigo_bloque, b.nombre AS nombre_bloque,
                                             CHAR_LENGTH(s.sql_generado) AS tamano_sql
                                      FROM migracion_scripts s
                                      LEFT JOIN migracion_bloques b ON s.id_bloque = b.id
                                      WHERE s.id_sesion = :id_sesion AND s.id_tenant = :id_tenant
                                      ORDER BY s.fecha_creacion DESC");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionScripts::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT s.*, b.codigo AS codigo_bloque, b.nombre AS nombre_bloque
                                      FROM migracion_scripts s
                                      LEFT JOIN migracion_bloques b ON s.id_bloque = b.id
                                      WHERE s.id = :id AND s.id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionScripts::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Guarda un script escrito o corregido a mano.
     */
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $data = Flight::request()->data;
            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $sql_generado = isset($data['sql_generado']) ? $data['sql_generado'] : '';
            $codigo_bloque = isset($data['codigo_bloque']) ? $data['codigo_bloque'] : null;
            $titulo = isset($data['titulo']) ? $data['titulo'] : 'Script manual';

            if (!$id_sesion || trim($sql_generado) === '') {
                Flight::json(array('error' => 'Faltan la sesión o el SQL'), 400);
                return;
            }

            $resultado = self::registrar($id_sesion, $codigo_bloque, $titulo, $sql_generado, null);
            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("MigracionScripts::new - " . $e->getMessage());
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
            $titulo = isset($data['titulo']) ? $data['titulo'] : null;
            $sql_generado = isset($data['sql_generado']) ? $data['sql_generado'] : null;

            $sentence = $db->prepare("SELECT id_sesion, estado FROM migracion_scripts
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $script = $sentence->fetch(PDO::FETCH_ASSOC);

            if (!$script) {
                Flight::json(array('error' => 'El script no existe'), 404);
                return;
            }
            if ($script['estado'] === 'ejecutado') {
                Flight::json(array('error' => 'Un script ya ejecutado no se edita. Deshaz el bloque y propón uno nuevo.'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($script['id_sesion']);
            $validacion = self::validar($sql_generado, $sesion);

            $sentence = $db->prepare("UPDATE migracion_scripts SET
                                        titulo = :titulo,
                                        sql_generado = :sql_generado,
                                        hash_sql = :hash,
                                        validado = :validado,
                                        motivo_rechazo = :motivo,
                                        estado = :estado
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':titulo', $titulo);
            $sentence->bindValue(':sql_generado', $sql_generado);
            $sentence->bindValue(':hash', hash('sha256', $sql_generado));
            $sentence->bindValue(':validado', $validacion['valido'] ? 1 : 0, PDO::PARAM_INT);
            $sentence->bindValue(':motivo', $validacion['valido'] ? null : implode(' | ', $validacion['errores']));
            $sentence->bindValue(':estado', $validacion['valido'] ? 'propuesto' : 'rechazado');
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionScripts::replace - " . $e->getMessage());
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

            $sentence = $db->prepare("DELETE FROM migracion_scripts
                                      WHERE id = :id AND id_tenant = :id_tenant AND estado <> 'ejecutado'");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() === 0) {
                Flight::json(array('error' => 'No se borró: un script ya ejecutado queda como registro de lo que se hizo'), 400);
                return;
            }

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionScripts::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Previsualizacion: no toca el destino. Descompone el script y dice
     * cuantas filas entran por tabla. Es lo que ve la persona antes de
     * aprobar.
     */
    public static function getPrevisualizacion()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id = isset(Flight::request()->query['id']) ? Flight::request()->query['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id del script'), 400);
                return;
            }

            $script = self::obtener($id);
            if (!$script) {
                Flight::json(array('error' => 'El script no existe'), 404);
                return;
            }

            $sesion = MigracionSesiones::obtener($script['id_sesion']);
            $sentencias = self::partirSentencias($script['sql_generado']);
            $validacion = self::validar($script['sql_generado'], $sesion);

            $porTabla = array();
            foreach ($sentencias as $sentencia) {
                $info = self::analizarSentencia($sentencia);
                if (!$info['tabla']) {
                    continue;
                }
                $clave = $info['operacion'] . ' ' . $info['tabla'];
                if (!isset($porTabla[$clave])) {
                    $porTabla[$clave] = array('operacion' => $info['operacion'], 'tabla' => $info['tabla'], 'sentencias' => 0, 'filas' => 0);
                }
                $porTabla[$clave]['sentencias']++;
                $porTabla[$clave]['filas'] += $info['filas'];
            }

            $conexion = MigracionConexiones::obtener($sesion['id_conexion']);

            Flight::json(array(
                'id' => $id,
                'titulo' => $script['titulo'],
                'estado' => $script['estado'],
                'validacion' => $validacion,
                'total_sentencias' => count($sentencias),
                'resumen' => array_values($porTabla),
                'muestra' => array_slice($sentencias, 0, 5),
                'resumen_declarado' => $script['resumen_json'] ? json_decode($script['resumen_json'], true) : null,
                'destino' => array(
                    'base_datos' => $conexion ? $conexion['base_datos'] : null,
                    'ambiente' => $conexion ? $conexion['ambiente'] : null,
                    'id_tenant_destino' => $sesion['id_tenant_destino']
                )
            ));
        } catch (Exception $e) {
            error_log("MigracionScripts::getPrevisualizacion - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Aprobar es la decision de la persona; ejecutar es la accion. Van
     * separadas a proposito.
     */
    public static function aprobar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.ejecutar');

            $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id del script'), 400);
                return;
            }

            $script = self::obtener($id);
            if (!$script) {
                Flight::json(array('error' => 'El script no existe'), 404);
                return;
            }
            if ((int)$script['validado'] !== 1) {
                Flight::json(array('error' => 'Este script no pasó la validación: ' . $script['motivo_rechazo']), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("UPDATE migracion_scripts SET estado = 'aprobado'
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'id' => $id, 'estado' => 'aprobado'));
        } catch (Exception $e) {
            error_log("MigracionScripts::aprobar - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Ejecuta un script aprobado contra la base destino, en transaccion.
     * Si algo revienta, InnoDB deshace todo y la base queda como estaba.
     */
    public static function ejecutar()
    {
        $destino = null;
        $sesion = null;
        $script = null;
        $conexion = null;
        $userData = null;

        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.ejecutar');

            $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id del script'), 400);
                return;
            }

            $script = self::obtener($id);
            if (!$script) {
                Flight::json(array('error' => 'El script no existe'), 404);
                return;
            }
            if ($script['estado'] !== 'aprobado') {
                Flight::json(array('error' => 'El script debe estar aprobado antes de ejecutarse'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($script['id_sesion']);
            if (!MigracionSesiones::estaAbierta($sesion)) {
                Flight::json(array('error' => 'La sesión está en estado "' . $sesion['estado'] . '" y ya no admite cambios'), 400);
                return;
            }

            $conexion = MigracionConexiones::obtener($sesion['id_conexion']);
            if (!$conexion) {
                Flight::json(array('error' => 'La conexión destino no existe'), 400);
                return;
            }
            if ((int)$conexion['solo_lectura'] === 1) {
                Flight::json(array('error' => 'La conexión destino es de solo lectura'), 400);
                return;
            }

            // Se revalida contra el estado actual: el script pudo guardarse
            // hace rato y la sesion pudo cambiar de tenant destino.
            $validacion = self::validar($script['sql_generado'], $sesion);
            if (!$validacion['valido']) {
                Flight::json(array('error' => 'El script ya no pasa la validación: ' . implode(' | ', $validacion['errores'])), 400);
                return;
            }

            $version = MigracionEsquemaCache::verificarVersion($sesion);
            if (!$version['coincide']) {
                Flight::json(array(
                    'error' => 'El esquema de la base destino cambió desde que se abrió la sesión. No se ejecuta nada hasta revisarlo.',
                    'version' => $version
                ), 409);
                return;
            }

            $sentencias = self::partirSentencias($script['sql_generado']);
            $destino = MigracionConexiones::pdoDestino($sesion['id_conexion']);

            $tablasTocadas = array();
            $tablasGlobales = array();
            $filasAfectadas = 0;

            $destino->beginTransaction();

            foreach ($sentencias as $sentencia) {
                $info = self::analizarSentencia($sentencia);
                $afectadas = $destino->exec($sentencia);
                if ($afectadas !== false) {
                    $filasAfectadas += (int)$afectadas;
                }
                if ($info['tabla']) {
                    if (!in_array($info['tabla'], $tablasTocadas, true)) {
                        $tablasTocadas[] = $info['tabla'];
                    }
                    // Sin id_tenant: es catalogo global y el deshacer no lo
                    // cubre, asi que se registra aparte.
                    if (!$info['tiene_tenant'] && !in_array($info['tabla'], $tablasGlobales, true)) {
                        $tablasGlobales[] = $info['tabla'];
                    }
                }
            }

            $destino->commit();

            MigracionEjecuciones::registrar($sesion, $script, $conexion, array(
                'sentencias' => count($sentencias),
                'filas' => $filasAfectadas,
                'tablas' => $tablasTocadas,
                'globales' => $tablasGlobales,
                'exito' => true,
                'error' => null,
                'usuario' => isset($userData->usuario) ? $userData->usuario : null
            ));

            self::cambiarEstado($script['id'], 'ejecutado');
            MigracionBloques::cambiarEstado($script['id_bloque'], 'ejecutado');
            MigracionSesiones::marcarEnProceso($sesion['id']);

            $aviso = empty($tablasGlobales) ? null
                : 'Se escribió en catálogos globales (' . implode(', ', $tablasGlobales) . '). El deshacer por tenant NO los cubre.';

            Flight::json(array(
                'success' => true,
                'sentencias' => count($sentencias),
                'filas_afectadas' => $filasAfectadas,
                'tablas_tocadas' => $tablasTocadas,
                'tablas_globales' => $tablasGlobales,
                'aviso_globales' => $aviso
            ));
        } catch (Exception $e) {
            if ($destino && $destino->inTransaction()) {
                $destino->rollBack();
            }

            try {
                if ($sesion && $script && $conexion) {
                    MigracionEjecuciones::registrar($sesion, $script, $conexion, array(
                        'sentencias' => 0,
                        'filas' => 0,
                        'tablas' => array(),
                        'globales' => array(),
                        'exito' => false,
                        'error' => $e->getMessage(),
                        'usuario' => isset($userData->usuario) ? $userData->usuario : null
                    ));
                    MigracionBloques::cambiarEstado($script['id_bloque'], 'error');
                }
            } catch (Exception $e2) {
                error_log("MigracionScripts::ejecutar - falló registrando el error: " . $e2->getMessage());
            }

            error_log("MigracionScripts::ejecutar - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Guarda un script propuesto y lo valida de una vez.
     * Lo llama MigracionMensajes cuando el asistente propone SQL.
     *
     * @return array ['id' => string, 'validacion' => array]
     */
    public static function registrar($id_sesion, $codigo_bloque, $titulo, $sql, $resumen = null)
    {
        $db = Flight::db();
        $sesion = MigracionSesiones::obtener($id_sesion);
        $id_bloque = MigracionBloques::obtenerIdPorCodigo($id_sesion, $codigo_bloque);
        $validacion = self::validar($sql, $sesion);

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO migracion_scripts
            (id, id_tenant, id_sesion, id_bloque, titulo, sql_generado, resumen_json, hash_sql, validado, motivo_rechazo, estado)
            VALUES (:id, :id_tenant, :id_sesion, :id_bloque, :titulo, :sql, :resumen, :hash, :validado, :motivo, :estado)");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_bloque', $id_bloque);
        $sentence->bindValue(':titulo', $titulo);
        $sentence->bindValue(':sql', $sql);
        $sentence->bindValue(':resumen', $resumen ? json_encode($resumen, JSON_UNESCAPED_UNICODE) : null);
        $sentence->bindValue(':hash', hash('sha256', $sql));
        $sentence->bindValue(':validado', $validacion['valido'] ? 1 : 0, PDO::PARAM_INT);
        $sentence->bindValue(':motivo', $validacion['valido'] ? null : implode(' | ', $validacion['errores']));
        $sentence->bindValue(':estado', $validacion['valido'] ? 'propuesto' : 'rechazado');
        $sentence->execute();

        if ($id_bloque && $validacion['valido']) {
            $sentence = $db->prepare("UPDATE migracion_bloques SET estado = 'propuesto'
                                      WHERE id = :id AND id_tenant = :id_tenant AND estado = 'pendiente'");
            $sentence->bindValue(':id', $id_bloque);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
        }

        return array('id' => $idNew, 'validacion' => $validacion);
    }

    /**
     * @return array|false
     */
    public static function obtener($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT s.*, b.codigo AS codigo_bloque
                                  FROM migracion_scripts s
                                  LEFT JOIN migracion_bloques b ON s.id_bloque = b.id
                                  WHERE s.id = :id AND s.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    public static function cambiarEstado($id, $estado)
    {
        if (!$id) {
            return;
        }

        $db = Flight::db();
        $sentence = $db->prepare("UPDATE migracion_scripts SET estado = :estado
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':estado', $estado);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    // =====================================================
    // VALIDADOR
    // =====================================================

    /**
     * Reglas, en orden de importancia:
     *
     *  1. Nada de DDL ni de sentencias administrativas.
     *  2. Todo UPDATE y DELETE filtra por id_tenant.
     *  3. Todo INSERT sobre una tabla con id_tenant lo trae, con el valor
     *     de la sesion.
     *  4. Los comentarios no esconden sentencias.
     *
     * @return array ['valido' => bool, 'errores' => [], 'advertencias' => []]
     */
    public static function validar($sql, $sesion)
    {
        $errores = array();
        $advertencias = array();

        $sentencias = self::partirSentencias($sql);

        if (empty($sentencias)) {
            return array('valido' => false, 'errores' => array('El script está vacío'), 'advertencias' => array(), 'total_sentencias' => 0);
        }

        $idTenant = ($sesion && $sesion['id_tenant_destino'] !== null) ? (int)$sesion['id_tenant_destino'] : null;

        foreach ($sentencias as $indice => $sentencia) {
            $limpia = self::sinLiterales($sentencia);
            $mayus = strtoupper($limpia);
            $numero = $indice + 1;

            foreach (self::PROHIBIDAS as $palabra) {
                if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/', $mayus)) {
                    $errores[] = "Sentencia {$numero}: contiene «{$palabra}», que no está permitido en un script de cargue";
                }
            }

            $operacion = self::operacionDe($mayus);

            if (in_array($operacion, array('UPDATE', 'DELETE'), true)) {
                if (!preg_match('/\bID_TENANT\s*=/', $mayus)) {
                    $errores[] = "Sentencia {$numero}: {$operacion} sin filtro de id_tenant. Ese filtro no es opcional";
                } elseif ($idTenant !== null && !preg_match('/\bID_TENANT\s*=\s*' . $idTenant . '\b/', $mayus)) {
                    $errores[] = "Sentencia {$numero}: {$operacion} filtra por un id_tenant distinto al de la sesión ({$idTenant})";
                }
            }

            if ($operacion === 'INSERT') {
                $info = self::analizarSentencia($sentencia);

                if ($info['tiene_tenant'] && $idTenant !== null) {
                    if (!preg_match('/\b' . $idTenant . '\b/', $limpia)) {
                        $advertencias[] = "Sentencia {$numero}: INSERT en {$info['tabla']} declara id_tenant pero no se ve el valor {$idTenant} de la sesión";
                    }
                } elseif (!$info['tiene_tenant']) {
                    $advertencias[] = "Sentencia {$numero}: INSERT en {$info['tabla']} sin id_tenant. Si esa tabla lleva tenant es un error; si es catálogo global, el deshacer no lo cubre";
                }
            }

            if ($operacion === null) {
                $advertencias[] = "Sentencia {$numero}: no se reconoce la operación; revísala antes de aprobar";
            }
        }

        if ($idTenant === null) {
            $advertencias[] = 'La sesión todavía no tiene id_tenant destino. Hasta que lo tenga no se puede validar el aislamiento ni deshacer por tenant';
        }

        return array(
            'valido' => empty($errores),
            'errores' => $errores,
            'advertencias' => $advertencias,
            'total_sentencias' => count($sentencias)
        );
    }

    // =====================================================
    // ANALISIS DE SQL
    // =====================================================

    /**
     * Parte el script respetando comillas y comentarios. Un split por ';'
     * pelado se rompe con cualquier nombre que traiga punto y coma, y en
     * esta base los hay.
     *
     * @return array
     */
    public static function partirSentencias($sql)
    {
        $sentencias = array();
        $actual = '';
        $largo = strlen($sql);
        $comilla = null;
        $enLinea = false;
        $enBloque = false;

        for ($i = 0; $i < $largo; $i++) {
            $c = $sql[$i];
            $siguiente = $i + 1 < $largo ? $sql[$i + 1] : '';

            if ($enLinea) {
                if ($c === "\n") {
                    $enLinea = false;
                    $actual .= $c;
                }
                continue;
            }
            if ($enBloque) {
                if ($c === '*' && $siguiente === '/') {
                    $enBloque = false;
                    $i++;
                }
                continue;
            }
            if ($comilla === null) {
                if ($c === '-' && $siguiente === '-') {
                    $enLinea = true;
                    $i++;
                    continue;
                }
                if ($c === '#') {
                    $enLinea = true;
                    continue;
                }
                if ($c === '/' && $siguiente === '*') {
                    $enBloque = true;
                    $i++;
                    continue;
                }
                if ($c === "'" || $c === '"' || $c === '`') {
                    $comilla = $c;
                    $actual .= $c;
                    continue;
                }
                if ($c === ';') {
                    if (trim($actual) !== '') {
                        $sentencias[] = trim($actual);
                    }
                    $actual = '';
                    continue;
                }
            } else {
                if ($c === '\\') {
                    $actual .= $c . $siguiente;
                    $i++;
                    continue;
                }
                if ($c === $comilla) {
                    $comilla = null;
                }
            }

            $actual .= $c;
        }

        if (trim($actual) !== '') {
            $sentencias[] = trim($actual);
        }

        return $sentencias;
    }

    /**
     * Saca tabla, operacion, si declara id_tenant y cuantas filas trae.
     *
     * @return array
     */
    public static function analizarSentencia($sentencia)
    {
        $limpia = self::sinLiterales($sentencia);
        $mayus = strtoupper(ltrim($limpia));
        $operacion = self::operacionDe($mayus);

        $tabla = null;
        $filas = 0;
        $tieneTenant = false;

        if ($operacion === 'INSERT' || $operacion === 'REPLACE') {
            if (preg_match('/INTO\s+`?([a-zA-Z0-9_]+)`?/i', $limpia, $coincidencias)) {
                $tabla = strtolower($coincidencias[1]);
            }
            if (preg_match('/\(([^)]*)\)\s*VALUES/i', $limpia, $coincidencias)) {
                $tieneTenant = stripos($coincidencias[1], 'id_tenant') !== false;
            }
            // Cada "(...)" despues de VALUES es una fila.
            if (preg_match('/VALUES(.*)$/is', $limpia, $coincidencias)) {
                $filas = max(1, substr_count($coincidencias[1], '('));
            } else {
                $filas = 1;
            }
        } elseif ($operacion === 'UPDATE') {
            if (preg_match('/UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $limpia, $coincidencias)) {
                $tabla = strtolower($coincidencias[1]);
            }
            $tieneTenant = (bool)preg_match('/\bid_tenant\s*=/i', $limpia);
        } elseif ($operacion === 'DELETE') {
            if (preg_match('/FROM\s+`?([a-zA-Z0-9_]+)`?/i', $limpia, $coincidencias)) {
                $tabla = strtolower($coincidencias[1]);
            }
            $tieneTenant = (bool)preg_match('/\bid_tenant\s*=/i', $limpia);
        }

        return array(
            'operacion' => $operacion,
            'tabla' => $tabla,
            'filas' => $filas,
            'tiene_tenant' => $tieneTenant
        );
    }

    /**
     * Quita el contenido de las cadenas para que un dato como
     * "Colegio DROP" no dispare el validador.
     */
    private static function sinLiterales($sentencia)
    {
        return preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", "''", $sentencia);
    }

    private static function operacionDe($mayus)
    {
        $mayus = ltrim($mayus);
        foreach (array('INSERT', 'UPDATE', 'DELETE', 'SELECT', 'REPLACE', 'SET', 'START TRANSACTION', 'COMMIT', 'ROLLBACK') as $operacion) {
            if (strpos($mayus, $operacion) === 0) {
                return $operacion;
            }
        }
        return null;
    }
}
