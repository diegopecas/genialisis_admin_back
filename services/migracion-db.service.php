<?php
/**
 * MigracionDb
 *
 * Punto unico de conexion del modulo. Maneja tres bases distintas:
 *
 *   Flight::db()            -> g_admin_prod   (el Admin: usuarios, clientes, ia_configuracion)
 *   MigracionDb::mig()      -> g_migracion_prod (el expediente de la sesion)
 *   MigracionDb::destino()  -> la base de Genialisis producto donde se siembra
 *
 * La conexion destino se abre por sesion y se cierra al terminar el request.
 * Nunca queda una conexion permanente contra la base de un cliente.
 */
class MigracionDb
{
    private static $mig = null;
    private static $destinos = [];

    private static function opciones()
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
    }

    /**
     * Conexion a la base intermedia de migracion.
     */
    public static function mig()
    {
        if (self::$mig === null) {
            if (!defined('DB_MIGRACION_DSN')) {
                require_once __DIR__ . '/../config/migracion.env.php';
            }
            self::$mig = new PDO(
                DB_MIGRACION_DSN,
                DB_MIGRACION_USERNAME,
                DB_MIGRACION_PASSWORD,
                self::opciones()
            );
        }
        return self::$mig;
    }

    /**
     * Conexion a una base destino a partir de su registro en mig_conexiones.
     *
     * @param string $idConexion
     * @return PDO
     * @throws Exception si la conexion no existe o esta inactiva
     */
    public static function destino($idConexion)
    {
        if (isset(self::$destinos[$idConexion])) {
            return self::$destinos[$idConexion];
        }

        $conexion = self::obtenerConexion($idConexion);
        if (!$conexion) {
            throw new Exception('La conexión destino no existe o está inactiva');
        }

        $dsn = 'mysql:host=' . $conexion['host']
             . ';port=' . $conexion['puerto']
             . ';dbname=' . $conexion['base_datos']
             . ';charset=utf8mb4';

        $pdo = new PDO(
            $dsn,
            $conexion['usuario'],
            self::descifrar($conexion['clave_cifrada']),
            self::opciones()
        );

        self::$destinos[$idConexion] = $pdo;
        return $pdo;
    }

    /**
     * Devuelve el registro de la conexion (sin descifrar la clave).
     */
    public static function obtenerConexion($idConexion)
    {
        $db = self::mig();
        $stmt = $db->prepare("SELECT id, nombre, ambiente, host, puerto, base_datos, usuario, clave_cifrada, solo_lectura, activo
                              FROM mig_conexiones WHERE id = :id AND activo = 1");
        $stmt->bindParam(':id', $idConexion);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // =====================================================
    // CIFRADO DE CLAVES DE CONEXION
    // =====================================================

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

    // =====================================================
    // AYUDAS COMUNES
    // =====================================================

    /**
     * Trae la sesion con su conexion resuelta. Lanza excepcion si no existe.
     */
    public static function sesion($idSesion)
    {
        $db = self::mig();
        $stmt = $db->prepare("SELECT * FROM mig_sesiones WHERE id = :id");
        $stmt->bindParam(':id', $idSesion);
        $stmt->execute();
        $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sesion) {
            throw new Exception('La sesión de migración no existe');
        }
        return $sesion;
    }

    /**
     * Valida que la sesion este en un estado que permita escribir.
     */
    public static function exigirSesionAbierta($sesion)
    {
        if (!in_array($sesion['estado'], ['abierta', 'en_proceso'], true)) {
            throw new Exception('La sesión está en estado "' . $sesion['estado'] . '" y ya no admite cambios');
        }
    }

    /**
     * Carpeta de archivos de una sesion.
     */
    public static function carpetaSesion($idSesion)
    {
        if (!defined('MIGRACION_RUTA_ARCHIVOS')) {
            require_once __DIR__ . '/../config/migracion.env.php';
        }
        $ruta = rtrim(MIGRACION_RUTA_ARCHIVOS, '/\\') . DIRECTORY_SEPARATOR . $idSesion;
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0775, true);
        }
        return $ruta;
    }
}
