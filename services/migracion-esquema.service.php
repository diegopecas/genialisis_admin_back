<?php
/**
 * MigracionEsquema
 *
 * Lee el esquema y los catalogos de la base destino EN VIVO, desde
 * INFORMATION_SCHEMA. Nadie genera dumps: si el modelo cambia, el
 * asistente se entera solo.
 *
 * El resultado se cachea en mig_esquema_cache por conexion y se refresca
 * unicamente cuando cambia el hash. Ese hash tambien es la verificacion
 * de version: si el esquema del destino no es el mismo con el que se
 * genero un script, no se deja ejecutar.
 */
class MigracionEsquema
{
    /**
     * Tablas del ciclo de migracion, agrupadas por bloque. La IA solo
     * recibe el DDL del bloque en curso: mandarle las 318 tablas en cada
     * turno no cabe ni sirve.
     */
    public static function tablasPorBloque()
    {
        return [
            'tenant' => [
                'configuracion_global', 'instituciones'
            ],
            'catalogos' => [
                'tipos_acudiente', 'tipos_parentesco', 'tipos_identificacion', 'generos',
                'tipos_contacto', 'tipos_plantillas', 'estados_actividades',
                'paises', 'departamentos', 'ciudades'
            ],
            'academico' => [
                'grados', 'grupos', 'grados_x_grupos', 'areas', 'cortes', 'ambientes',
                'tarifas_grupos', 'conceptos_pagos', 'medios_pago', 'productos_servicios',
                'cargos', 'roles', 'colaboradores', 'docentes'
            ],
            'personas' => [
                'personas', 'estudiantes', 'acudientes', 'estudiantes_x_grupos',
                'usuarios', 'datos_medicos_x_estudiante', 'horarios_estudiante'
            ],
            'contratos' => [
                'contratos_matricula', 'contratos_matricula_valores',
                'plantillas_contratos', 'contratos_clausulas'
            ],
            'cartera' => [
                'cuentas_por_cobrar', 'pagos_recibidos', 'pagos', 'conceptos_pagos'
            ],
        ];
    }

    /**
     * Devuelve el esquema de la base destino para un bloque (o completo).
     */
    public static function obtener()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $idSesion = Flight::request()->query['id_sesion'] ?? null;
            $bloque = Flight::request()->query['bloque'] ?? null;

            if (!$idSesion) {
                Flight::json(['error' => 'Falta el id de la sesión'], 400);
                return;
            }

            $sesion = MigracionDb::sesion($idSesion);
            $datos = self::leerEsquema($sesion['id_conexion'], $bloque);

