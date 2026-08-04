<?php
/**
 * Servicio de auditoría compartido (GENIALISIS / PSYNCRONIA).
 *
 * Captura cada request entrante y lo registra en la BD de auditoría.
 * El registro ocurre en register_shutdown_function, liberando antes la
 * respuesta al cliente con fastcgi_finish_request() cuando está disponible,
 * de modo que el INSERT no agrega latencia perceptible al usuario.
 *
 * Punto de uso: AuditService::iniciar('GENIALISIS'); al inicio de index.php.
 */
class AuditService
{
    // Claves cuyo valor se enmascara antes de guardar (editable).
    private static $clavesSensibles = [
        'clave', 'password', 'contrasena', 'contraseña',
        'token', 'secret', 'api_key', 'authorization'
    ];

    // Prefijos de rutas consideradas públicas (editable según index.php).
    private static $prefijosPublicos = [
        '/webhooks/', '/auth/pre-login', '/auth/webauthn',
        '/test-publico/', '/google-calendar/callback'
    ];

    // Límite del body almacenado en bytes. Evita guardar uploads base64 enormes.
    private static $limiteBody = 10240; // 10 KB

    private static $plataforma = null;
    private static $rawBody = '';
    private static $iniciado = false;
    private static $db = null;

    /**
     * Punto de entrada único. Captura el body crudo del request y registra
     * el handler de cierre. Llamar una sola vez al inicio de index.php.
     *
     * @param string $plataforma Identificador del proyecto (GENIALISIS | PSYNCRONIA)
     */
    public static function iniciar($plataforma)
    {
        if (self::$iniciado) {
            return;
        }
        self::$iniciado = true;
        self::$plataforma = $plataforma;

        $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
        if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            // Los uploads multipart no se capturan a propósito (PHP no los expone en php://input).
            if (stripos($contentType, 'multipart/form-data') === false) {
                self::$rawBody = file_get_contents('php://input');
            }
        }

