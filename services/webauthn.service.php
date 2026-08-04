<?php
/**
 * Servicio WebAuthn para autenticación biométrica (huella digital, Face ID, Windows Hello)
 * Usa Web Authentication API estándar del W3C
 */
class WebAuthn
{
    private static $rpName = 'Genialisis';

    private static function getRpConfig()
    {
        $host = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        
        if (strpos($host, 'localhost') !== false) {
            $origin = rtrim($host, '/');
            if (empty($origin)) {
                $origin = 'http://localhost:4200';
            }
            return [
                'rpId' => 'localhost',
                'origin' => $origin
            ];
        }

        return [
            'rpId' => 'app.genialisis.com',
            'origin' => 'https://app.genialisis.com'
        ];
    }

    private static function getDbMaster()
    {
        static $db = null;
        if ($db === null) {
            require_once __DIR__ . '/../config/master.env.php';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            $db = new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, $options);
        }
        return $db;
    }

    private static function getTenantActual()
    {
        if (isset($_SERVER['HTTP_X_TENANT'])) {
            return $_SERVER['HTTP_X_TENANT'];
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-tenant') {
                    return $value;
                }
            }
        }
        return null;
    }

    /**
     * Conecta a una BD de tenant específico por su código
     */
    private static function getDbTenant($tenantCodigo)
    {
        $configFile = __DIR__ . "/../config/tenants/{$tenantCodigo}.env.php";
        if (!file_exists($configFile)) {
            return null;
        }

        // Guardar constantes actuales si existen
        $prevDSN = defined('DB_DSN') ? DB_DSN : null;

        // Cargar config del tenant
        $config = [];
        $content = file_get_contents($configFile);
        
        // Extraer valores de las constantes del archivo
        if (preg_match("/define\('DB_DSN',\s*'([^']+)'\)/", $content, $m)) $config['dsn'] = $m[1];
        if (preg_match("/define\('DB_USERNAME',\s*'([^']+)'\)/", $content, $m)) $config['user'] = $m[1];
        if (preg_match("/define\('DB_PASSWORD',\s*'([^']+)'\)/", $content, $m)) $config['pass'] = $m[1];

        if (empty($config['dsn'])) {
            return null;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
        ];

        return new PDO($config['dsn'], $config['user'], $config['pass'], $options);
    }

    private static function limpiarChallengesExpirados()
    {
        try {
            $db = self::getDbMaster();
            $db->exec("DELETE FROM webauthn_challenges WHERE expira < NOW()");
        } catch (Exception $e) {
            error_log("Error limpiando challenges expirados: " . $e->getMessage());
        }
    }

    private static function generarBytesAleatorios($length = 32)
    {
        $bytes = random_bytes($length);
        return self::base64urlEncode($bytes);
    }

    private static function base64urlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64urlDecode($data)
    {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // ================================================================
    // REGISTRO - Paso 1: Generar opciones para navigator.credentials.create()
    // ================================================================
    public static function generarOpcionesRegistro()
    {
        try {
            $userData = JWTService::requerirAutenticacion();

            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, usuario, correo_electronico FROM usuarios WHERE id = :id AND activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $userData->id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $usuario = $stmt->fetch();

            if (!$usuario) {
                Flight::json(['error' => true, 'message' => 'Usuario no encontrado'], 404);
                return;
            }

            $stmtCreds = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE id_usuario = :id_usuario AND activo = 1 AND id_tenant = :id_tenant");
            $stmtCreds->bindParam(':id_usuario', $usuario['id']);
            $stmtCreds->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtCreds->execute();
            $credsExistentes = $stmtCreds->fetchAll(PDO::FETCH_COLUMN);

            $excludeCredentials = [];
            foreach ($credsExistentes as $credId) {
                $excludeCredentials[] = [
                    'id' => $credId,
                    'type' => 'public-key',
                    'transports' => ['internal', 'hybrid']
                ];
            }

            $challenge = self::generarBytesAleatorios(32);
            $challengeId = bin2hex(random_bytes(16));

            self::limpiarChallengesExpirados();
            $tenant = self::getTenantActual();
            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("INSERT INTO webauthn_challenges (id, challenge, usuario, tipo, tenant_codigo, expira) VALUES (:id, :challenge, :usuario, 'registro', :tenant, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->bindParam(':challenge', $challenge);
            $stmtChallenge->bindParam(':usuario', $usuario['usuario']);
            $stmtChallenge->bindParam(':tenant', $tenant);
            $stmtChallenge->execute();

            $rpConfig = self::getRpConfig();

            $opciones = [
                'challengeId' => $challengeId,
                'publicKey' => [
                    'rp' => [
                        'name' => self::$rpName,
                        'id' => $rpConfig['rpId']
                    ],
                    'user' => [
                        'id' => self::base64urlEncode($usuario['usuario']),
                        'name' => $usuario['usuario'],
                        'displayName' => $usuario['usuario']
                    ],
                    'challenge' => $challenge,
                    'pubKeyCredParams' => [
                        ['alg' => -7, 'type' => 'public-key'],
                        ['alg' => -257, 'type' => 'public-key']
                    ],
                    'timeout' => 300000,
                    'excludeCredentials' => $excludeCredentials,
                    'authenticatorSelection' => [
                        'authenticatorAttachment' => 'platform',
                        'userVerification' => 'required',
                        'residentKey' => 'required',
                        'requireResidentKey' => true
                    ],
                    'attestation' => 'none'
                ]
            ];

            Flight::json($opciones);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::generarOpcionesRegistro: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // REGISTRO - Paso 2: Verificar y guardar la credencial
    // ================================================================
    public static function verificarRegistro()
    {
        try {
            $userData = JWTService::requerirAutenticacion();

            $challengeId = Flight::request()->data['challengeId'] ?? null;
            $credentialId = Flight::request()->data['credentialId'] ?? null;
            $publicKey = Flight::request()->data['publicKey'] ?? null;
            $clientDataJSON = Flight::request()->data['clientDataJSON'] ?? null;
            $dispositivo = Flight::request()->data['dispositivo'] ?? 'Dispositivo desconocido';

            if (!$challengeId || !$credentialId || !$publicKey || !$clientDataJSON) {
                Flight::json(['error' => true, 'message' => 'Datos incompletos'], 400);
                return;
            }

            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("SELECT challenge, usuario, tenant_codigo FROM webauthn_challenges WHERE id = :id AND tipo = 'registro' AND expira > NOW()");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->execute();
            $challengeData = $stmtChallenge->fetch();

            if (!$challengeData) {
                Flight::json(['error' => true, 'message' => 'Challenge expirado o inválido'], 401);
                return;
            }

            $stmtDelete = $dbMaster->prepare("DELETE FROM webauthn_challenges WHERE id = :id");
            $stmtDelete->bindParam(':id', $challengeId);
            $stmtDelete->execute();

            $clientData = json_decode(base64_decode(strtr($clientDataJSON, '-_', '+/')), true);
            if (!$clientData) {
                $clientData = json_decode(self::base64urlDecode($clientDataJSON), true);
            }

            if (!$clientData || $clientData['type'] !== 'webauthn.create') {
                Flight::json(['error' => true, 'message' => 'Tipo de operación inválido'], 400);
                return;
            }

            $challengeFromClient = $clientData['challenge'] ?? '';
            if ($challengeFromClient !== $challengeData['challenge']) {
                Flight::json(['error' => true, 'message' => 'Challenge no coincide'], 401);
                return;
            }

            // Guardar credencial en BD del tenant
            $db = Flight::db();
            $stmt = $db->prepare("INSERT INTO webauthn_credentials (id, id_tenant, id_usuario, credential_id, public_key, dispositivo) VALUES (:id, :id_tenant, :id_usuario, :credential_id, :public_key, :dispositivo)");
            $idWac = Uuid::generar();
            $stmt->bindValue(':id', $idWac);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_usuario', $userData->id);
            $stmt->bindParam(':credential_id', $credentialId);
            $stmt->bindParam(':public_key', $publicKey);
            $stmt->bindParam(':dispositivo', $dispositivo);
            $stmt->execute();

            // Guardar índice en BD master para login sin usuario
            $stmtMaster = $dbMaster->prepare("INSERT INTO webauthn_credentials_master (credential_id, usuario, tenant_codigo) VALUES (:credential_id, :usuario, :tenant_codigo)");
            $stmtMaster->bindParam(':credential_id', $credentialId);
            $stmtMaster->bindParam(':usuario', $challengeData['usuario']);
            $stmtMaster->bindParam(':tenant_codigo', $challengeData['tenant_codigo']);
            $stmtMaster->execute();

            Flight::json([
                'success' => true,
                'message' => 'Biométrico registrado correctamente'
            ]);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::verificarRegistro: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // AUTENTICACIÓN CON TENANT (requiere usuario + tenant ya configurado)
    // ================================================================
    public static function generarOpcionesAutenticacion()
    {
        try {
            $usuario = Flight::request()->data['usuario'] ?? null;

            if (empty($usuario)) {
                Flight::json(['error' => true, 'message' => 'Usuario es requerido'], 400);
                return;
            }

            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT wc.credential_id 
                FROM webauthn_credentials wc
                INNER JOIN usuarios u ON wc.id_usuario = u.id
                WHERE u.usuario = :usuario AND wc.activo = 1 AND u.activo = 1 AND wc.id_tenant = :id_tenant
            ");
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $credenciales = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($credenciales)) {
                Flight::json([
                    'disponible' => false,
                    'message' => 'No hay credenciales biométricas registradas'
                ]);
                return;
            }

            $allowCredentials = [];
            foreach ($credenciales as $credId) {
                $allowCredentials[] = [
                    'id' => $credId,
                    'type' => 'public-key',
                    'transports' => ['internal', 'hybrid']
                ];
            }

            $challenge = self::generarBytesAleatorios(32);
            $challengeId = bin2hex(random_bytes(16));

            self::limpiarChallengesExpirados();
            $tenant = self::getTenantActual();
            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("INSERT INTO webauthn_challenges (id, challenge, usuario, tipo, tenant_codigo, expira) VALUES (:id, :challenge, :usuario, 'autenticacion', :tenant, DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->bindParam(':challenge', $challenge);
            $stmtChallenge->bindParam(':usuario', $usuario);
            $stmtChallenge->bindParam(':tenant', $tenant);
            $stmtChallenge->execute();

            $rpConfig = self::getRpConfig();

            $opciones = [
                'disponible' => true,
                'challengeId' => $challengeId,
                'publicKey' => [
                    'challenge' => $challenge,
                    'rpId' => $rpConfig['rpId'],
                    'allowCredentials' => $allowCredentials,
                    'userVerification' => 'required',
                    'timeout' => 300000
                ]
            ];

            Flight::json($opciones);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::generarOpcionesAutenticacion: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // AUTENTICACIÓN CON TENANT - Paso 2: Verificar firma y hacer login
    // ================================================================
    public static function verificarAutenticacion()
    {
        try {
            $challengeId = Flight::request()->data['challengeId'] ?? null;
            $credentialId = Flight::request()->data['credentialId'] ?? null;
            $clientDataJSON = Flight::request()->data['clientDataJSON'] ?? null;
            $authenticatorData = Flight::request()->data['authenticatorData'] ?? null;
            $signature = Flight::request()->data['signature'] ?? null;

            if (!$challengeId || !$credentialId || !$clientDataJSON || !$authenticatorData || !$signature) {
                Flight::json(['error' => true, 'message' => 'Datos incompletos'], 400);
                return;
            }

            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("SELECT challenge, usuario FROM webauthn_challenges WHERE id = :id AND tipo = 'autenticacion' AND expira > NOW()");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->execute();
            $challengeData = $stmtChallenge->fetch();

            if (!$challengeData) {
                Flight::json(['error' => true, 'message' => 'Challenge expirado o inválido'], 401);
                return;
            }

            $stmtDelete = $dbMaster->prepare("DELETE FROM webauthn_challenges WHERE id = :id");
            $stmtDelete->bindParam(':id', $challengeId);
            $stmtDelete->execute();

            $clientData = json_decode(self::base64urlDecode($clientDataJSON), true);
            if (!$clientData || $clientData['type'] !== 'webauthn.get') {
                Flight::json(['error' => true, 'message' => 'Tipo de operación inválido'], 400);
                return;
            }

            if ($clientData['challenge'] !== $challengeData['challenge']) {
                Flight::json(['error' => true, 'message' => 'Challenge no coincide'], 401);
                return;
            }

            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT wc.id as cred_id, wc.public_key, wc.counter, wc.id_usuario
                FROM webauthn_credentials wc
                WHERE wc.credential_id = :credential_id AND wc.activo = 1
            ");
            $stmt->bindParam(':credential_id', $credentialId);
            $stmt->execute();
            $credencial = $stmt->fetch();

            if (!$credencial) {
                Flight::json(['error' => true, 'message' => 'Credencial no encontrada'], 401);
                return;
            }

            $authDataBin = self::base64urlDecode($authenticatorData);
            $clientDataHash = hash('sha256', self::base64urlDecode($clientDataJSON), true);
            $signedData = $authDataBin . $clientDataHash;
            $signatureBin = self::base64urlDecode($signature);
            $publicKeyPem = $credencial['public_key'];

            $verificado = openssl_verify($signedData, $signatureBin, $publicKeyPem, OPENSSL_ALGO_SHA256);

            if ($verificado !== 1) {
                Flight::json(['error' => true, 'message' => 'Firma biométrica inválida'], 401);
                return;
            }

            $nuevoCounter = $credencial['counter'] + 1;
            $stmtUpdate = $db->prepare("UPDATE webauthn_credentials SET counter = :counter, ultimo_uso = NOW() WHERE id = :id");
            $stmtUpdate->bindParam(':counter', $nuevoCounter);
            $stmtUpdate->bindParam(':id', $credencial['cred_id']);
            $stmtUpdate->execute();

            $response = self::obtenerDatosCompletoUsuario($db, $credencial['id_usuario'], TenantContext::codigo(), true);
            Flight::json($response);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::verificarAutenticacion: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // LOGIN SIN USUARIO - Paso 1: Generar challenge (ruta pública, sin tenant)
    // ================================================================
    public static function generarOpcionesLoginDirecto()
    {
        try {
            $challenge = self::generarBytesAleatorios(32);
            $challengeId = bin2hex(random_bytes(16));

            self::limpiarChallengesExpirados();
            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("INSERT INTO webauthn_challenges (id, challenge, usuario, tipo, tenant_codigo, expira) VALUES (:id, :challenge, '', 'autenticacion', '', DATE_ADD(NOW(), INTERVAL 5 MINUTE))");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->bindParam(':challenge', $challenge);
            $stmtChallenge->execute();

            $rpConfig = self::getRpConfig();

            $opciones = [
                'challengeId' => $challengeId,
                'publicKey' => [
                    'challenge' => $challenge,
                    'rpId' => $rpConfig['rpId'],
                    'allowCredentials' => [],
                    'userVerification' => 'required',
                    'timeout' => 300000
                ]
            ];

            Flight::json($opciones);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::generarOpcionesLoginDirecto: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // LOGIN SIN USUARIO - Paso 2: Verificar firma, resolver tenant, autenticar
    // ================================================================
    public static function verificarLoginDirecto()
    {
        try {
            $challengeId = Flight::request()->data['challengeId'] ?? null;
            $credentialId = Flight::request()->data['credentialId'] ?? null;
            $clientDataJSON = Flight::request()->data['clientDataJSON'] ?? null;
            $authenticatorData = Flight::request()->data['authenticatorData'] ?? null;
            $signature = Flight::request()->data['signature'] ?? null;

            if (!$challengeId || !$credentialId || !$clientDataJSON || !$authenticatorData || !$signature) {
                Flight::json(['error' => true, 'message' => 'Datos incompletos'], 400);
                return;
            }

            $dbMaster = self::getDbMaster();
            $stmtChallenge = $dbMaster->prepare("SELECT challenge FROM webauthn_challenges WHERE id = :id AND tipo = 'autenticacion' AND expira > NOW()");
            $stmtChallenge->bindParam(':id', $challengeId);
            $stmtChallenge->execute();
            $challengeData = $stmtChallenge->fetch();

            if (!$challengeData) {
                Flight::json(['error' => true, 'message' => 'Challenge expirado o inválido'], 401);
                return;
            }

            $stmtDelete = $dbMaster->prepare("DELETE FROM webauthn_challenges WHERE id = :id");
            $stmtDelete->bindParam(':id', $challengeId);
            $stmtDelete->execute();

            $clientData = json_decode(self::base64urlDecode($clientDataJSON), true);
            if (!$clientData || $clientData['type'] !== 'webauthn.get') {
                Flight::json(['error' => true, 'message' => 'Tipo de operación inválido'], 400);
                return;
            }

            if ($clientData['challenge'] !== $challengeData['challenge']) {
                Flight::json(['error' => true, 'message' => 'Challenge no coincide'], 401);
                return;
            }

            // Buscar en master a qué usuario y tenant pertenece esta credencial
            $stmtIndex = $dbMaster->prepare("SELECT usuario, tenant_codigo FROM webauthn_credentials_master WHERE credential_id = :credential_id");
            $stmtIndex->bindParam(':credential_id', $credentialId);
            $stmtIndex->execute();
            $indexData = $stmtIndex->fetch();

            if (!$indexData) {
                Flight::json(['error' => true, 'message' => 'Credencial no registrada'], 401);
                return;
            }

            // Conectar a la BD del tenant
            $dbTenant = self::getDbTenant($indexData['tenant_codigo']);
            if (!$dbTenant) {
                Flight::json(['error' => true, 'message' => 'Institución no encontrada'], 404);
                return;
            }

            // Buscar la credencial en el tenant
            $stmt = $dbTenant->prepare("
                SELECT wc.id as cred_id, wc.public_key, wc.counter, wc.id_usuario
                FROM webauthn_credentials wc
                WHERE wc.credential_id = :credential_id AND wc.activo = 1
            ");
            $stmt->bindParam(':credential_id', $credentialId);
            $stmt->execute();
            $credencial = $stmt->fetch();

            if (!$credencial) {
                Flight::json(['error' => true, 'message' => 'Credencial no encontrada en la institución'], 401);
                return;
            }

            // Verificar firma criptográfica
            $authDataBin = self::base64urlDecode($authenticatorData);
            $clientDataHash = hash('sha256', self::base64urlDecode($clientDataJSON), true);
            $signedData = $authDataBin . $clientDataHash;
            $signatureBin = self::base64urlDecode($signature);
            $publicKeyPem = $credencial['public_key'];

            $verificado = openssl_verify($signedData, $signatureBin, $publicKeyPem, OPENSSL_ALGO_SHA256);

            if ($verificado !== 1) {
                Flight::json(['error' => true, 'message' => 'Firma biométrica inválida'], 401);
                return;
            }

            // Actualizar contador y último uso
            $nuevoCounter = $credencial['counter'] + 1;
            $stmtUpdate = $dbTenant->prepare("UPDATE webauthn_credentials SET counter = :counter, ultimo_uso = NOW() WHERE id = :id");
            $stmtUpdate->bindParam(':counter', $nuevoCounter);
            $stmtUpdate->bindParam(':id', $credencial['cred_id']);
            $stmtUpdate->execute();

            // Obtener datos completos y JWT
            $response = self::obtenerDatosCompletoUsuario($dbTenant, $credencial['id_usuario'], $indexData['tenant_codigo']);

            if (empty($response)) {
                Flight::json(['error' => true, 'message' => 'Usuario no encontrado o inactivo'], 401);
                return;
            }

            // Agregar info del tenant para que el frontend sepa cuál configurar
            $stmtTenant = $dbMaster->prepare("SELECT id, codigo, nombre FROM tenants WHERE codigo = :codigo AND activo = 1");
            $stmtTenant->bindParam(':codigo', $indexData['tenant_codigo']);
            $stmtTenant->execute();
            $tenantInfo = $stmtTenant->fetch();

            Flight::json([
                'success' => true,
                'tenant' => $tenantInfo,
                'usuario' => $response
            ]);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::verificarLoginDirecto: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // Utilidad: Obtener datos completos + JWT de un usuario
    // ================================================================
    /**
     * @param string|null $portal Portal desde el que se autentica. Si es null se
     *                            lee del body; un valor desconocido cae a
     *                            'institucional' (no bloqueado) via normalizarPortal.
     * @param bool $conContextoTenant true cuando TenantContext esta cargado y por
     *                            tanto se puede consultar el estado de habeas data.
     */
    private static function obtenerDatosCompletoUsuario($db, $idUsuario, $tenantCodigo = null, $conContextoTenant = false)
    {
        $stmtUser = $db->prepare("
            SELECT 
                u.id, u.id_persona, u.usuario, u.correo_electronico, 
                p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                p.id_tipo_identificacion, ti.nombre tipo_identificacion,
                p.numero_identificacion, p.fecha_nacimiento, p.id_genero, 
                g.nombre nombre_genero, p.direccion, u.activo, 
                u.acceso_institucional, u.acceso_chat_wa, u.acceso_portal_padres, u.super_admin,
                c.sobrenombre, c.id id_colaborador, c.valida_ingreso_jornada, c.valida_ingreso_descanso
            FROM usuarios u 
            INNER JOIN personas p ON u.id_persona = p.id
            LEFT JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            LEFT JOIN generos g ON p.id_genero = g.id
            LEFT JOIN colaboradores c ON p.id = c.id_persona
            WHERE u.id = :id AND u.activo = 1
        ");
        $stmtUser->bindParam(':id', $idUsuario);
        $stmtUser->execute();
        $response = $stmtUser->fetchAll();

        if (!empty($response) && isset($response[0])) {
            if ($response[0]['super_admin'] == 1) {
                $permisos = ['*'];
            } else {
                $permisos = self::obtenerPermisosUsuarioFromDb($db, $response[0]['id']);
            }
            $portal = JWTService::normalizarPortal(Flight::request()->data['portal'] ?? null);

            // hd_ok solo se puede calcular con TenantContext cargado (la ruta
            // /auth/webauthn corre sin tenant, ver index.php). Sin contexto no
            // se emite el claim y el backend no bloquea: ese camino queda
            // cubierto por la verificacion que el front hace al entrar.
            $extra = ['portal' => $portal];
            if ($conContextoTenant) {
                $extra['hd_ok'] = AutorizacionesHabeasData::estaAutorizado($response[0]['id'], $portal);
                $extra['hd_v'] = AutorizacionesHabeasData::versionAceptada($response[0]['id'], $portal);
            }

            $token = JWTService::generarToken($response[0], $permisos, $tenantCodigo, $extra);
            $response[0]['token'] = $token;
            $response[0]['permisos'] = $permisos;
        }

        return $response;
    }

    private static function obtenerPermisosUsuarioFromDb($db, $idUsuario)
    {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT pxr.codigo_permiso
                FROM permisos_x_rol pxr
                INNER JOIN roles_x_usuario rxu ON pxr.id_rol = rxu.id_rol
                WHERE rxu.id_usuario = :id_usuario
                ORDER BY pxr.codigo_permiso
            ");
            $stmt->bindParam(':id_usuario', $idUsuario);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            error_log("Error obteniendo permisos: " . $e->getMessage());
            return [];
        }
    }

    // ================================================================
    // Verificar si un usuario tiene biométrico registrado
    // ================================================================
    public static function verificarDisponibilidad()
    {
        try {
            $usuario = Flight::request()->data['usuario'] ?? null;

            if (empty($usuario)) {
                Flight::json(['error' => true, 'message' => 'Usuario es requerido'], 400);
                return;
            }

            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM webauthn_credentials wc
                INNER JOIN usuarios u ON wc.id_usuario = u.id
                WHERE u.usuario = :usuario AND wc.activo = 1 AND u.activo = 1 AND wc.id_tenant = :id_tenant
            ");
            $stmt->bindParam(':usuario', $usuario);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch();

            Flight::json([
                'disponible' => ($resultado['total'] > 0),
                'cantidad' => (int)$resultado['total']
            ]);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::verificarDisponibilidad: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // Listar credenciales del usuario autenticado
    // ================================================================
    public static function listarCredenciales()
    {
        try {
            $userData = JWTService::requerirAutenticacion();

            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT id, dispositivo, fecha_registro, ultimo_uso, activo
                FROM webauthn_credentials
                WHERE id_usuario = :id_usuario AND id_tenant = :id_tenant
                ORDER BY fecha_registro DESC
            ");
            $stmt->bindParam(':id_usuario', $userData->id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $credenciales = $stmt->fetchAll();

            Flight::json($credenciales);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::listarCredenciales: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    // ================================================================
    // Eliminar una credencial
    // ================================================================
    public static function eliminarCredencial()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => true, 'message' => 'ID de credencial es requerido'], 400);
                return;
            }

            $db = Flight::db();

            // Obtener credential_id antes de eliminar para limpiar master
            $stmtGet = $db->prepare("SELECT credential_id FROM webauthn_credentials WHERE id = :id AND id_usuario = :id_usuario AND id_tenant = :id_tenant");
            $stmtGet->bindParam(':id', $id);
            $stmtGet->bindParam(':id_usuario', $userData->id);
            $stmtGet->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtGet->execute();
            $cred = $stmtGet->fetch();

            if (!$cred) {
                Flight::json(['error' => true, 'message' => 'Credencial no encontrada'], 404);
                return;
            }

            // Eliminar del tenant
            $stmt = $db->prepare("DELETE FROM webauthn_credentials WHERE id = :id AND id_usuario = :id_usuario AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':id_usuario', $userData->id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            // Eliminar del master
            $dbMaster = self::getDbMaster();
            $stmtMaster = $dbMaster->prepare("DELETE FROM webauthn_credentials_master WHERE credential_id = :credential_id");
            $stmtMaster->bindParam(':credential_id', $cred['credential_id']);
            $stmtMaster->execute();

            Flight::json(['success' => true, 'message' => 'Credencial eliminada correctamente']);

        } catch (Exception $e) {
            error_log("Error en WebAuthn::eliminarCredencial: " . $e->getMessage());
            Flight::json(['error' => true, 'message' => 'Error interno del servidor'], 500);
        }
    }

    /**
     * Obtiene permisos del usuario (para rutas con Flight::db())
     */
    private static function obtenerPermisosUsuario($idUsuario)
    {
        return self::obtenerPermisosUsuarioFromDb(Flight::db(), $idUsuario);
    }
}