            Flight::json($datos);
        } catch (Exception $e) {
            error_log("MigracionEsquema::obtener - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lee el esquema (todas las tablas del ciclo o solo las de un bloque)
     * y los catalogos. Usa cache por hash.
     *
     * @param string      $idConexion
     * @param string|null $bloque
     * @return array
     */
    public static function leerEsquema($idConexion, $bloque = null)
    {
        $conexion = MigracionDb::obtenerConexion($idConexion);
        if (!$conexion) {
            throw new Exception('La conexión destino no existe');
        }

        $destino = MigracionDb::destino($idConexion);
        $bd = $conexion['base_datos'];

        $mapa = self::tablasPorBloque();
        if ($bloque && isset($mapa[$bloque])) {
            $tablas = $mapa[$bloque];
        } else {
            $tablas = [];
            foreach ($mapa as $lista) {
                $tablas = array_merge($tablas, $lista);
            }
            $tablas = array_values(array_unique($tablas));
        }

        // Columnas
        $marcadores = implode(',', array_fill(0, count($tablas), '?'));
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT,
                       COLUMN_KEY, EXTRA, COLUMN_COMMENT
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME IN ({$marcadores})
                ORDER BY TABLE_NAME, ORDINAL_POSITION";
        $stmt = $destino->prepare($sql);
        $stmt->execute(array_merge([$bd], $tablas));
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $esquema = [];
        foreach ($filas as $f) {
            $t = $f['TABLE_NAME'];
            if (!isset($esquema[$t])) {
                $esquema[$t] = ['tabla' => $t, 'tiene_tenant' => false, 'columnas' => []];
            }
            if ($f['COLUMN_NAME'] === 'id_tenant') {
                $esquema[$t]['tiene_tenant'] = true;
            }
            $esquema[$t]['columnas'][] = [
                'nombre' => $f['COLUMN_NAME'],
                'tipo' => $f['COLUMN_TYPE'],
                'nulo' => $f['IS_NULLABLE'] === 'YES',
                'defecto' => $f['COLUMN_DEFAULT'],
                'llave' => $f['COLUMN_KEY'],
                'extra' => $f['EXTRA'],
                'comentario' => $f['COLUMN_COMMENT']
            ];
        }

        // Llaves foraneas: el orden de escritura depende de esto.
        $sql = "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                  AND TABLE_NAME IN ({$marcadores})";
        $stmt = $destino->prepare($sql);
        $stmt->execute(array_merge([$bd], $tablas));
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
            $t = $fk['TABLE_NAME'];
            if (!isset($esquema[$t])) {
                continue;
            }
            $esquema[$t]['fk'][] = [
                'columna' => $fk['COLUMN_NAME'],
                'tabla_referida' => $fk['REFERENCED_TABLE_NAME'],
                'columna_referida' => $fk['REFERENCED_COLUMN_NAME']
            ];
        }

        $hash = hash('sha256', json_encode($esquema));

        $resultado = [
            'base_datos' => $bd,
            'ambiente' => $conexion['ambiente'],
            'hash_esquema' => $hash,
            'total_tablas' => count($esquema),
            'esquema' => array_values($esquema)
        ];

        self::guardarCache($idConexion, $hash, $resultado);

        return $resultado;
    }

    /**
     * Contenido de los catalogos del destino. Contra esto la IA normaliza
     * los grados sucios y los parentescos.
     *
     * Importante: los catalogos globales (sin id_tenant) se leen completos;
     * los del tenant se filtran por id_tenant.
     */
    public static function catalogos()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $idSesion = Flight::request()->query['id_sesion'] ?? null;
            if (!$idSesion) {
                Flight::json(['error' => 'Falta el id de la sesión'], 400);
                return;
            }

            $sesion = MigracionDb::sesion($idSesion);
            Flight::json(self::leerCatalogos($sesion));
        } catch (Exception $e) {
            error_log("MigracionEsquema::catalogos - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function leerCatalogos($sesion)
    {
        $destino = MigracionDb::destino($sesion['id_conexion']);
        $conexion = MigracionDb::obtenerConexion($sesion['id_conexion']);
        $bd = $conexion['base_datos'];

        // Que tablas de catalogo existen y cuales llevan id_tenant.
        $stmt = $destino->prepare("SELECT t.TABLE_NAME,
                                          MAX(CASE WHEN c.COLUMN_NAME = 'id_tenant' THEN 1 ELSE 0 END) AS tiene_tenant
                                   FROM information_schema.TABLES t
                                   INNER JOIN information_schema.COLUMNS c
                                        ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                                   WHERE t.TABLE_SCHEMA = :bd
                                     AND (t.TABLE_NAME LIKE 'tipos\\_%' OR t.TABLE_NAME LIKE 'estados\\_%'
                                          OR t.TABLE_NAME IN ('generos','paises','departamentos','ciudades',
                                                              'dias_semana','periodicidad','periodicidad_cobro',
                                                              'niveles_escolaridad','motivos_retiro','unidades_medida'))
                                   GROUP BY t.TABLE_NAME
                                   ORDER BY t.TABLE_NAME");
        $stmt->bindValue(':bd', $bd);
        $stmt->execute();
        $tablas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $catalogos = [];
        foreach ($tablas as $t) {
            $tabla = $t['TABLE_NAME'];
            $tieneTenant = (int)$t['tiene_tenant'] === 1;

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
                // Una tabla de catalogo que no se puede leer no debe tumbar
                // la carga del contexto completo.
                error_log("MigracionEsquema - no se pudo leer el catálogo {$tabla}: " . $e->getMessage());
                continue;
            }

            $catalogos[] = [
                'tabla' => $tabla,
                'global' => !$tieneTenant,
                'total' => count($filas),
                'filas' => $filas
            ];
        }

        return [
            'base_datos' => $bd,
            'id_tenant_destino' => $sesion['id_tenant_destino'],
            'total_catalogos' => count($catalogos),
            'catalogos' => $catalogos
        ];
    }

    /**
     * Compara el hash actual del destino contra el guardado en la sesion.
     * Si no coinciden, el esquema cambio y no se puede ejecutar nada.
     */
    public static function verificarVersion($sesion)
    {
        $actual = self::leerEsquema($sesion['id_conexion']);

        if (empty($sesion['hash_esquema'])) {
            // Primera vez: se fija el hash de referencia.
            $db = MigracionDb::mig();
            $stmt = $db->prepare("UPDATE mig_sesiones SET hash_esquema = :hash WHERE id = :id");
            $stmt->bindValue(':hash', $actual['hash_esquema']);
            $stmt->bindValue(':id', $sesion['id']);
            $stmt->execute();
            return ['coincide' => true, 'hash' => $actual['hash_esquema']];
        }

        return [
            'coincide' => $sesion['hash_esquema'] === $actual['hash_esquema'],
            'hash_sesion' => $sesion['hash_esquema'],
            'hash_destino' => $actual['hash_esquema']
        ];
    }

    public static function verificar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $idSesion = Flight::request()->query['id_sesion'] ?? null;
            if (!$idSesion) {
                Flight::json(['error' => 'Falta el id de la sesión'], 400);
                return;
            }

            $sesion = MigracionDb::sesion($idSesion);
            $resultado = self::verificarVersion($sesion);

            if (!$resultado['coincide']) {
                $resultado['mensaje'] = 'El esquema de la base destino cambió desde que se abrió la sesión. '
                    . 'Revisa el modelo antes de ejecutar: los scripts pueden referirse a campos que ya no existen.';
            }

            Flight::json($resultado);
        } catch (Exception $e) {
            error_log("MigracionEsquema::verificar - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function guardarCache($idConexion, $hash, $datos)
    {
        try {
            $db = MigracionDb::mig();
            $stmt = $db->prepare("INSERT INTO mig_esquema_cache (id, id_conexion, hash_esquema, esquema_json, fecha)
                                  VALUES (:id, :id_conexion, :hash, :json, NOW())
                                  ON DUPLICATE KEY UPDATE hash_esquema = :hash2, esquema_json = :json2, fecha = NOW()");
            $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
            $stmt->bindValue(':id', Uuid::generar());
            $stmt->bindValue(':id_conexion', $idConexion);
            $stmt->bindValue(':hash', $hash);
            $stmt->bindValue(':hash2', $hash);
            $stmt->bindValue(':json', $json);
            $stmt->bindValue(':json2', $json);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("MigracionEsquema::guardarCache - " . $e->getMessage());
        }
    }
}
