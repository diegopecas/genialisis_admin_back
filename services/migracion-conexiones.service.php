<?php
/**
 * Tabla migracion_conexiones.
 *
 * Ademas del CRUD, aqui vive todo lo que tiene que ver con la conexion a
 * las bases destino: el cifrado de la clave y la apertura del PDO. Es la
 * tabla que guarda esos datos, asi que la logica va con ella.
 *
 * La conexion destino se abre por request y no queda permanente contra la
 * base de un cliente.
 */
class MigracionConexiones
{
    private static $destinos = [];

    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, ambiente, host, puerto, base_datos, usuario,
                                             solo_lectura, activo, fecha_creacion,
                                             CASE WHEN clave_cifrada IS NULL OR clave_cifrada = '' THEN 0 ELSE 1 END AS tiene_clave
                                      FROM migracion_conexiones
                                      WHERE id_tenant = :id_tenant AND activo = 1
                                      ORDER BY ambiente, nombre");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionConexiones::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, nombre, ambiente, host, puerto, base_datos, usuario,
                                             solo_lectura, activo, fecha_creacion,
                                             CASE WHEN clave_cifrada IS NULL OR clave_cifrada = '' THEN 0 ELSE 1 END AS tiene_clave
                                      FROM migracion_conexiones
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionConexiones::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $db = Flight::db();
            $data = Flight::request()->data;

            $nombre = isset($data['nombre']) ? trim($data['nombre']) : '';
            $ambiente = isset($data['ambiente']) ? $data['ambiente'] : 'pruebas';
            $host = isset($data['host']) ? trim($data['host']) : '';
            $puerto = isset($data['puerto']) && $data['puerto'] ? (int)$data['puerto'] : 3306;
            $base_datos = isset($data['base_datos']) ? trim($data['base_datos']) : '';
            $usuario = isset($data['usuario']) ? trim($data['usuario']) : '';
            $clave = isset($data['clave']) ? $data['clave'] : '';
            $solo_lectura = isset($data['solo_lectura']) ? (int)$data['solo_lectura'] : 0;

            if ($nombre === '' || $host === '' || $base_datos === '' || $usuario === '') {
                Flight::json(array('error' => 'Nombre, host, base de datos y usuario son obligatorios'), 400);
                return;
            }
            if (!in_array($ambiente, array('semilla', 'pruebas', 'produccion'), true)) {
                Flight::json(array('error' => 'Ambiente inválido'), 400);
                return;
            }

            // La semilla es referencia, nunca destino de escritura.
            if ($ambiente === 'semilla') {
                $solo_lectura = 1;
            }

