<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('max_execution_time', 300);
error_reporting(E_ALL);

date_default_timezone_set('America/Bogota');

// ===================================================================
// CONFIGURACIÓN CORS - DEBE ESTAR PRIMERO
// ===================================================================
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: X-Tenant, x-tenant, X-API-KEY, X-Silent, X-Skip-Tenant, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH");
// Sin esto el navegador oculta Content-Disposition al JS y las descargas blob
// pierden el nombre real del archivo (bajan con el nombre por defecto, sin extension).
header("Access-Control-Expose-Headers: Content-Disposition");
header("Allow: GET, POST, OPTIONS, PUT, DELETE, PATCH");

$method = $_SERVER['REQUEST_METHOD'];
if($method == "OPTIONS") {
    http_response_code(200);
    exit(0);
}

// ===================================================================
// 🛡️ AUDITORÍA - Punto único de captura (antes de cualquier ruta)
// ===================================================================
require_once __DIR__ . '/config/audit.env.php';
require_once __DIR__ . '/services/audit.service.php';
AuditService::iniciar('GENIALISIS');

// ===================================================================
// 🔓 RUTAS PÚBLICAS - NO REQUIEREN X-TENANT
// ===================================================================
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Webhook Firma Digital
if (strpos($requestUri, '/webhooks/firma') !== false) {
    require 'flight/Flight.php';
    require_once __DIR__ . '/services/firma-webhook.service.php';
    require_once __DIR__ . '/routes/firma-webhook.routes.php';
    
    Flight::start();
    exit(0);
}
// Login biométrico directo (sin tenant)
if (strpos($requestUri, '/auth/webauthn') !== false) {
    require 'flight/Flight.php';
    require_once __DIR__ . '/config/master.env.php';
    require_once __DIR__ . '/config/jwt.env.php';
    require_once __DIR__ . '/vendor/firebase/php-jwt/src/JWT.php';
    require_once __DIR__ . '/vendor/firebase/php-jwt/src/Key.php';
    require_once __DIR__ . '/services/jwt.service.php';
    require_once __DIR__ . '/services/webauthn.service.php';
    require_once __DIR__ . '/routes/auth-webauthn.routes.php';
    
    Flight::start();
    exit(0);
}
// Pre-Login (autenticación sin tenant)
if (strpos($requestUri, '/auth/pre-login') !== false) {
    require 'flight/Flight.php';
    require_once __DIR__ . '/config/master.env.php';
    require_once __DIR__ . '/services/auth-master.service.php';
    require_once __DIR__ . '/routes/auth-master.routes.php';
    
    Flight::start();
    exit(0);
}

require 'flight/Flight.php';

// ===================================================================
// SEGURIDAD - clave JWT (no versionada) y contexto de tenant centralizado
// ===================================================================
require_once __DIR__ . '/config/jwt.env.php';
require_once __DIR__ . '/services/tenant-context.service.php';


// ===================================================================
// 🔒 CARGAR TENANT - SIN VALORES POR DEFECTO
// ===================================================================
$tenant = null;

// 1. Primero intentar desde query string (para <img src="">)
if (isset($_GET['tenant']) && !empty($_GET['tenant'])) {
    $tenant = $_GET['tenant'];
}
// 2. Si no, buscar en header
elseif (isset($_SERVER['HTTP_X_TENANT'])) {
    $tenant = $_SERVER['HTTP_X_TENANT'];
} elseif (function_exists('getallheaders')) {
    $headers = getallheaders();
    foreach ($headers as $key => $value) {
        if (strtolower($key) === 'x-tenant') {
            $tenant = $value;
            break;
        }
    }
}

// 🚨 VALIDACIÓN ESTRICTA: Si no hay tenant, devolver error 400
if (empty($tenant)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Header X-Tenant es requerido',
        'code' => 'MISSING_TENANT_HEADER'
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

// Sanitizar el tenant (solo permitir caracteres seguros)
$tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);

