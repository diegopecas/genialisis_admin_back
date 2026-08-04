<?php
/**
 * Servicio JWT para autenticación
 */

// Cargar manualmente las clases de JWT
require_once __DIR__ . '/../vendor/firebase/php-jwt/src/JWT.php';
require_once __DIR__ . '/../vendor/firebase/php-jwt/src/Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTService
{
    // Clave secreta para firmar los tokens 
    // La clave secreta se resuelve desde config/jwt.env.php (JWT_SECRET_KEY),
    // fuera del codigo versionado. Ver getSecretKey().
    
    // Algoritmo de encriptación
    private static $algorithm = 'HS256';
    
    // Tiempo de expiración del token (en segundos)
    // 24 horas = 86400 segundos
    private static $expireTime = 86400;

    // Tiempo de expiración del token efímero de descarga (en segundos)
    // 5 minutos = 300 segundos. Cubre el render de <img> y reabrir el modal.
    private static $expireTimeDescarga = 300;

    /**
     * Obtiene la clave secreta para firmar/validar tokens desde la
     * configuracion no versionada (config/jwt.env.php). Falla cerrado: si la
     * constante JWT_SECRET_KEY no esta definida, corta la peticion, porque
     * firmar o validar con una clave vacia seria inseguro.
     *
     * @return string
     */
    private static function getSecretKey()
    {
        if (!defined('JWT_SECRET_KEY') || JWT_SECRET_KEY === '') {
            error_log('JWTService: JWT_SECRET_KEY no esta definida (config/jwt.env.php).');
            Flight::halt(500, json_encode([
                'error' => 'Configuracion de seguridad incompleta',
                'code'  => 'JWT_SECRET_MISSING'
            ]));
            exit;
        }

        return JWT_SECRET_KEY;
    }

    /**
     * Genera un token JWT para el usuario
     * 
     * @param array $userData Datos del usuario (id, id_persona, usuario, etc.)
     * @param array $permisos Array de códigos de permisos del usuario (ej: ['menu.estudiantes', 'admin.productos.crear']) o ['*'] si es super_admin
     * @param string|null $tenant Codigo del tenant
     * @param array $extra Claims adicionales firmados (ej: ['portal' => 'padres', 'hd_v' => '1.0'])
     * @return string Token JWT
     */
    public static function generarToken($userData, $permisos = [], $tenant = null, $extra = [])
    {
        $issuedAt = time();
        $expire = $issuedAt + self::$expireTime;

        $payload = [
            'iat' => $issuedAt,           // Tiempo de emisión
            'exp' => $expire,              // Tiempo de expiración
            'data' => array_merge([
                'id' => $userData['id'],
                'id_persona' => $userData['id_persona'],
                'usuario' => $userData['usuario'],
                'primer_nombre' => $userData['primer_nombre'] ?? '',
                'primer_apellido' => $userData['primer_apellido'] ?? '',
                'super_admin' => $userData['super_admin'] ?? 0,
                'tenant' => $tenant,
                'permisos' => $permisos
            ], $extra)
        ];

        return JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }

    /**
     * Portales validos. 'institucional' es el valor por defecto: los tokens
     * emitidos antes de este cambio no traen el claim y no deben bloquearse.
     */
    const PORTAL_PADRES = 'padres';
    const PORTAL_INSTITUCIONAL = 'institucional';

    /**
     * Normaliza el portal recibido del cliente. Cualquier valor desconocido
     * cae a 'institucional' (no bloqueado), nunca a 'padres'.
     *
     * @param string|null $portal
     * @return string
     */
    public static function normalizarPortal($portal)
    {
        return $portal === self::PORTAL_PADRES
            ? self::PORTAL_PADRES
            : self::PORTAL_INSTITUCIONAL;
    }

    /**
     * Valida un token JWT y retorna los datos del usuario
     * 
     * @param string $token Token JWT a validar
     * @return object|null Datos del usuario si es válido, null si no
     */
    public static function validarToken($token)
    {
        try {
            if (empty($token)) {
                return null;
            }

            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));

            // Un token efímero de recurso (?token= en <img src="">) NO es un
            // token de sesion: se distingue por el claim 'tipo'. Sin este
            // rechazo pasaria por requerirAutenticacion/requerirTenant (que
            // solo comparan el tenant) y daria acceso a toda la API, que es
            // justo lo que este mecanismo busca evitar.
            if (isset($decoded->data->tipo)) {
                return null;
            }

            return $decoded->data;
        } catch (\Firebase\JWT\ExpiredException $e) {
            // Token expirado
            return null;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            // Firma inválida
            return null;
        } catch (\Exception $e) {
            // Cualquier otro error
            return null;
        }
    }

    /**
     * Obtiene el token del request (header Authorization o query param)
     * 
     * @return string|null Token si existe, null si no
     */
    public static function obtenerTokenDelRequest()
    {
        $token = null;

        // 1. Intentar obtener del query string (para imágenes en <img src="">)
        if (isset($_GET['token'])) {
            $token = $_GET['token'];
        }

        // 2. Si no está en query, intentar del header Authorization
        if (!$token) {
            $headers = self::getAuthorizationHeader();
            if ($headers) {
                // Bearer token
                if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                    $token = $matches[1];
                }
            }
        }

        return $token;
    }

    /**
     * Obtiene el header Authorization de diferentes formas
     * (compatibilidad con diferentes servidores)
     * 
     * @return string|null
     */
    private static function getAuthorizationHeader()
    {
        $headers = null;

        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER['Authorization']);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }

        return $headers;
    }

    /**
     * Middleware: Valida que el request tenga un token válido
     * Detiene la ejecución si no es válido
     * 
     * @return object Datos del usuario (incluye ->permisos y ->super_admin)
     */
    public static function requerirAutenticacion()
    {
        $token = self::obtenerTokenDelRequest();

        if (!$token) {
            Flight::halt(401, json_encode([
                'error' => 'Token no proporcionado',
                'code' => 'NO_TOKEN'
            ]));
            exit;
        }

        $userData = self::validarToken($token);

        if (!$userData) {
            Flight::halt(401, json_encode([
                'error' => 'Token inválido o expirado',
                'code' => 'INVALID_TOKEN'
            ]));
            exit;
        }

        return $userData;
    }

    /**
     * Valida token opcionalmente (no detiene si no hay token)
     * Útil para rutas que funcionan con o sin autenticación
     * 
     * @return object|null Datos del usuario si hay token válido, null si no
     */
    public static function autenticacionOpcional()
    {
        $token = self::obtenerTokenDelRequest();
        
        if (!$token) {
            return null;
        }

        return self::validarToken($token);
    }

    /**
     * Middleware: exige un token valido Y que el tenant firmado dentro del
     * token coincida con el tenant del request (X-Tenant ya validado).
     *
     * Blinda contra el cambio de header tras autenticarse: el tenant viaja
     * firmado dentro del JWT con una clave que solo conoce el servidor, asi
     * que no puede falsificarse cambiando el header del lado cliente.
     *
     * Nota: los tokens emitidos antes de incluir el tenant no traen este dato;
     * esos usuarios deberan volver a iniciar sesion una vez tras el despliegue.
     *
     * @param string|null $tenantRequest Codigo de tenant del request (ej. 'lumen').
     * @return object Datos del usuario (incluye ->permisos, ->super_admin, ->tenant).
     */
    public static function requerirTenant($tenantRequest)
    {
        $userData = self::requerirAutenticacion();

        $tenantToken = isset($userData->tenant) ? $userData->tenant : null;

        if (empty($tenantToken) || empty($tenantRequest) || $tenantToken !== $tenantRequest) {
            Flight::halt(403, json_encode([
                'error' => 'El token no corresponde a la institucion solicitada',
                'code'  => 'TENANT_MISMATCH'
            ]));
            exit;
        }

        return $userData;
    }

    /**
     * Genera un token efímero (5 min) para acceder a UN recurso protegido.
     * No es un token de sesion: solo sirve para el tipo de recurso y tenant
     * indicados, y solo lo aceptan las rutas que lo validan explicitamente.
     * Asi, aunque se filtre en la URL, no da acceso a nada mas.
     *
     * Se usa donde el token NO puede viajar en el header Authorization, es
     * decir en URLs que consume el navegador directamente (<img src="">).
     * Para descargas via HttpClient se sigue usando el token de sesion.
     *
     * @param string      $tipo       tipo de recurso: 'documento' | 'imagen'
     * @param string|null $idRecurso  id del recurso autorizado. Si es null, el
     *                                token sirve para cualquier recurso de ese
     *                                tipo dentro del tenant (galerias: evita
     *                                pedir un token por cada miniatura).
     * @param string      $tenant     codigo del tenant
     * @return string Token JWT efímero
     */
    public static function generarTokenRecurso($tipo, $idRecurso, $tenant)
    {
        $issuedAt = time();

        $payload = [
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::$expireTimeDescarga,
            'data' => [
                'tipo'   => $tipo,
                'rec'    => $idRecurso,
                'tenant' => $tenant
            ]
        ];

        return JWT::encode($payload, self::getSecretKey(), self::$algorithm);
    }

    /**
     * Valida un token efímero de recurso: firma correcta, no expirado, del tipo
     * esperado, del mismo tenant, y para ese recurso.
     *
     * Si el token se emitio con rec = null, vale para cualquier recurso de ese
     * tipo en el tenant. Si se emitio con un id, debe coincidir exactamente.
     *
     * @param string      $token      Token recibido en ?token=
     * @param string      $tipo       tipo de recurso esperado
     * @param string|null $idRecurso  id del recurso solicitado
     * @param string      $tenant     codigo del tenant del request
     * @return bool true si el token autoriza ese recurso
     */
    public static function validarTokenRecurso($token, $tipo, $idRecurso, $tenant)
    {
        try {
            if (empty($token)) {
                return false;
            }

            $decoded = JWT::decode($token, new Key(self::getSecretKey(), self::$algorithm));
            $data = isset($decoded->data) ? $decoded->data : null;

            if (!$data) {
                return false;
            }

            // El tipo evita que un token de imagenes sirva para documentos.
            if (!isset($data->tipo) || $data->tipo !== $tipo) {
                return false;
            }

            if (!isset($data->tenant) || $data->tenant !== $tenant) {
                return false;
            }

            // rec null = token de alcance tenant para ese tipo de recurso.
            $rec = isset($data->rec) ? $data->rec : null;
            if ($rec !== null && $rec !== $idRecurso) {
                return false;
            }

            return true;
        } catch (\Firebase\JWT\ExpiredException $e) {
            return false;
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Middleware: exige un token efímero valido para el recurso indicado.
     * Lee el token de ?token= y corta con 401 si no sirve.
     *
     * @param string      $tipo       tipo de recurso esperado
     * @param string|null $idRecurso  id del recurso solicitado
     * @param string      $tenant     codigo del tenant del request
     * @return void
     */
    public static function requerirTokenRecurso($tipo, $idRecurso, $tenant)
    {
        $token = isset($_GET['token']) ? $_GET['token'] : null;

        if (!self::validarTokenRecurso($token, $tipo, $idRecurso, $tenant)) {
            Flight::halt(401, json_encode([
                'error' => 'Token de acceso invalido o expirado',
                'code'  => 'INVALID_RESOURCE_TOKEN'
            ]));
            exit;
        }
    }

    /**
     * Tiempo de vida (segundos) del token efímero. El front lo usa para saber
     * cuando renovarlo antes de que expire.
     */
    public static function getExpiracionTokenRecurso()
    {
        return self::$expireTimeDescarga;
    }
}