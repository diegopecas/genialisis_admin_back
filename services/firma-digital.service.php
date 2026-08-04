<?php
class FirmaDigital
{
    private static function obtenerApiKey()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = 'firma_digital_api_key' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();
        return $result ? $result['valor_texto'] : null;
    }

    private static function obtenerTenantCode()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = 'tenant_code' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();
        return $result ? $result['valor_texto'] : null;
    }

    private static function obtenerProveedor()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = 'firma_digital_proveedor' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();
        return $result ? $result['valor_texto'] : 'firma.dev';
    }

    private static function obtenerNombreInstitucion()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT valor_texto FROM configuracion_global WHERE clave = 'institucion_nombre' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $result = $sentence->fetch();
        return $result ? $result['valor_texto'] : 'Institución';
    }

    public static function enviarAFirmar($idDocumento)
    {
        try {
            $db = Flight::db();
            
            // Obtener documento
            $sentenceDoc = $db->prepare("
                SELECT dp.*, td.nombre as nombre_tipo_documento, p.correo_electronico as email_persona
                FROM documentos_personas dp
                INNER JOIN tipos_documentos td ON dp.id_tipo_documento = td.id
                INNER JOIN personas p ON dp.id_persona = p.id
                WHERE dp.id = :id AND dp.id_tenant = :id_tenant
            ");
            $sentenceDoc->bindParam(':id', $idDocumento);
            $sentenceDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDoc->execute();
            $documento = $sentenceDoc->fetch();

            if (!$documento) {
                Flight::json(['error' => 'Documento no encontrado'], 404);
                return;
            }

            // Verificar que el documento existe físicamente
            $rutaCompleta = UploadHelper::getFullPath($documento['ruta_archivo']);
            
            error_log("=== DEBUG FIRMA DIGITAL ===");
            error_log("ruta_archivo de BD: " . $documento['ruta_archivo']);
            error_log("rutaCompleta construida: " . $rutaCompleta);
            error_log("Archivo existe: " . (file_exists($rutaCompleta) ? 'SI' : 'NO'));
            
            if (!file_exists($rutaCompleta)) {
                error_log("ERROR: Archivo no encontrado en: " . $rutaCompleta);
                Flight::json(['error' => 'Archivo físico no encontrado', 'ruta_buscada' => $rutaCompleta], 404);
                return;
            }
            
            error_log("Archivo encontrado OK, tamaño: " . filesize($rutaCompleta) . " bytes");

            // Obtener emails de firmantes desde el request
            $emailsFirmantes = Flight::request()->data['emails_firmantes'] ?? [];
            
            if (empty($emailsFirmantes)) {
                Flight::json(['error' => 'Debe proporcionar al menos un email de firmante'], 400);
                return;
            }

            // Firmantes externos (opcional): personas que NO están en la tabla personas,
            // por ejemplo el representante legal. Estructura esperada por cada item:
            //   ['email' => '...', 'first_name' => '...', 'last_name' => '...', 'es_representante' => true|false]
            // Si no se envía, el comportamiento es idéntico al original (estudiantes).
            $firmantesExternos = Flight::request()->data['firmantes_externos'] ?? [];
            $externosMap = [];
            foreach ($firmantesExternos as $externo) {
                if (!empty($externo['email'])) {
                    $externosMap[$externo['email']] = $externo;
                }
            }

            // Respaldo: correo del representante legal desde configuración global.
            // Permite identificar al representante por email aunque el front no
            // envíe 'firmantes_externos', para casar con el placeholder R99.
            $representanteEmail = null;
            $representanteNombre = null;
            $sentenceRep = $db->prepare("SELECT clave, valor_texto FROM configuracion_global WHERE clave IN ('representante_legal_email', 'representante_legal_nombre') AND id_tenant = :id_tenant");
            $sentenceRep->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceRep->execute();
            foreach ($sentenceRep->fetchAll(PDO::FETCH_ASSOC) as $filaRep) {
                if ($filaRep['clave'] === 'representante_legal_email' && !empty($filaRep['valor_texto'])) {
                    $representanteEmail = trim($filaRep['valor_texto']);
                }
                if ($filaRep['clave'] === 'representante_legal_nombre' && !empty($filaRep['valor_texto'])) {
                    $representanteNombre = trim($filaRep['valor_texto']);
                }
            }

            // Validar emails
            foreach ($emailsFirmantes as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Flight::json(['error' => "Email inválido: {$email}"], 400);
                    return;
                }
            }
            
            // Obtener nombres de las personas desde la BD
            $placeholders = str_repeat('?,', count($emailsFirmantes) - 1) . '?';
            $sentencePersonas = $db->prepare("
                SELECT 
                    correo_electronico,
                    primer_nombre,
                    COALESCE(segundo_nombre, '') as segundo_nombre,
                    primer_apellido,
                    COALESCE(segundo_apellido, '') as segundo_apellido
                FROM personas
                WHERE correo_electronico IN ($placeholders) AND id_tenant = ?
            ");
            $sentencePersonas->execute(array_merge($emailsFirmantes, [TenantContext::id()]));
            $personasData = $sentencePersonas->fetchAll(PDO::FETCH_ASSOC);
            
            // Crear mapa de email -> datos persona
            $personasMap = [];
            foreach ($personasData as $persona) {
                $personasMap[$persona['correo_electronico']] = $persona;
            }

            // Obtener API Key
            $apiKey = self::obtenerApiKey();
            if (!$apiKey) {
                Flight::json(['error' => 'API Key no configurada'], 500);
                return;
            }

            // Leer el archivo PDF en base64
            $pdfContent = file_get_contents($rutaCompleta);
            $pdfBase64 = base64_encode($pdfContent);
            
            error_log("PDF leído correctamente, tamaño original: " . strlen($pdfContent) . " bytes");
            error_log("PDF en base64, longitud: " . strlen($pdfBase64) . " caracteres");

            // Extraer coordenadas de placeholders
            require_once __DIR__ . '/pdf-placeholder-extractor.service.php';
            $signatureFields = PdfPlaceholderExtractor::extractSignatureFields($rutaCompleta, $emailsFirmantes);
            
            // Validar campos extraídos
            $validation = PdfPlaceholderExtractor::validateFields($signatureFields, count($emailsFirmantes));
            if (!$validation['valid']) {
                error_log("⚠️ Advertencias de validación de campos:");
                foreach ($validation['issues'] as $issue) {
                    error_log("   - " . $issue);
                }
            }
            
            error_log("📊 Campos extraídos: {$validation['total_fields']} total, {$validation['unique_recipients']} firmantes únicos");
            error_log("   Promedio de campos por firmante: " . number_format($validation['fields_per_recipient'], 1));

            // Preparar request para Firma.dev
            $recipients = [];
            $recipientIndex = 1;
            foreach ($emailsFirmantes as $email) {
                $esRepresentante = false;

                // Respaldo: si el correo coincide con el del representante legal
                // configurado, se marca como representante aunque no venga en
                // firmantes_externos.
                if ($representanteEmail && strcasecmp($email, $representanteEmail) === 0) {
                    $esRepresentante = true;
                }

                if (isset($externosMap[$email])) {
                    // Firmante externo (no está en personas): usar nombre provisto
                    $externo = $externosMap[$email];
                    $firstName = trim($externo['first_name'] ?? '');
                    $lastName = trim($externo['last_name'] ?? '');
                    $esRepresentante = $esRepresentante || !empty($externo['es_representante']);

                    // Fallback de nombre si vino vacío
                    if ($firstName === '') {
                        $emailParts = explode('@', $email);
                        $firstName = $emailParts[0];
                    }
                    if ($lastName === '') {
                        $lastName = 'Firmante';
                    }
                } else if (isset($personasMap[$email])) {
                    $persona = $personasMap[$email];
                    $firstName = trim($persona['primer_nombre'] . ' ' . $persona['segundo_nombre']);
                    $lastName = trim($persona['primer_apellido'] . ' ' . $persona['segundo_apellido']);
                } else if ($esRepresentante && $representanteNombre) {
                    // Representante identificado por configuración: partir el nombre
                    // completo en nombres y apellidos de forma aproximada.
                    $partes = preg_split('/\s+/', $representanteNombre);
                    if (count($partes) >= 4) {
                        $firstName = $partes[0] . ' ' . $partes[1];
                        $lastName = implode(' ', array_slice($partes, 2));
                    } else if (count($partes) === 3) {
                        $firstName = $partes[0];
                        $lastName = $partes[1] . ' ' . $partes[2];
                    } else {
                        $firstName = $partes[0] ?? $representanteNombre;
                        $lastName = $partes[1] ?? 'Representante';
                    }
                } else {
                    $emailParts = explode('@', $email);
                    $emailName = $emailParts[0];
                    $firstName = $emailName;
                    $lastName = 'Firmante';
                }

                // El representante usa el id temp_representante para casar con el
                // placeholder R99 que genera el PDF (PdfPlaceholderExtractor).
                $recipientId = $esRepresentante ? 'temp_representante' : 'temp_' . $recipientIndex;

                $recipients[] = [
                    'id' => $recipientId,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName
                ];

                if (!$esRepresentante) {
                    $recipientIndex++;
                }
            }

            // Obtener tenant_code para el webhook
            $tenantCode = self::obtenerTenantCode();
            if (!$tenantCode) {
                Flight::json(['error' => 'tenant_code no configurado en configuracion_global'], 500);
                return;
            }

            // Obtener nombre de institución para personalizar email
            $nombreInstitucion = self::obtenerNombreInstitucion();
            $tipoDocumento = $documento['nombre_tipo_documento'];
            
            $emailSubject = "{$nombreInstitucion} - {$tipoDocumento} para firmar";
            $emailMessage = "Estimado(a), por favor revise y firme el documento: {$tipoDocumento}. Si tiene alguna pregunta, no dude en contactarnos.";

            $payload = [
                'document' => $pdfBase64,
                'name' => $documento['nombre_archivo'],
                'recipients' => $recipients,
                'expiration_hours' => 168,
                'fields' => $signatureFields,
                'custom_fields' => [
                    ['name' => 'tenant_code', 'value' => $tenantCode],
                    ['name' => 'id_documento', 'value' => (string)$idDocumento]
                ],
                'email_subject' => $emailSubject,
                'email_body' => $emailMessage
            ];
            
            error_log("╔══════════════════════════════════════════════════════════╗");
            error_log("║  VALIDACIÓN PRE-ENVÍO - FIRMA DIGITAL                   ║");
            error_log("╚══════════════════════════════════════════════════════════╝");
            
            error_log("📄 DOCUMENTO:");
            error_log("  - Nombre: " . $documento['nombre_archivo']);
            error_log("  - Tamaño base64: " . number_format(strlen($pdfBase64)) . " caracteres");
            error_log("  - ID documento: " . $idDocumento);
            
            error_log("👥 FIRMANTES (" . count($recipients) . "):");
            foreach ($recipients as $i => $recipient) {
                error_log("  " . ($i+1) . ". {$recipient['first_name']} {$recipient['last_name']}");
                error_log("     Email: {$recipient['email']}");
                error_log("     ID: {$recipient['id']}");
            }
            
            error_log("✍️ CAMPOS DE FIRMA (" . count($signatureFields) . "):");
            if (empty($signatureFields)) {
                error_log("  ⚠️ ¡ADVERTENCIA! NO HAY CAMPOS DE FIRMA");
                error_log("  Los firmantes NO verán dónde firmar en el documento");
            } else {
                foreach ($signatureFields as $i => $field) {
                    error_log("  " . ($i+1) . ". Tipo: {$field['type']}");
                    error_log("     Página: {$field['page_number']}");
                    error_log("     Posición: x={$field['position']['x']}%, y={$field['position']['y']}%");
                    error_log("     Tamaño: {$field['position']['width']}% x {$field['position']['height']}%");
                    error_log("     Para: {$field['recipient_id']}");
                }
            }
            
            error_log("⚙️ CONFIGURACIÓN:");
            error_log("  - Expiración: 168 horas (7 días)");
            error_log("  - Proveedor: " . self::obtenerProveedor());
            
            $expectedFields = count($recipients);
            $actualFields = count($signatureFields);
            
            if ($actualFields > 0 && $actualFields !== $expectedFields) {
                error_log("⚠️ ADVERTENCIA: Cantidad de campos ({$actualFields}) ≠ Cantidad de firmantes ({$expectedFields})");
            }
            
            $payloadPreview = $payload;
            $payloadPreview['document'] = '[BASE64_DATA_' . strlen($pdfBase64) . '_CHARS]';
            error_log("📦 PAYLOAD FINAL:");
            error_log(json_encode($payloadPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            error_log("╔══════════════════════════════════════════════════════════╗");
            error_log("║  ENVIANDO A FIRMA.DEV...                                 ║");
            error_log("╚══════════════════════════════════════════════════════════╝");

            // Llamar a API de Firma.dev
            $ch = curl_init('https://api.firma.dev/functions/v1/signing-request-api/signing-requests');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Respuesta de Firma.dev:");
            error_log("- HTTP Code: " . $httpCode);
            error_log("- Response body: " . $response);

            if ($httpCode !== 200 && $httpCode !== 201) {
                error_log("Error Firma.dev: " . $response);
                Flight::json(['error' => 'Error al enviar a firma digital', 'detalles' => $response], 500);
                return;
            }

            $responseData = json_decode($response, true);
            error_log("🔍 PASO 1 COMPLETADO - Response decodificada");
            error_log("Response keys: " . implode(', ', array_keys($responseData ?? [])));
            
            $signingRequestId = $responseData['id'] ?? null;
            error_log("🔍 Signing Request ID extraído: " . ($signingRequestId ?? 'NULL'));
            
            if (!$signingRequestId) {
                error_log("❌ ERROR: No se obtuvo ID del signing request");
                Flight::json(['error' => 'No se pudo crear el signing request'], 500);
                return;
            }
            
            error_log("✅ Signing request creado con ID: " . $signingRequestId);
            error_log("🚀 INICIANDO PASO 2: Envío a firmantes...");
            
            // PASO 2: Enviar el signing request a los firmantes
            error_log("📧 Enviando signing request a firmantes...");
            
            $ch = curl_init('https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $signingRequestId . '/send');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json'
            ]);
            
            $sendResponse = curl_exec($ch);
            $sendHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            error_log("Respuesta de SEND:");
            error_log("- HTTP Code: " . $sendHttpCode);
            error_log("- Response body: " . $sendResponse);
            
            if ($sendHttpCode !== 200 && $sendHttpCode !== 201) {
                error_log("Error al enviar: " . $sendResponse);
            } else {
                error_log("✅ Signing request enviado exitosamente a firmantes");
            }

            // Actualizar documento con información de firma
            $proveedor = self::obtenerProveedor();
            $firmaUrl = $responseData['document_url'] ?? null;

            $updateDoc = $db->prepare("
                UPDATE documentos_personas 
                SET firma_digital_id = :firma_id,
                    firma_digital_estado = 'enviado',
                    firma_digital_url = :firma_url,
                    fecha_envio_firma = NOW(),
                    proveedor_firma = :proveedor
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $updateDoc->bindParam(':firma_id', $signingRequestId);
            $updateDoc->bindParam(':firma_url', $firmaUrl);
            $updateDoc->bindParam(':proveedor', $proveedor);
            $updateDoc->bindParam(':id', $idDocumento);
            $updateDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $updateDoc->execute();

            Flight::json([
                'success' => true,
                'firma_id' => $signingRequestId,
                'firma_url' => $firmaUrl,
                'estado' => 'enviado'
            ]);

        } catch (Exception $e) {
            error_log("Error en enviarAFirmar: " . $e->getMessage());
            Flight::json(['error' => 'Error al procesar solicitud: ' . $e->getMessage()], 500);
        }
    }

    public static function consultarEstado($idDocumento)
    {
        try {
            $db = Flight::db();
            
            // Obtener documento
            $sentenceDoc = $db->prepare("
                SELECT firma_digital_id, firma_digital_estado, proveedor_firma, ruta_archivo, nombre_archivo
                FROM documentos_personas 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceDoc->bindParam(':id', $idDocumento);
            $sentenceDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDoc->execute();
            $documento = $sentenceDoc->fetch();

            if (!$documento || !$documento['firma_digital_id']) {
                Flight::json(['error' => 'Documento no tiene firma digital iniciada'], 404);
                return;
            }

            // Obtener API Key
            $apiKey = self::obtenerApiKey();
            if (!$apiKey) {
                Flight::json(['error' => 'API Key no configurada'], 500);
                return;
            }

            // Consultar estado en Firma.dev
            $ch = curl_init('https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $documento['firma_digital_id']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Error consultando estado Firma.dev: " . $response);
                Flight::json(['error' => 'Error al consultar estado'], 500);
                return;
            }

            $responseData = json_decode($response, true);
            
            error_log("=== CONSULTAR ESTADO ===");
            error_log("Response completa: " . print_r($responseData, true));
            
            // El status es un objeto con flags
            $statusObj = $responseData['status'] ?? [];
            
            // Determinar estado basándose en los flags
            $nuevoEstado = 'not_sent';
            
            if (!empty($statusObj['finished'])) {
                $nuevoEstado = 'finished';
            } elseif (!empty($statusObj['cancelled'])) {
                $nuevoEstado = 'cancelled';
            } elseif (!empty($statusObj['declined'])) {
                $nuevoEstado = 'cancelled';
            } elseif (!empty($statusObj['expired'])) {
                $nuevoEstado = 'expired';
            } elseif (!empty($statusObj['sent'])) {
                $nuevoEstado = 'in_progress';
            }
            
            error_log("Estado determinado: " . $nuevoEstado);

            // Mapear estados de Firma.dev a nuestros estados
            $estadoMapeado = self::mapearEstado($nuevoEstado);

            // Obtener lista de firmantes
            $firmantes = [];
            $totalFirmantes = 0;
            $firmantesCompletados = 0;
            
            $chUsers = curl_init('https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $documento['firma_digital_id'] . '/users');
            curl_setopt($chUsers, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chUsers, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey
            ]);

            $usersResponse = curl_exec($chUsers);
            $usersHttpCode = curl_getinfo($chUsers, CURLINFO_HTTP_CODE);
            curl_close($chUsers);
            
            error_log("📋 Respuesta de /users (HTTP {$usersHttpCode}):");
            error_log($usersResponse);
            
            if ($usersHttpCode === 200) {
                $usersData = json_decode($usersResponse, true);
                
                $recipients = [];
                if (isset($usersData['results'])) {
                    $recipients = $usersData['results'];
                } elseif (isset($usersData['users'])) {
                    $recipients = $usersData['users'];
                } elseif (isset($usersData['recipients'])) {
                    $recipients = $usersData['recipients'];
                } elseif (isset($usersData['data'])) {
                    $recipients = $usersData['data'];
                } elseif (is_array($usersData) && !empty($usersData) && isset($usersData[0]['email'])) {
                    $recipients = $usersData;
                }
                
                error_log("📋 Recipients encontrados: " . count($recipients));
                
                if (is_array($recipients) && count($recipients) > 0) {
                    $totalFirmantes = count($recipients);
                    
                    foreach ($recipients as $recipient) {
                        $firmadoAt = $recipient['finished_on'] 
                            ?? $recipient['finished_date'] 
                            ?? $recipient['signed_at'] 
                            ?? $recipient['signed_on']
                            ?? null;
                        $haFirmado = !empty($firmadoAt);
                        
                        if (!$haFirmado && isset($recipient['status'])) {
                            $haFirmado = in_array(strtolower($recipient['status']), ['signed', 'finished', 'completed']);
                        }
                        
                        $emailFirmante = $recipient['email'] ?? 'sin-email';
                        $nombreFirmante = trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''));
                        if (empty($nombreFirmante)) {
                            $nombreFirmante = $recipient['name'] ?? $emailFirmante;
                        }
                        
                        error_log("  👤 {$emailFirmante} ({$nombreFirmante}): finished_on=" . ($firmadoAt ?? 'NULL') . " -> " . ($haFirmado ? 'FIRMADO' : 'PENDIENTE'));
                        
                        if ($haFirmado) {
                            $firmantesCompletados++;
                        }
                        
                        $firmantes[] = [
                            'email' => $emailFirmante,
                            'nombre' => $nombreFirmante,
                            'firmado' => $haFirmado,
                            'fecha_firma' => $firmadoAt,
                            'estado' => $haFirmado ? 'firmado' : 'pendiente'
                        ];
                    }
                } else {
                    error_log("⚠️ No se encontraron recipients en la respuesta");
                    error_log("Keys disponibles: " . implode(', ', array_keys($usersData ?? [])));
                }
            } else {
                error_log("⚠️ No se pudo obtener lista de firmantes (HTTP {$usersHttpCode})");
            }
            
            error_log("📊 Resumen: {$firmantesCompletados} de {$totalFirmantes} firmantes completados");

            // Actualizar estado en BD
            $updateDoc = $db->prepare("
                UPDATE documentos_personas 
                SET firma_digital_estado = :estado,
                    fecha_firmado = CASE WHEN :estado_check = 'firmado' THEN NOW() ELSE fecha_firmado END
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $updateDoc->bindParam(':estado', $estadoMapeado);
            $updateDoc->bindParam(':estado_check', $estadoMapeado);
            $updateDoc->bindParam(':id', $idDocumento);
            $updateDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $updateDoc->execute();

            // Si está firmado, descargar y reemplazar archivo
            $archivoActualizado = false;
            if ($estadoMapeado === 'firmado') {
                // Verificar si ya tiene _firmado en el nombre (ya fue descargado previamente)
                if (strpos($documento['nombre_archivo'], '_firmado') !== false) {
                    error_log("✅ Documento ya tiene sufijo _firmado, no es necesario descargar de nuevo");
                    $archivoActualizado = true;
                } else {
                    $finalDocumentUrl = $responseData['final_document_download_url'] ?? null;
                    
                    error_log("🔗 URL de descarga: " . ($finalDocumentUrl ?? 'NULL'));
                    
                    if ($finalDocumentUrl && $documento['ruta_archivo']) {
                        // PASO 1: Descargar PDF firmado
                        $chPdf = curl_init($finalDocumentUrl);
                        curl_setopt($chPdf, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($chPdf, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($chPdf, CURLOPT_TIMEOUT, 60);
                        $pdfContent = curl_exec($chPdf);
                        $pdfHttpCode = curl_getinfo($chPdf, CURLINFO_HTTP_CODE);
                        $curlError = curl_error($chPdf);
                        curl_close($chPdf);
                        
                        $pdfSize = strlen($pdfContent);
                        error_log("📥 Descarga: HTTP {$pdfHttpCode}, Tamaño: {$pdfSize} bytes");
                        
                        if ($curlError) {
                            error_log("❌ Error CURL: " . $curlError);
                        }
                    
                        if ($pdfHttpCode === 200 && $pdfSize > 1024) {
                            $rutaOriginal = UploadHelper::getFullPath($documento['ruta_archivo']);
                            $carpeta = dirname($rutaOriginal);
                            
                            $nombreOriginal = $documento['nombre_archivo'];
                            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
                            $nombreSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
                            $nuevoNombre = $nombreSinExt . '_firmado.' . $extension;
                            $nuevaRuta = $carpeta . '/' . $nuevoNombre;
                            
                            // PASO 2: Guardar archivo firmado
                            $bytesWritten = file_put_contents($nuevaRuta, $pdfContent);
                            
                            if ($bytesWritten > 0 && file_exists($nuevaRuta)) {
                                error_log("✅ Archivo guardado: {$bytesWritten} bytes");
                                
                                // PASO 3: Actualizar BD
                                $rutaRelativa = dirname($documento['ruta_archivo']) . '/' . $nuevoNombre;
                                $updateRuta = $db->prepare("
                                    UPDATE documentos_personas 
                                    SET ruta_archivo = :ruta, nombre_archivo = :nombre
                                    WHERE id = :id AND id_tenant = :id_tenant
                                ");
                                $updateRuta->bindParam(':ruta', $rutaRelativa);
                                $updateRuta->bindParam(':nombre', $nuevoNombre);
                                $updateRuta->bindParam(':id', $idDocumento);
                                $updateRuta->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                                $updateRuta->execute();
                                
                                error_log("✅ BD actualizada");
                                
                                // PASO 4: Borrar original (solo si todo lo anterior fue exitoso)
                                if (file_exists($rutaOriginal) && $rutaOriginal !== $nuevaRuta) {
                                    unlink($rutaOriginal);
                                    error_log("🗑️ Original eliminado");
                                }
                                
                                $archivoActualizado = true;
                                error_log("✅ Proceso completado: " . $nuevoNombre);
                            } else {
                                error_log("❌ Error al guardar archivo");
                            }
                        } else {
                            error_log("❌ Descarga fallida (HTTP {$pdfHttpCode}, Size: {$pdfSize})");
                        }
                    } else {
                        error_log("⚠️ No hay URL de descarga o ruta de archivo");
                    }
                }
            }

            Flight::json([
                'success' => true,
                'estado' => $estadoMapeado,
                'progreso' => [
                    'total' => $totalFirmantes,
                    'completados' => $firmantesCompletados,
                    'porcentaje' => $totalFirmantes > 0 ? round(($firmantesCompletados / $totalFirmantes) * 100) : 0
                ],
                'firmantes' => $firmantes,
                'archivo_actualizado' => $archivoActualizado,
                'detalles' => $responseData
            ]);

        } catch (Exception $e) {
            error_log("Error en consultarEstado: " . $e->getMessage());
            Flight::json(['error' => 'Error al consultar estado: ' . $e->getMessage()], 500);
        }
    }

    public static function descargarFirmado($idDocumento)
    {
        try {
            $db = Flight::db();
            
            // Obtener documento
            $sentenceDoc = $db->prepare("
                SELECT firma_digital_id, firma_digital_estado, nombre_archivo, proveedor_firma, ruta_archivo
                FROM documentos_personas 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceDoc->bindParam(':id', $idDocumento);
            $sentenceDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDoc->execute();
            $documento = $sentenceDoc->fetch();

            if (!$documento || !$documento['firma_digital_id']) {
                Flight::json(['error' => 'Documento no tiene firma digital'], 404);
                return;
            }

            if ($documento['firma_digital_estado'] !== 'firmado') {
                Flight::json(['error' => 'Documento aún no está firmado'], 400);
                return;
            }

            // Verificar si ya tiene _firmado en el nombre (ya fue descargado)
            if (strpos($documento['nombre_archivo'], '_firmado') !== false) {
                Flight::json([
                    'success' => true,
                    'mensaje' => 'Documento firmado ya disponible',
                    'ruta_firmado' => $documento['ruta_archivo']
                ]);
                return;
            }

            // Obtener API Key
            $apiKey = self::obtenerApiKey();
            if (!$apiKey) {
                Flight::json(['error' => 'API Key no configurada'], 500);
                return;
            }

            // Obtener información del signing request
            $ch = curl_init('https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $documento['firma_digital_id']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Error obteniendo info del documento: " . $response);
                Flight::json(['error' => 'Error al obtener información del documento'], 500);
                return;
            }

            $signingRequestData = json_decode($response, true);
            $finalDocumentUrl = $signingRequestData['final_document_download_url'] ?? null;

            if (!$finalDocumentUrl) {
                Flight::json(['error' => 'URL de descarga no disponible aún'], 400);
                return;
            }

            // Descargar PDF firmado
            $ch = curl_init($finalDocumentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $pdfContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Error descargando PDF firmado: HTTP " . $httpCode);
                Flight::json(['error' => 'Error al descargar documento firmado'], 500);
                return;
            }

            // Guardar en misma carpeta con _firmado
            $rutaOriginal = UploadHelper::getFullPath($documento['ruta_archivo']);
            $carpeta = dirname($rutaOriginal);
            
            $nombreOriginal = $documento['nombre_archivo'];
            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            $nombreSinExt = pathinfo($nombreOriginal, PATHINFO_FILENAME);
            $nuevoNombre = $nombreSinExt . '_firmado.' . $extension;
            $nuevaRuta = $carpeta . '/' . $nuevoNombre;
            
            // Guardar firmado
            file_put_contents($nuevaRuta, $pdfContent);
            
            // Borrar original
            if (file_exists($rutaOriginal) && $rutaOriginal !== $nuevaRuta) {
                unlink($rutaOriginal);
            }
            
            // Actualizar BD
            $rutaRelativa = dirname($documento['ruta_archivo']) . '/' . $nuevoNombre;
            $updateDoc = $db->prepare("
                UPDATE documentos_personas 
                SET ruta_archivo = :ruta, nombre_archivo = :nombre
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $updateDoc->bindParam(':ruta', $rutaRelativa);
            $updateDoc->bindParam(':nombre', $nuevoNombre);
            $updateDoc->bindParam(':id', $idDocumento);
            $updateDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $updateDoc->execute();

            Flight::json([
                'success' => true,
                'ruta_firmado' => $rutaRelativa
            ]);

        } catch (Exception $e) {
            error_log("Error en descargarFirmado: " . $e->getMessage());
            Flight::json(['error' => 'Error al descargar firmado: ' . $e->getMessage()], 500);
        }
    }

    private static function mapearEstado($estadoFirmaDev)
    {
        $mapeo = [
            'not_sent' => 'enviado',
            'in_progress' => 'enviado',
            'finished' => 'firmado',
            'cancelled' => 'rechazado',
            'expired' => 'rechazado',
            'deleted' => 'rechazado'
        ];

        return $mapeo[$estadoFirmaDev] ?? $estadoFirmaDev;
    }

    public static function reenviarCorreoFirma($idDocumento)
    {
        try {
            $db = Flight::db();
            
            // Obtener documento
            $sentenceDoc = $db->prepare("
                SELECT firma_digital_id, firma_digital_estado, nombre_archivo
                FROM documentos_personas 
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceDoc->bindParam(':id', $idDocumento);
            $sentenceDoc->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDoc->execute();
            $documento = $sentenceDoc->fetch();

            if (!$documento || !$documento['firma_digital_id']) {
                Flight::json(['error' => 'Documento no tiene firma digital iniciada'], 404);
                return;
            }

            if ($documento['firma_digital_estado'] === 'firmado') {
                Flight::json(['error' => 'El documento ya fue firmado'], 400);
                return;
            }

            // Obtener API Key
            $apiKey = self::obtenerApiKey();
            if (!$apiKey) {
                Flight::json(['error' => 'API Key no configurada'], 500);
                return;
            }

            // Obtener los recipients del endpoint /users
            $signingRequestId = $documento['firma_digital_id'];
            
            $urlUsers = 'https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $signingRequestId . '/users';
            
            $chUsers = curl_init($urlUsers);
            curl_setopt($chUsers, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chUsers, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json'
            ]);
            
            $responseUsers = curl_exec($chUsers);
            $httpCodeUsers = curl_getinfo($chUsers, CURLINFO_HTTP_CODE);
            curl_close($chUsers);
            
            error_log("Reenvío - Users response HTTP: " . $httpCodeUsers);
            error_log("Reenvío - Users response: " . $responseUsers);
            
            if ($httpCodeUsers !== 200) {
                Flight::json(['error' => 'No se pudo consultar los firmantes'], 500);
                return;
            }
            
            $usersData = json_decode($responseUsers, true);
            
            // Buscar recipients en diferentes estructuras posibles
            $recipients = [];
            if (isset($usersData['results'])) {
                $recipients = $usersData['results'];
            } elseif (isset($usersData['users'])) {
                $recipients = $usersData['users'];
            } elseif (isset($usersData['recipients'])) {
                $recipients = $usersData['recipients'];
            } elseif (isset($usersData['data'])) {
                $recipients = $usersData['data'];
            } elseif (is_array($usersData) && !empty($usersData) && isset($usersData[0]['email'])) {
                $recipients = $usersData;
            }
            
            // Obtener los recipient_ids de firmantes que NO han firmado
            $recipientIdsPendientes = [];
            
            // Determinar el orden activo (el menor orden que no ha firmado)
            $ordenActivo = PHP_INT_MAX;
            if (is_array($recipients)) {
                foreach ($recipients as $recipient) {
                    $firmadoAt = $recipient['finished_on'] 
                        ?? $recipient['finished_date'] 
                        ?? $recipient['signed_at'] 
                        ?? $recipient['signed_on']
                        ?? null;
                    $haFirmado = !empty($firmadoAt);
                    
                    if (!$haFirmado && isset($recipient['status'])) {
                        $haFirmado = in_array(strtolower($recipient['status']), ['signed', 'finished', 'completed']);
                    }
                    
                    // Si no ha firmado, verificar si su orden es menor
                    if (!$haFirmado) {
                        $orden = $recipient['order'] ?? 1;
                        if ($orden < $ordenActivo) {
                            $ordenActivo = $orden;
                        }
                    }
                }
            }
            
            error_log("Reenvío - Orden activo: " . $ordenActivo);
            
            // Ahora filtrar solo los del orden activo
            if (is_array($recipients)) {
                foreach ($recipients as $recipient) {
                    $firmadoAt = $recipient['finished_on'] 
                        ?? $recipient['finished_date'] 
                        ?? $recipient['signed_at'] 
                        ?? $recipient['signed_on']
                        ?? null;
                    $haFirmado = !empty($firmadoAt);
                    
                    if (!$haFirmado && isset($recipient['status'])) {
                        $haFirmado = in_array(strtolower($recipient['status']), ['signed', 'finished', 'completed']);
                    }
                    
                    $orden = $recipient['order'] ?? 1;
                    
                    error_log("Reenvío - Recipient: " . $recipient['email'] . " orden:" . $orden . " -> " . ($haFirmado ? 'FIRMADO' : 'PENDIENTE'));
                    
                    // Solo agregar si no ha firmado Y está en el orden activo
                    if (!$haFirmado && isset($recipient['id']) && $orden == $ordenActivo) {
                        $recipientIdsPendientes[] = $recipient['id'];
                    }
                }
            }
            
            if (empty($recipientIdsPendientes)) {
                Flight::json(['error' => 'No hay firmantes pendientes'], 400);
                return;
            }

            // Llamar a API de Firma.dev para reenviar
            $url = 'https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . $documento['firma_digital_id'] . '/resend';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: ' . $apiKey,
                'Content-Type: application/json'
            ]);
            
            // Enviar recipient_ids de los pendientes
            $payload = json_encode(['recipient_ids' => $recipientIdsPendientes]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            
            error_log("Reenvío firma - Payload: " . $payload);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("Reenvío firma - HTTP: {$httpCode}, Response: {$response}");

            if ($httpCode !== 200 && $httpCode !== 201) {
                $errorData = json_decode($response, true);
                $errorMsg = $errorData['error'] ?? 'Error al reenviar correo';
                Flight::json(['error' => $errorMsg, 'detalles' => $response], 500);
                return;
            }

            Flight::json([
                'success' => true,
                'mensaje' => 'Correo de firma reenviado exitosamente',
                'firmantes_notificados' => count($recipientIdsPendientes)
            ]);

        } catch (Exception $e) {
            error_log("Error en reenviarCorreoFirma: " . $e->getMessage());
            Flight::json(['error' => 'Error al reenviar: ' . $e->getMessage()], 500);
        }
    }
}