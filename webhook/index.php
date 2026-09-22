<?php
/*=============================================
WEBHOOK PARA WHATSAPP BUSINESS API - MULTI-TENANT
Con descarga automática de multimedia
=============================================*/

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webhook_errors.log');
date_default_timezone_set('America/Bogota');

// Cargar autoload de Composer (para web-push)
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

// Carpeta de configuracion segun entorno (define CONFIG_DIR)
require_once dirname(__DIR__) . '/config-path.php';

// Responder rápido a Meta (< 5 segundos)
ignore_user_abort(true);

// =============================================
// VERIFICACIÓN DEL WEBHOOK (GET)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_verify_token'])) {
    
    require_once CONFIG_DIR . '/master.env.php';
    
    $dbMaster = conectarBD(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD);
    
    $stmt = $dbMaster->prepare("SELECT verify_token FROM tenants_whatsapp_config WHERE activo = 1 LIMIT 1");
    $stmt->execute();
    $config = $stmt->fetch();
    
    if ($config && $_GET['hub_verify_token'] === $config['verify_token']) {
        echo $_GET['hub_challenge'];
    } else {
        http_response_code(403);
        echo 'Token invalido';
    }
    exit;
}

// =============================================
// RECEPCIÓN DE MENSAJES (POST)
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input = file_get_contents('php://input');
    
    // Responder 200 inmediatamente a Meta
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }
    
    // Procesar en background
    try {
        $webhook = json_decode($input, true);
        
        if (!isset($webhook['entry'][0]['changes'][0]['value'])) {
            exit;
        }
        
        $value = $webhook['entry'][0]['changes'][0]['value'];
        
        // Extraer phone_number_id del payload
        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        
        if (!$phoneNumberId) {
            logError('phone_number_id no encontrado en payload');
            exit;
        }
        
        // Cargar BD maestra
        require_once CONFIG_DIR . '/master.env.php';
        $dbMaster = conectarBD(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD);
        
        // Resolver tenant
        $config = resolverTenant($dbMaster, $phoneNumberId);
        
        if (!$config) {
            logError("No se encontró tenant para phone_number_id: {$phoneNumberId}");
            exit;
        }
        
        // Verificar firma HMAC
        if (!empty($config['app_secret'])) {
            $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
            if (!verificarFirma($input, $config['app_secret'], $signature)) {
                logError('Firma HMAC inválida');
                exit;
            }
        }
        
        // Cargar config del tenant y conectar a su BD
        $tenantConfig = CONFIG_DIR . "/tenants/{$config['codigo']}.env.php";
        
        if (!file_exists($tenantConfig)) {
            logError("Config no encontrada para tenant: {$config['codigo']}");
            exit;
        }
        
        require_once $tenantConfig;
        $dbTenant = conectarBD(DB_DSN, DB_USERNAME, DB_PASSWORD);
        
        // Procesar según tipo de evento
        if (isset($value['messages'])) {
            procesarMensajeEntrante($dbTenant, $value, $config);

            // Enviar push notification a los usuarios suscritos
            enviarPushNotification($dbTenant, $value);
        }
        
        if (isset($value['statuses'])) {
            procesarEstadoMensaje($dbTenant, $value['statuses'][0]);
        }
        
    } catch (Exception $e) {
        logError('Error webhook: ' . $e->getMessage());
    }
    
    exit;
}

// Si no es GET ni POST
http_response_code(405);
exit;

// =============================================
// FUNCIONES DE CONEXIÓN Y SEGURIDAD
// =============================================

