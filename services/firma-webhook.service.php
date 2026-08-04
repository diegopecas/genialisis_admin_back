<?php
/**
 * Servicio para procesar webhooks de Firma.dev
 * Este endpoint NO requiere header X-Tenant
 */
class FirmaWebhook
{
    public static function procesar()
    {
        try {
            // Leer payload
            $payload = file_get_contents('php://input');
            
            // DEBUG: Log del payload crudo
            error_log("===================================================");
            error_log("RAW PAYLOAD RECIBIDO:");
            error_log($payload ?: '(vacio)');
            error_log("===================================================");
            
            $data = json_decode($payload, true);
            
            if (!$data) {
                error_log("Webhook Firma: Payload invalido o vacio");
                Flight::json(['error' => 'Payload invalido'], 400);
                return;
            }
            
            // Firma.dev usa 'id' y 'type' (no 'event_id' y 'event_type')
            $eventId = $data['id'] ?? $data['event_id'] ?? null;
            $eventType = $data['type'] ?? $data['event_type'] ?? null;
            $signingRequestId = $data['data']['signing_request']['id'] ?? null;
            
            error_log("===================================================");
            error_log("WEBHOOK FIRMA.DEV RECIBIDO");
            error_log("   Event ID: " . $eventId);
            error_log("   Event Type: " . $eventType);
            error_log("   Signing Request ID: " . $signingRequestId);
            error_log("===================================================");
            
            // Obtener tenant desde workspace_id usando archivo de mapeo
            $workspaceId = $data['workspace_id'] ?? $data['data']['workspace']['id'] ?? null;
            $workspaceName = $data['data']['workspace']['name'] ?? null;
            
            error_log("   Workspace ID: " . ($workspaceId ?? 'NULL'));
            error_log("   Workspace name: " . ($workspaceName ?? 'NULL'));
            
            // Cargar mapeo de workspaces (JSON)
            $workspaceMapFile = __DIR__ . "/../config/firma_workspaces.json";
            if (!file_exists($workspaceMapFile)) {
                error_log("Archivo de mapeo no encontrado: " . $workspaceMapFile);
                Flight::json(['error' => 'Configuracion de workspaces no encontrada'], 500);
                return;
            }
            
            $workspaceMap = json_decode(file_get_contents($workspaceMapFile), true);
            $tenantCode = $workspaceMap[$workspaceId] ?? null;
            
            error_log("   Tenant mapeado: " . ($tenantCode ?? 'NULL'));
            
            // Validar que tenemos tenant
            if (!$tenantCode) {
                error_log("Workspace ID no mapeado: " . $workspaceId);
                Flight::json(['received' => true, 'warning' => 'workspace no mapeado a tenant']);
                return;
            }
            
            // Validar tenant_code y cargar config
            $tenantCode = preg_replace('/[^a-z0-9\-_]/i', '', $tenantCode);
            $configFile = __DIR__ . "/../config/tenants/{$tenantCode}.env.php";
            
            if (!file_exists($configFile)) {
                error_log("Config no encontrada para tenant: " . $tenantCode . " (archivo: " . $configFile . ")");
                Flight::json(['error' => 'Tenant no valido'], 400);
                return;
            }
            
            // Cargar configuracion del tenant
            require_once $configFile;
            
            // Conectar a BD del tenant
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            $db = new PDO(DB_DSN, DB_USERNAME, DB_PASSWORD, $options);
            
            // Buscar documento por signing_request_id en la BD del tenant
            $stmt = $db->prepare("SELECT id FROM documentos_personas WHERE firma_digital_id = :firma_id AND id_tenant = :id_tenant LIMIT 1");
            $stmt->bindParam(':firma_id', $signingRequestId);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $doc = $stmt->fetch();
            
            if (!$doc) {
                error_log("Documento no encontrado con firma_digital_id: " . $signingRequestId);
                Flight::json(['received' => true, 'warning' => 'documento no encontrado en BD']);
                return;
            }
            
            $idDocumento = $doc['id'];
            error_log("   Documento encontrado: ID " . $idDocumento);
            
            // Procesar segun tipo de evento
            switch ($eventType) {
                case 'signing_request.completed':
                    self::procesarFirmaCompletada($db, $idDocumento, $data);
                    break;
                    
                case 'signing_request.signed':
                    self::procesarFirmanteFirmo($db, $idDocumento, $data);
                    break;
                    
                case 'signing_request.cancelled':
                case 'signing_request.expired':
                    self::procesarFirmaCancelada($db, $idDocumento, $data, $eventType);
                    break;
                    
                case 'signing_request.created':
                case 'signing_request.sent':
                    error_log("Evento informativo: " . $eventType);
                    break;
                    
                default:
                    error_log("Evento no procesado: " . $eventType);
            }
            
            Flight::json(['received' => true, 'processed' => $eventType]);
            
        } catch (Exception $e) {
            error_log("Error procesando webhook: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    
    private static function procesarFirmaCompletada($db, $idDocumento, $data)
    {
        error_log("Procesando firma completada para documento: " . $idDocumento);
        
        // Obtener URL de descarga del signing_request
        $finalDocumentUrl = $data['data']['signing_request']['final_document_download_url'] 
            ?? $data['data']['final_document_download_url'] 
            ?? null;
        
        // Actualizar estado
        $stmt = $db->prepare("
            UPDATE documentos_personas 
            SET firma_digital_estado = 'firmado',
                fecha_firmado = NOW()
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $stmt->bindParam(':id', $idDocumento);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        
        // Si hay URL de descarga, descargar y reemplazar archivo
        if ($finalDocumentUrl) {
            self::descargarYReemplazarPDF($db, $idDocumento, $finalDocumentUrl);
        } else {
            error_log("No hay URL de descarga en el webhook, se descargara al consultar estado");
        }
        
        error_log("Documento {$idDocumento} marcado como firmado");
    }
    
    private static function procesarFirmanteFirmo($db, $idDocumento, $data)
    {
        error_log("Firmante firmo documento: " . $idDocumento);
        // Solo log por ahora, el estado final se actualiza en completed
    }
    
    private static function procesarFirmaCancelada($db, $idDocumento, $data, $eventType)
    {
        $estado = ($eventType === 'signing_request.expired') ? 'expirado' : 'cancelado';
        error_log("Firma {$estado} para documento: " . $idDocumento);
        
        $stmt = $db->prepare("
            UPDATE documentos_personas 
            SET firma_digital_estado = :estado
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $stmt->bindParam(':estado', $estado);
        $stmt->bindParam(':id', $idDocumento);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
    }
    
    private static function descargarYReemplazarPDF($db, $idDocumento, $url)
    {
        try {
            // Obtener info del documento
            $stmt = $db->prepare("SELECT ruta_archivo, nombre_archivo FROM documentos_personas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $idDocumento);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $documento = $stmt->fetch();
            
            if (!$documento) {
                error_log("Documento no encontrado: " . $idDocumento);
                return;
            }
            
            // Verificar si ya esta descargado
            if (strpos($documento['nombre_archivo'], '_firmado') !== false) {
                error_log("Documento ya tiene sufijo _firmado");
                return;
            }
            
            // Descargar PDF
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || empty($pdfContent)) {
                error_log("Error descargando PDF: HTTP " . $httpCode);
                return;
            }
            
            error_log("PDF descargado: " . strlen($pdfContent) . " bytes");
            
            // Construir rutas
            $uploadPath = defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads';
            $rutaOriginal = $uploadPath . '/' . $documento['ruta_archivo'];
            $carpeta = dirname($rutaOriginal);
            
            $nombreOriginal = $documento['nombre_archivo'];
            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            $nombreSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $nuevoNombre = $nombreSinExt . '_firmado.' . $extension;
            $nuevaRuta = $carpeta . '/' . $nuevoNombre;
            
            // Guardar PDF firmado
            $bytesWritten = file_put_contents($nuevaRuta, $pdfContent);
            
            if ($bytesWritten > 0 && file_exists($nuevaRuta)) {
                error_log("Archivo guardado: " . $bytesWritten . " bytes en " . $nuevaRuta);
                
                // Actualizar BD primero
                $rutaRelativa = dirname($documento['ruta_archivo']) . '/' . $nuevoNombre;
                $updateStmt = $db->prepare("
                    UPDATE documentos_personas 
                    SET ruta_archivo = :ruta, nombre_archivo = :nombre
                    WHERE id = :id AND id_tenant = :id_tenant
                ");
                $updateStmt->bindParam(':ruta', $rutaRelativa);
                $updateStmt->bindParam(':nombre', $nuevoNombre);
                $updateStmt->bindParam(':id', $idDocumento);
                $updateStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $updateStmt->execute();
                
                error_log("BD actualizada con nuevo nombre: " . $nuevoNombre);
                
                // Borrar original solo si BD se actualizo
                if (file_exists($rutaOriginal) && $rutaOriginal !== $nuevaRuta) {
                    unlink($rutaOriginal);
                    error_log("Original eliminado: " . $rutaOriginal);
                }
                
                error_log("PDF firmado procesado exitosamente: " . $nuevoNombre);
            } else {
                error_log("Error guardando PDF firmado");
            }
            
        } catch (Exception $e) {
            error_log("Error en descargarYReemplazarPDF: " . $e->getMessage());
        }
    }
}