        register_shutdown_function([self::class, 'registrar']);
    }

    /**
     * Se ejecuta al finalizar el request. Libera la respuesta al cliente y
     * luego inserta el registro de auditoría sin bloquear al usuario.
     */
    public static function registrar()
    {
        // Liberar la respuesta al cliente lo antes posible (async real bajo PHP-FPM).
        if (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
        }

        try {
            $metodo = $_SERVER['REQUEST_METHOD'] ?? '';
            if ($metodo === 'OPTIONS') {
                return;
            }

            $uri  = $_SERVER['REQUEST_URI'] ?? '';
            $ruta = parse_url($uri, PHP_URL_PATH);
            if ($ruta === false || $ruta === null) {
                $ruta = $uri;
            }

            if (self::esRutaExcluida($ruta, $metodo)) {
                return;
            }

            $tenant  = self::detectarTenant($ruta);
            $usuario = self::detectarUsuario();

            $queryParams = !empty($_GET)
                ? json_encode(self::enmascarar($_GET), JSON_UNESCAPED_UNICODE)
                : null;

            $body = null;
            if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE']) && self::$rawBody !== '') {
                $body = self::prepararBody(self::$rawBody);
            }

            $statusCode = http_response_code();
            if (!is_int($statusCode)) {
                $statusCode = null;
            }

            self::insertar([
                'plataforma'      => self::$plataforma,
                'tenant'          => $tenant,
                'id_usuario'      => $usuario['id'] ?? null,
                'usuario'         => $usuario['usuario'] ?? null,
                'metodo'          => $metodo,
                'ruta'            => substr($ruta, 0, 2048),
                'query_params'    => $queryParams,
                'request_body'    => $body,
                'status_code'     => $statusCode,
                'ip'              => self::detectarIp(),
                'user_agent'      => isset($_SERVER['HTTP_USER_AGENT'])
                                        ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512) : null,
                'es_ruta_publica' => self::esRutaPublica($ruta) ? 1 : 0
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe afectar la operación del usuario.
            error_log('AUDIT ERROR: ' . $e->getMessage());
        }
    }

    /**
     * Detecta el tenant desde query string, header X-Tenant o la URL
     * (/test-publico/{tenant}/...). Retorna null si no aplica.
     */
    private static function detectarTenant($ruta)
    {
        $tenant = null;

        if (isset($_GET['tenant']) && !empty($_GET['tenant'])) {
            $tenant = $_GET['tenant'];
        } elseif (isset($_SERVER['HTTP_X_TENANT'])) {
            $tenant = $_SERVER['HTTP_X_TENANT'];
        } elseif (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                if (strtolower($key) === 'x-tenant') {
                    $tenant = $value;
                    break;
                }
            }
        }

        // Rutas públicas que llevan el tenant en la URL: /test-publico/{tenant}/...
        if (empty($tenant) && strpos($ruta, '/test-publico/') !== false) {
            $segmentos = array_values(array_filter(explode('/', $ruta)));
            $pos = array_search('test-publico', $segmentos);
            if ($pos !== false && isset($segmentos[$pos + 1])) {
                $tenant = $segmentos[$pos + 1];
            }
        }

        if (empty($tenant)) {
            return null;
        }

        $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
        return empty($tenant) ? null : $tenant;
    }

    /**
     * Extrae el usuario autenticado del JWT si JWTService está cargado.
     * Retorna ['id' => ..., 'usuario' => ...] o array vacío.
     */
    private static function detectarUsuario()
    {
        if (!class_exists('JWTService') || !method_exists('JWTService', 'autenticacionOpcional')) {
            return [];
        }

        $data = JWTService::autenticacionOpcional();
        if (!$data) {
            return [];
        }

        return [
            'id'      => $data->id ?? null,
            'usuario' => $data->usuario ?? null
        ];
    }

    /**
     * Enmascara recursivamente las claves sensibles de un array.
     */
    private static function enmascarar($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::enmascarar($value);
            } elseif (in_array(strtolower((string) $key), self::$clavesSensibles, true)) {
                $value = '***';
            }
        }
        unset($value);

        return $data;
    }

    /**
     * Prepara el body para almacenar: enmascara claves sensibles si es JSON
     * válido y trunca al límite configurado.
     */
    private static function prepararBody($raw)
    {
        $decoded = json_decode($raw, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $raw = json_encode(self::enmascarar($decoded), JSON_UNESCAPED_UNICODE);
        }

        if (strlen($raw) > self::$limiteBody) {
            $raw = substr($raw, 0, self::$limiteBody) . '...[TRUNCADO]';
        }

        return $raw;
    }

    /**
     * Obtiene la IP del cliente, considerando proxies.
     */
    private static function detectarIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    /**
     * Indica si la ruta corresponde a un endpoint público conocido.
     */
    private static function esRutaPublica($ruta)
    {
        foreach (self::$prefijosPublicos as $prefijo) {
            if (strpos($ruta, $prefijo) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Indica si la ruta está en la lista de exclusión (no se audita).
     * La lista se define por plataforma en la constante AUDIT_RUTAS_EXCLUIDAS
     * (ver audit.env.php). Cada regla puede ser '/ruta' (cualquier método)
     * o 'METODO /ruta' (solo ese método).
     */
    private static function esRutaExcluida($ruta, $metodo)
    {
        $reglas = defined('AUDIT_RUTAS_EXCLUIDAS') ? AUDIT_RUTAS_EXCLUIDAS : [];

        foreach ($reglas as $excluida) {
            $metodoRegla = null;
            $rutaRegla = $excluida;

            // Formato opcional "METODO /ruta": separar método y ruta.
            if (strpos($excluida, ' ') !== false) {
                list($posibleMetodo, $resto) = explode(' ', $excluida, 2);
                if (in_array(strtoupper($posibleMetodo), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                    $metodoRegla = strtoupper($posibleMetodo);
                    $rutaRegla = $resto;
                }
            }

            // Si la regla fija un método y no coincide, se ignora.
            if ($metodoRegla !== null && $metodoRegla !== $metodo) {
                continue;
            }

            if (strpos($ruta, $rutaRegla) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Conexión PDO propia a la BD de auditoría (lazy, con timeout corto).
     * No usa el contenedor de Flight porque corre en fase de shutdown.
     */
    private static function db()
    {
        if (self::$db === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 2,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';"
            ];
            self::$db = new PDO(DB_AUDIT_DSN, DB_AUDIT_USERNAME, DB_AUDIT_PASSWORD, $options);
        }
        return self::$db;
    }

    /**
     * Inserta el registro de auditoría.
     */
    private static function insertar($d)
    {
        $sql = "INSERT INTO audit_log
                (plataforma, tenant, id_usuario, usuario, metodo, ruta,
                 query_params, request_body, status_code, ip, user_agent, es_ruta_publica)
                VALUES
                (:plataforma, :tenant, :id_usuario, :usuario, :metodo, :ruta,
                 :query_params, :request_body, :status_code, :ip, :user_agent, :es_ruta_publica)";

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':plataforma',      $d['plataforma']);
        $stmt->bindValue(':tenant',          $d['tenant']);
        $stmt->bindValue(':id_usuario',      $d['id_usuario'], $d['id_usuario'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':usuario',         $d['usuario']);
        $stmt->bindValue(':metodo',          $d['metodo']);
        $stmt->bindValue(':ruta',            $d['ruta']);
        $stmt->bindValue(':query_params',    $d['query_params']);
        $stmt->bindValue(':request_body',    $d['request_body']);
        $stmt->bindValue(':status_code',     $d['status_code'], $d['status_code'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ip',              $d['ip']);
        $stmt->bindValue(':user_agent',      $d['user_agent']);
        $stmt->bindValue(':es_ruta_publica', $d['es_ruta_publica'], PDO::PARAM_INT);
        $stmt->execute();
    }
}