function conectarBD($dsn, $user, $pass) {
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '-05:00';",
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

function resolverTenant($dbMaster, $phoneNumberId) {
    $stmt = $dbMaster->prepare("
        SELECT twc.*, t.codigo 
        FROM tenants_whatsapp_config twc
        INNER JOIN tenants t ON twc.id_tenant = t.id
        WHERE twc.phone_number_id = :phone_number_id 
        AND twc.activo = 1
        LIMIT 1
    ");
    $stmt->execute(['phone_number_id' => $phoneNumberId]);
    return $stmt->fetch();
}

function verificarFirma($payload, $appSecret, $signature) {
    if (empty($signature)) return false;
    $expected = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);
    return hash_equals($expected, $signature);
}

// =============================================
// PROCESAMIENTO DE MENSAJES
// =============================================

function procesarMensajeEntrante($db, $value, $config) {
    $message = $value['messages'][0];
    $contact = $value['contacts'][0] ?? [];
    
    $numeroTelefono = $message['from'];
    $nombreWhatsapp = $contact['profile']['name'] ?? null;
    $idMensajeWa = $message['id'];
    $timestampWa = $message['timestamp'];
    
    // 1. Buscar o crear contacto
    $idContacto = buscarOCrearContacto($db, $numeroTelefono, $nombreWhatsapp);
    
    if (!$idContacto) {
        logError("Error creando contacto para: {$numeroTelefono}");
        return;
    }
    
    // 2. Buscar o crear conversación activa (ÚNICA por contacto)
    $idConversacion = buscarOCrearConversacion($db, $idContacto);
    
    if (!$idConversacion) {
        logError("Error creando conversación para contacto: {$idContacto}");
        return;
    }
    
    // 3. Actualizar ventana de 24h (cada mensaje entrante la reinicia)
    actualizarVentana24h($db, $idConversacion);
    
    // 4. Procesar contenido del mensaje
    $contenido = procesarContenido($message);
    
    // 5. Verificar que el mensaje no exista ya (idempotencia)
    $stmt = $db->prepare("SELECT id FROM wa_mensajes WHERE id_mensaje_wa = :id_mensaje_wa LIMIT 1");
    $stmt->execute(['id_mensaje_wa' => $idMensajeWa]);
    if ($stmt->fetch()) {
        return;
    }
    
    // 6. Guardar mensaje
    $stmt = $db->prepare("
        INSERT INTO wa_mensajes (
            id_conversacion, id_mensaje_wa, direccion, canal, tipo,
            contenido, id_multimedia, tipo_mime_multimedia,
            nombre_archivo, timestamp_wa, estado, respondido
        ) VALUES (
            :id_conversacion, :id_mensaje_wa, 'entrada', 'whatsapp', :tipo,
            :contenido, :id_multimedia, :tipo_mime,
            :nombre_archivo, :timestamp_wa, 'recibido', 0
        )
    ");
    
    $stmt->execute([
        'id_conversacion' => $idConversacion,
        'id_mensaje_wa' => $idMensajeWa,
        'tipo' => $contenido['tipo'],
        'contenido' => $contenido['contenido'],
        'id_multimedia' => $contenido['id_multimedia'] ?? null,
        'tipo_mime' => $contenido['tipo_mime'] ?? null,
        'nombre_archivo' => $contenido['nombre_archivo'] ?? null,
        'timestamp_wa' => $timestampWa
    ]);
    
    $idMensajeLocal = $db->lastInsertId();
    
    logError("Mensaje recibido de {$numeroTelefono} - ID: {$idMensajeWa} - Tipo: {$contenido['tipo']}");
    
    // 7. Si tiene multimedia, descargar el archivo
    if (!empty($contenido['id_multimedia'])) {
        descargarMultimedia($db, $idMensajeLocal, $contenido, $config, $idConversacion);
    }
}

// =============================================
// VENTANA DE 24H
// =============================================

function actualizarVentana24h($db, $idConversacion) {
    $stmt = $db->prepare("
        UPDATE wa_conversaciones 
        SET ventana_wa_inicio = NOW(), 
            ventana_wa_fin = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE id = :id
    ");
    $stmt->execute(['id' => $idConversacion]);
}

// =============================================
// DESCARGA DE MULTIMEDIA
// =============================================

function descargarMultimedia($db, $idMensaje, $contenido, $config, $idConversacion) {
    try {
        $mediaId = $contenido['id_multimedia'];
        $tipoMime = $contenido['tipo_mime'] ?? '';
        $nombreArchivo = $contenido['nombre_archivo'] ?? null;
        
        logError("Descargando multimedia: {$mediaId} (mime: {$tipoMime})");
        
        // Paso 1: Obtener URL temporal del archivo desde Graph API
        $urlInfo = "https://graph.facebook.com/{$config['api_version']}/{$mediaId}";
        
        $ch = curl_init($urlInfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            logError("Error obteniendo URL de multimedia (HTTP {$httpCode}): {$response}");
            return;
        }
        
        $mediaInfo = json_decode($response, true);
        $downloadUrl = $mediaInfo['url'] ?? null;
        
        if (!$downloadUrl) {
            logError("URL de descarga no encontrada en respuesta de Graph API");
            return;
        }
        
        // Paso 2: Descargar el archivo binario
        $ch = curl_init($downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $config['access_token']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $fileContent = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || empty($fileContent)) {
            logError("Error descargando archivo (HTTP {$httpCode})");
            return;
        }
        
        logError("Archivo descargado: " . strlen($fileContent) . " bytes");
        
        // Paso 3: Determinar extensión y nombre del archivo
        $extension = obtenerExtension($tipoMime, $nombreArchivo);
        
        if (!$nombreArchivo) {
            $prefijos = [
                'imagen' => 'IMG',
                'audio'  => 'AUD',
                'video'  => 'VID',
            ];
            $prefijo = $prefijos[$contenido['tipo']] ?? 'FILE';
            $nombreArchivo = $prefijo . '_' . date('Ymd_His') . '.' . $extension;
        }
        
        // Paso 4: Crear carpeta y guardar archivo
        $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : dirname(__DIR__) . '/uploads';
        $codigoTenant = $config['codigo'];
        $carpetaMedia = "{$uploadPath}/{$codigoTenant}/wa-media/{$idConversacion}";
        
        if (!is_dir($carpetaMedia)) {
            mkdir($carpetaMedia, 0755, true);
        }
        
        // Nombre único para evitar colisiones
        $nombreUnico = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $nombreArchivo);
        $rutaCompleta = "{$carpetaMedia}/{$nombreUnico}";
        
        $bytesWritten = file_put_contents($rutaCompleta, $fileContent);
        
        if ($bytesWritten > 0) {
            // Paso 5: Actualizar BD con la ruta relativa
            $rutaRelativa = "{$codigoTenant}/wa-media/{$idConversacion}/{$nombreUnico}";
            
            $stmt = $db->prepare("
                UPDATE wa_mensajes 
                SET url_multimedia = :url_multimedia,
                    nombre_archivo = :nombre_archivo
                WHERE id = :id
            ");
            $stmt->execute([
                'url_multimedia' => $rutaRelativa,
                'nombre_archivo' => $nombreArchivo,
                'id' => $idMensaje
            ]);
            
            logError("Multimedia guardada: {$rutaRelativa} ({$bytesWritten} bytes)");
        } else {
            logError("Error guardando archivo en: {$rutaCompleta}");
        }
        
    } catch (Exception $e) {
        logError("Error descargando multimedia: " . $e->getMessage());
    }
}

function obtenerExtension($tipoMime, $nombreArchivo = null) {
    if ($nombreArchivo) {
        $ext = pathinfo($nombreArchivo, PATHINFO_EXTENSION);
        if ($ext) return strtolower($ext);
    }
    
    $mimeMap = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/gif'        => 'gif',
        'image/webp'       => 'webp',
        'video/mp4'        => 'mp4',
        'video/3gpp'       => '3gp',
        'audio/aac'        => 'aac',
        'audio/ogg'        => 'ogg',
        'audio/mpeg'       => 'mp3',
        'audio/amr'        => 'amr',
        'audio/opus'       => 'opus',
        'application/pdf'  => 'pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'application/msword'       => 'doc',
        'application/vnd.ms-excel' => 'xls',
        'text/plain'               => 'txt',
    ];
    
    if (isset($mimeMap[$tipoMime])) {
        return $mimeMap[$tipoMime];
    }
    
    $tipoBase = trim(explode(';', $tipoMime)[0]);
    if (isset($mimeMap[$tipoBase])) {
        return $mimeMap[$tipoBase];
    }
    
    return 'bin';
}

// =============================================
// FUNCIONES DE CONTACTOS Y CONVERSACIONES
// =============================================

function buscarOCrearContacto($db, $numero, $nombre) {
    $stmt = $db->prepare("SELECT id FROM wa_contactos WHERE numero_telefono = :numero LIMIT 1");
    $stmt->execute(['numero' => $numero]);
    $contacto = $stmt->fetch();
    
    if ($contacto) {
        if ($nombre) {
            $stmt = $db->prepare("UPDATE wa_contactos SET nombre_whatsapp = :nombre WHERE id = :id");
            $stmt->execute(['nombre' => $nombre, 'id' => $contacto['id']]);
        }
        return $contacto['id'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO wa_contactos (numero_telefono, nombre_whatsapp, fecha_primera_interaccion)
        VALUES (:numero, :nombre, NOW())
    ");
    $stmt->execute(['numero' => $numero, 'nombre' => $nombre]);
    
    $idContacto = $db->lastInsertId();
    vincularConPersona($db, $idContacto, $numero);
    
    return $idContacto;
}

function vincularConPersona($db, $idContacto, $numero) {
    $numeroLimpio = preg_replace('/[^0-9]/', '', $numero);
    $ultimos10 = substr($numeroLimpio, -10);
    
    try {
        $stmt = $db->prepare("
            SELECT p.id
            FROM personas p
            INNER JOIN acudientes a ON a.id_persona = p.id
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(p.telefono, '+', ''), ' ', ''), '-', ''), '(', '') LIKE CONCAT('%', :ultimos10)
            AND a.activo = 1
            LIMIT 1
        ");
        $stmt->execute(['ultimos10' => $ultimos10]);
        $persona = $stmt->fetch();
        
        if ($persona) {
            $stmt = $db->prepare("UPDATE wa_contactos SET id_persona = :id_persona WHERE id = :id");
            $stmt->execute(['id_persona' => $persona['id'], 'id' => $idContacto]);
        }
    } catch (Exception $e) {
        logError("Error vinculando persona: " . $e->getMessage());
    }
}

/**
 * Conversación ÚNICA por contacto.
 * Ya no crea nuevas conversaciones por ventana de 24h.
 */
function buscarOCrearConversacion($db, $idContacto) {
    $stmt = $db->prepare("
        SELECT id FROM wa_conversaciones
        WHERE id_contacto = :id_contacto AND activa = 1
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute(['id_contacto' => $idContacto]);
    $conv = $stmt->fetch();
    
    if ($conv) {
        return $conv['id'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO wa_conversaciones (id_contacto, activa, fecha_creacion)
        VALUES (:id_contacto, 1, NOW())
    ");
    $stmt->execute(['id_contacto' => $idContacto]);
    
    return $db->lastInsertId();
}

function procesarContenido($message) {
    $resultado = [
        'tipo' => 'texto',
        'contenido' => '',
        'id_multimedia' => null,
        'tipo_mime' => null,
        'nombre_archivo' => null
    ];
    
    if (isset($message['text'])) {
        $resultado['contenido'] = $message['text']['body'];
    } elseif (isset($message['image'])) {
        $resultado['tipo'] = 'imagen';
        $resultado['contenido'] = $message['image']['caption'] ?? 'Imagen';
        $resultado['id_multimedia'] = $message['image']['id'];
        $resultado['tipo_mime'] = $message['image']['mime_type'];
    } elseif (isset($message['audio'])) {
        $resultado['tipo'] = 'audio';
        $resultado['contenido'] = 'Nota de voz';
        $resultado['id_multimedia'] = $message['audio']['id'];
        $resultado['tipo_mime'] = $message['audio']['mime_type'];
    } elseif (isset($message['video'])) {
        $resultado['tipo'] = 'video';
        $resultado['contenido'] = $message['video']['caption'] ?? 'Video';
        $resultado['id_multimedia'] = $message['video']['id'];
        $resultado['tipo_mime'] = $message['video']['mime_type'];
    } elseif (isset($message['document'])) {
        $resultado['tipo'] = 'documento';
        $resultado['contenido'] = $message['document']['filename'] ?? 'Documento';
        $resultado['id_multimedia'] = $message['document']['id'];
        $resultado['tipo_mime'] = $message['document']['mime_type'];
        $resultado['nombre_archivo'] = $message['document']['filename'] ?? null;
    }
    
    return $resultado;
}

function procesarEstadoMensaje($db, $status) {
    $estados = [
        'sent' => 'enviado',
        'delivered' => 'entregado',
        'read' => 'leido',
        'failed' => 'fallido'
    ];
    
    $estado = $estados[$status['status']] ?? null;
    if (!$estado) return;
    
    $idMensajeWa = $status['id'];
    
    $stmt = $db->prepare("UPDATE wa_mensajes SET estado = :estado WHERE id_mensaje_wa = :id_mensaje_wa");
    $stmt->execute(['estado' => $estado, 'id_mensaje_wa' => $idMensajeWa]);

    // Si Meta envía datos de la conversación (ventana), actualizar
    if (isset($status['conversation']['expiration_timestamp'])) {
        $expTimestamp = $status['conversation']['expiration_timestamp'];
        $idMensajeWa = $status['id'];
        
        // Buscar la conversación asociada a este mensaje
        $stmt = $db->prepare("
            SELECT wm.id_conversacion 
            FROM wa_mensajes wm 
            WHERE wm.id_mensaje_wa = :id_mensaje_wa 
            LIMIT 1
        ");
        $stmt->execute(['id_mensaje_wa' => $idMensajeWa]);
        $msg = $stmt->fetch();
        
        if ($msg) {
            $stmt = $db->prepare("
                UPDATE wa_conversaciones 
                SET ventana_wa_fin = FROM_UNIXTIME(:exp_timestamp)
                WHERE id = :id
            ");
            $stmt->execute([
                'exp_timestamp' => $expTimestamp,
                'id' => $msg['id_conversacion']
            ]);
        }
    }
}

// =============================================
// PUSH NOTIFICATIONS
// =============================================

function enviarPushNotification($db, $value) {
    try {
        if (!class_exists('Minishlink\WebPush\WebPush')) {
            return;
        }

        require_once dirname(__DIR__) . '/services/push-notification.service.php';

        $pushService = new PushNotificationService($db);

        $contact = $value['contacts'][0] ?? [];
        $message = $value['messages'][0] ?? [];
        $nombreContacto = $contact['profile']['name'] ?? $message['from'] ?? 'WhatsApp';

        $tipo = 'texto';
        $preview = '';

        if (isset($message['text'])) {
            $preview = $message['text']['body'];
            if (mb_strlen($preview) > 80) {
                $preview = mb_substr($preview, 0, 80) . '...';
            }
        } elseif (isset($message['image'])) {
            $tipo = 'imagen';
            $preview = '📷 Imagen';
        } elseif (isset($message['audio'])) {
            $tipo = 'audio';
            $preview = '🎵 Nota de voz';
        } elseif (isset($message['video'])) {
            $tipo = 'video';
            $preview = '🎬 Video';
        } elseif (isset($message['document'])) {
            $tipo = 'documento';
            $preview = '📄 ' . ($message['document']['filename'] ?? 'Documento');
        } else {
            $preview = 'Nuevo mensaje';
        }

        $numeroTelefono = $message['from'] ?? '';
        $idConversacion = null;

        if ($numeroTelefono) {
            $stmt = $db->prepare("
                SELECT wc.id
                FROM wa_conversaciones wc
                INNER JOIN wa_contactos wco ON wco.id = wc.id_contacto
                WHERE wco.numero_telefono = :numero
                AND wc.activa = 1
                ORDER BY wc.id DESC LIMIT 1
            ");
            $stmt->execute(['numero' => $numeroTelefono]);
            $conv = $stmt->fetch();
            $idConversacion = $conv['id'] ?? null;
        }

        $pushService->notificarATodos(
            $nombreContacto,
            $preview,
            [
                'id_conversacion' => $idConversacion,
                'url' => '/#/menu',
                'tipo' => $tipo,
            ]
        );

    } catch (Exception $e) {
        logError('Error enviando push: ' . $e->getMessage());
    }
}

function logError($msg) {
    error_log(date('[Y-m-d H:i:s] ') . $msg);
}