            $sentence = $db->prepare("INSERT INTO migracion_conexiones
                (id, id_tenant, nombre, ambiente, host, puerto, base_datos, usuario, clave_cifrada, solo_lectura, activo)
                VALUES (:id, :id_tenant, :nombre, :ambiente, :host, :puerto, :base_datos, :usuario, :clave, :solo_lectura, 1)");
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':ambiente', $ambiente);
            $sentence->bindParam(':host', $host);
            $sentence->bindValue(':puerto', $puerto, PDO::PARAM_INT);
            $sentence->bindParam(':base_datos', $base_datos);
            $sentence->bindParam(':usuario', $usuario);
            $sentence->bindValue(':clave', self::cifrar($clave));
            $sentence->bindValue(':solo_lectura', $solo_lectura, PDO::PARAM_INT);
            $sentence->execute();
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionConexiones::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id = isset($data['id']) ? $data['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id de la conexión'), 400);
                return;
            }

            $ambiente = isset($data['ambiente']) ? $data['ambiente'] : 'pruebas';
            $solo_lectura = isset($data['solo_lectura']) ? (int)$data['solo_lectura'] : 0;
            if ($ambiente === 'semilla') {
                $solo_lectura = 1;
            }

            // La clave solo se toca si viene: asi se puede editar la conexion
            // sin obligar a volver a escribirla.
            $cambiaClave = isset($data['clave']) && $data['clave'] !== '';
            $campoClave = $cambiaClave ? ', clave_cifrada = :clave' : '';

            $sentence = $db->prepare("UPDATE migracion_conexiones SET
                                        nombre = :nombre,
                                        ambiente = :ambiente,
                                        host = :host,
                                        puerto = :puerto,
                                        base_datos = :base_datos,
                                        usuario = :usuario,
                                        solo_lectura = :solo_lectura
                                        {$campoClave}
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':nombre', isset($data['nombre']) ? trim($data['nombre']) : '');
            $sentence->bindValue(':ambiente', $ambiente);
            $sentence->bindValue(':host', isset($data['host']) ? trim($data['host']) : '');
            $sentence->bindValue(':puerto', isset($data['puerto']) && $data['puerto'] ? (int)$data['puerto'] : 3306, PDO::PARAM_INT);
            $sentence->bindValue(':base_datos', isset($data['base_datos']) ? trim($data['base_datos']) : '');
            $sentence->bindValue(':usuario', isset($data['usuario']) ? trim($data['usuario']) : '');
            $sentence->bindValue(':solo_lectura', $solo_lectura, PDO::PARAM_INT);
            if ($cambiaClave) {
                $sentence->bindValue(':clave', self::cifrar($data['clave']));
            }
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionConexiones::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Baja logica: si hay sesiones que la referencian, borrarla de verdad
     * dejaria la bitacora sin contexto.
     */
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE migracion_conexiones SET activo = 0
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionConexiones::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Prueba la conexion y devuelve con que se encontro: cuantas tablas
     * tiene, cuantas llevan id_tenant y que tenants ya existen ahi.
     */
    public static function probar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.conexiones');

            $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id de la conexión'), 400);
                return;
            }

            $conexion = self::obtener($id);
            if (!$conexion) {
                Flight::json(array('error' => 'La conexión no existe'), 404);
                return;
            }

            $destino = self::pdoDestino($id);

            $sentence = $destino->prepare("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = :bd");
            $sentence->bindValue(':bd', $conexion['base_datos']);
            $sentence->execute();
            $totalTablas = (int)$sentence->fetch()['total'];

            $sentence = $destino->prepare("SELECT COUNT(DISTINCT TABLE_NAME) AS total FROM information_schema.COLUMNS
                                           WHERE TABLE_SCHEMA = :bd AND COLUMN_NAME = 'id_tenant'");
            $sentence->bindValue(':bd', $conexion['base_datos']);
            $sentence->execute();
            $conTenant = (int)$sentence->fetch()['total'];

            // Se lee de personas porque es la tabla que siempre tiene datos
            // en un tenant ya montado. Si la base esta vacia no es un error.
            $tenants = array();
            try {
                $sentence = $destino->query("SELECT DISTINCT id_tenant FROM personas WHERE id_tenant IS NOT NULL ORDER BY id_tenant");
                foreach ($sentence->fetchAll() as $fila) {
                    $tenants[] = (int)$fila['id_tenant'];
                }
            } catch (Exception $e) {
                error_log("MigracionConexiones::probar - la base destino aún no tiene personas: " . $e->getMessage());
            }

            Flight::json(array(
                'success' => true,
                'base_datos' => $conexion['base_datos'],
                'ambiente' => $conexion['ambiente'],
                'total_tablas' => $totalTablas,
                'tablas_con_tenant' => $conTenant,
                'tablas_globales' => $totalTablas - $conTenant,
                'tenants_presentes' => $tenants
            ));
        } catch (Exception $e) {
            error_log("MigracionConexiones::probar - " . $e->getMessage());
            Flight::json(array('success' => false, 'error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Registro de la conexion, con la clave todavia cifrada.
     *
     * @return array|false
     */
    public static function obtener($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, ambiente, host, puerto, base_datos, usuario,
                                         clave_cifrada, solo_lectura, activo
                                  FROM migracion_conexiones
                                  WHERE id = :id AND id_tenant = :id_tenant AND activo = 1");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * PDO contra la base destino.
     *
     * @return PDO
     * @throws Exception si la conexion no existe o esta inactiva
     */
    public static function pdoDestino($id)
    {
        if (isset(self::$destinos[$id])) {
            return self::$destinos[$id];
        }

        $conexion = self::obtener($id);
        if (!$conexion) {
            throw new Exception('La conexión destino no existe o está inactiva');
        }

        $dsn = 'mysql:host=' . $conexion['host']
             . ';port=' . $conexion['puerto']
             . ';dbname=' . $conexion['base_datos']
             . ';charset=utf8mb4';

        $opciones = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false
        );

        $pdo = new PDO($dsn, $conexion['usuario'], self::descifrar($conexion['clave_cifrada']), $opciones);
        self::$destinos[$id] = $pdo;

        return $pdo;
    }

    public static function cifrar($texto)
    {
        if ($texto === null || $texto === '') {
            return null;
        }
        if (!defined('MIGRACION_CLAVE')) {
            require_once __DIR__ . '/../config/migracion.env.php';
        }
        $iv = openssl_random_pseudo_bytes(16);
        $llave = hash('sha256', MIGRACION_CLAVE, true);
        $cifrado = openssl_encrypt($texto, 'AES-256-CBC', $llave, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cifrado);
    }

    public static function descifrar($cifrado)
    {
        if ($cifrado === null || $cifrado === '') {
            return '';
        }
        if (!defined('MIGRACION_CLAVE')) {
            require_once __DIR__ . '/../config/migracion.env.php';
        }
        $bruto = base64_decode($cifrado);
        if ($bruto === false || strlen($bruto) <= 16) {
            return '';
        }
        $iv = substr($bruto, 0, 16);
        $datos = substr($bruto, 16);
        $llave = hash('sha256', MIGRACION_CLAVE, true);
        $texto = openssl_decrypt($datos, 'AES-256-CBC', $llave, OPENSSL_RAW_DATA, $iv);
        return $texto === false ? '' : $texto;
    }
}