// Validar que después de sanitizar aún tengamos algo
if (empty($tenant)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Formato de tenant inválido',
        'code' => 'INVALID_TENANT_FORMAT'
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

// Construir ruta del archivo de configuración
$configFile = __DIR__ . "/config/tenants/{$tenant}.env.php";

// 🚨 VALIDACIÓN ESTRICTA: Si el archivo no existe, devolver error 404
if (!file_exists($configFile)) {
    error_log("❌ Archivo de configuración no encontrado para tenant: {$tenant}");
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'error' => true,
        'message' => "Configuración no encontrada para la institución: {$tenant}",
        'code' => 'TENANT_CONFIG_NOT_FOUND',
        'tenant' => $tenant
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

// ✅ Si llegamos aquí, cargar la configuración
require_once $configFile;

// Fijar el contexto de tenant (codigo validado). El id numerico se obtiene
// via TenantContext::id() desde la constante TENANT_ID del .env.php del tenant.
TenantContext::setCodigo($tenant);
// error_log("✅ Tenant cargado exitosamente: {$tenant} -> BD: " . DB_NAME);

function convertirNumerosEnArray(&$array) {
    if (!is_array($array)) return;
    
    foreach ($array as $key => &$value) {
        if (is_array($value)) {
            convertirNumerosEnArray($value);
        } elseif (is_string($value) && is_numeric($value)) {
            // Las versiones son strings ('1.0' != 1.0). Sin esto, json_encode
            // devuelve 1 y cualquier comparacion de version se rompe.
            $camposString = ['telefono', 'celular', 'documento', 'ruc', 'codigo_postal', 'nit', 'clave', 'fecha',
                             'version', 'version_actual', 'version_politica', 'hd_v', 'referencia', 'referencia_bancaria'];
            
            if (!in_array($key, $camposString)) {
                $value = strpos($value, '.') !== false ? (float)$value : (int)$value;
            }
        }
    }
}

function responderJSON($data, $code = 200) {
    convertirNumerosEnArray($data);
    Flight::response()->status($code);
    Flight::response()->header('Content-Type: application/json; charset=utf-8');
    Flight::response()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
    Flight::response()->send();
    exit;
}

Flight::before('json_convert', function(&$params, &$output) {
    $method = Flight::request()->method;
    
    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        try {
            $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
            if (empty($contentType) && isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                $contentType = $_SERVER['HTTP_CONTENT_TYPE'];
            }
            
            if (stripos($contentType, 'application/json') !== false) {
                $body = Flight::request()->getBody();
                
                if (!empty($body)) {
                    $data = json_decode($body, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && $data !== null) {
                        convertirNumerosEnArray($data);
                        Flight::request()->data->setData($data);
                    } else {
                        error_log("Error al decodificar JSON: " . json_last_error_msg());
                    }
                }
            }
        } catch (Exception $e) {
            error_log("ERROR en middleware: " . $e->getMessage());
        }
    }
});

foreach (glob(__DIR__ . '/services/*.service.php') as $serviceFile) {
    require_once $serviceFile;
}

foreach (glob(__DIR__ . '/routes/*.routes.php') as $routeFile) {
    require_once $routeFile;
}

Flight::route('/', function () {
    $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : 'NO_TENANT';
    echo "API v1.0 Multi-Tenant - Tenant activo: {$tenant}";
});

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
    PDO::ATTR_STRINGIFY_FETCHES => false,
    PDO::ATTR_EMULATE_PREPARES => false
];

Flight::register('db', 'PDO', array(
    DB_DSN,
    DB_USERNAME,
    DB_PASSWORD,
    $options
));

Flight::after('db', function($db) {
    try {
        $db->exec("SET time_zone = '-05:00'");
    } catch (Exception $e) {
        error_log("Error configurando zona horaria MySQL: " . $e->getMessage());
    }
});

// ===================================================================
// 📌 REGISTRAR CONEXIÓN BD MAESTRA
// ===================================================================
require_once __DIR__ . '/config/master.env.php';

Flight::register('db_master', 'PDO', array(
    DB_MASTER_DSN,
    DB_MASTER_USERNAME,
    DB_MASTER_PASSWORD,
    $options
));

Flight::after('db_master', function($db) {
    try {
        $db->exec("SET time_zone = '-05:00'");
    } catch (Exception $e) {
        error_log("Error configurando zona horaria MySQL (master): " . $e->getMessage());
    }
});

// ===================================================================
// 📌 REGISTRAR CONEXIÓN BD PORTAL (SI EXISTE)
// ===================================================================
if (defined('DB_PORTAL_DSN')) {
    Flight::register('db_portal', 'PDO', array(
        DB_PORTAL_DSN,
        DB_PORTAL_USERNAME,
        DB_PORTAL_PASSWORD,
        $options
    ));
    
    Flight::after('db_portal', function($db) {
        try {
            $db->exec("SET time_zone = '-05:00'");
        } catch (Exception $e) {
            error_log("Error configurando zona horaria MySQL (portal): " . $e->getMessage());
        }
    });
    
    // error_log("✅ Conexión al portal configurada: " . DB_PORTAL_NAME);
    
    // Cargar services y routes del portal desde subcarpetas
    foreach (glob(__DIR__ . '/services/portal/*.service.php') as $serviceFile) {
        require_once $serviceFile;
    }
    
    foreach (glob(__DIR__ . '/routes/portal/*.routes.php') as $routeFile) {
        require_once $routeFile;
    }
} else {
    error_log("ℹ️ Tenant sin BD portal configurada");
}

Flight::map('json', function($data, $code = 200, $encode = true) {
    convertirNumerosEnArray($data);
    $json = $encode ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;

    Flight::response()
        ->status($code)
        ->header('Content-Type', 'application/json; charset=utf-8')
        ->write($json)
        ->send();
});

// ===================================================================
// AUTENTICACION CENTRAL - exige token valido + tenant firmado en todas las
// rutas que no sean publicas (login). Las rutas que hacen exit(0) mas arriba
// (webhook de firma, /auth/pre-login, /auth/webauthn)
// no llegan hasta este punto.
// ===================================================================
Flight::before('start', function (&$params, &$output) {
    $metodo = Flight::request()->method;

    if ($metodo === 'OPTIONS') {
        return;
    }

    // Ruta sin query string ni slash final
    $ruta = Flight::request()->url;
    $posQuery = strpos($ruta, '?');
    if ($posQuery !== false) {
        $ruta = substr($ruta, 0, $posQuery);
    }
    $ruta = rtrim($ruta, '/');
    if ($ruta === '') {
        $ruta = '/';
    }

    // Rutas publicas: son el medio para OBTENER el token, no pueden exigirlo.
    $rutasPublicas = [
        '/',                        // healthcheck
        '/usuarios-auth',           // login usuario + clave
        '/webauthn/auth/opciones',  // login biometrico (con tenant) - paso 1
        '/webauthn/auth/verificar', // login biometrico (con tenant) - paso 2
        '/webauthn/disponible',     // verificacion previa al login
    ];

    if (in_array($ruta, $rutasPublicas, true)) {
        return;
    }

    // La descarga de documentos NO usa el token de sesion: se autentica con un
    // token efímero propio (?token=) que el handler valida contra el documento
    // y el tenant. Por eso se salta aqui la autenticacion de sesion. El prefijo
    // termina en '/download/', asi que NO afecta a '/download-token/', que si
    // exige sesion para emitir el token.
    if (strpos($ruta, '/documentos-personas/download/') === 0) {
        return;
    }

    // Servir imagenes de galeria: igual que la descarga de documentos, el
    // handler se autentica solo (token efímero por ?token= para <img src="">,
    // o token de sesion por header para la descarga blob).
    if (strpos($ruta, '/galeria-imagenes/servir/') === 0) {
        return;
    }

    // Resto: token valido y que el tenant del token coincida con el del request.
    $userData = JWTService::requerirTenant(TenantContext::codigo());

    // -----------------------------------------------------------------
    // HABEAS DATA - barrera real. El claim hd_ok viaja firmado dentro del
    // token, asi que no se puede falsificar desde el cliente y no cuesta
    // una consulta por request.
    //
    // Los tokens emitidos antes de este cambio no traen 'portal': se leen
    // como 'institucional' y no se bloquean. Nadie pierde la sesion en el
    // despliegue. Un usuario de padres con token viejo queda sin bloquear
    // hasta que expire (24h); el guard del front cubre ese caso.
    // -----------------------------------------------------------------
    $rutasHabeasData = [
        '/autorizaciones-habeas-data',
        '/autorizaciones-habeas-data/verificar',
        '/autorizaciones-habeas-data/plantilla',
    ];

    if (in_array($ruta, $rutasHabeasData, true)) {
        return;
    }

    // Rutas de bootstrap: la app las necesita para arrancar (nombre, logo,
    // NIT de la institucion). No son datos personales y corren antes del
    // login, asi que no deben exigir habeas data. Sin esto, una sesion a
    // medias o el atras/adelante del navegador rompen el arranque con un 403.
    $rutasBootstrap = [
        '/configuracion-global/multiples',
    ];

    if (in_array($ruta, $rutasBootstrap, true)) {
        return;
    }

    $portalToken = isset($userData->portal) ? $userData->portal : JWTService::PORTAL_INSTITUCIONAL;

    // Portales sujetos al bloqueo. Que un portal este aqui NO significa que
    // se exija: el backend solo bloquea si ese tenant tiene la politica
    // publicada y su flag habeas_data_activo_* encendido (ver seExige()).
    // Un tenant sin plantilla de colaboradores no bloquea a nadie.
    $portalesBloqueados = [
        JWTService::PORTAL_PADRES,
        JWTService::PORTAL_INSTITUCIONAL,
    ];

    if (in_array($portalToken, $portalesBloqueados, true)) {
        $hdOk = isset($userData->hd_ok) ? $userData->hd_ok : false;

        if ($hdOk !== true) {
            Flight::halt(403, json_encode([
                'error' => 'Debe aceptar la política de tratamiento de datos',
                'code'  => 'HABEAS_DATA_REQUIRED'
            ]));
            exit;
        }
    }
});

Flight::start();