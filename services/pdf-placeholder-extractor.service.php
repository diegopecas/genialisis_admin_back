<?php
/**
 * Extractor de coordenadas de placeholders en PDFs para firma digital
 * 
 * FORMATO NUEVO (con recipient index):
 * [[SIGN_{globalIndex}:R{recipientIndex}:P{page}:X{x%}:Y{y%}:W{w%}:H{h%}]]
 * Ejemplo: [[SIGN_1:R1:P7:X15:Y75:W32:H7]]
 * 
 * FORMATO ANTIGUO (compatible):
 * [[SIGN_{index}:P{page}:X{x%}:Y{y%}:W{w%}:H{h%}]]
 * Ejemplo: [[SIGN_1:P7:X15:Y75:W32:H7]]
 * 
 * Soporta MÚLTIPLES campos de firma por firmante (uno por sección del documento)
 */

class PdfPlaceholderExtractor
{
    /**
     * Extrae placeholders y coordenadas del PDF
     * 
     * @param string $pdfPath Ruta al PDF
     * @param array $emailsFirmantes Array de emails de firmantes (para mapear recipient_id)
     * @return array Campos de firma con coordenadas
     */
    public static function extractSignatureFields($pdfPath, $emailsFirmantes = [])
    {
        try {
            // Leer contenido del PDF
            $content = file_get_contents($pdfPath);
            
            if (!$content) {
                error_log("❌ No se pudo leer el PDF: " . $pdfPath);
                return [];
            }
            
            // Obtener número de páginas del PDF
            $pageCount = self::getPdfPageCount($pdfPath);
            error_log("📄 PDF tiene {$pageCount} páginas");
            
            $fields = [];
            $numFirmantes = count($emailsFirmantes);
            
            // =====================================================
            // MÉTODO 1: Buscar placeholders con formato NUEVO (con recipient)
            // Formato: [[SIGN_{globalIndex}:R{recipientIndex}:P{page}:X{x}:Y{y}:W{w}:H{h}]]
            // =====================================================
            $patternNew = '/\[\[SIGN_(\d+):R(\d+):P(\d+):X(\d+):Y(\d+):W(\d+):H(\d+)\]\]/';
            
            if (preg_match_all($patternNew, $content, $matches, PREG_SET_ORDER)) {
                error_log("✅ Encontrados " . count($matches) . " placeholders con formato NUEVO (múltiples por firmante)");
                
                // Agrupar campos por recipient_id
                $fieldsByRecipient = [];
                
                foreach ($matches as $match) {
                    $globalIndex = (int)$match[1];
                    $recipientIndex = (int)$match[2];
                    $page = (int)$match[3];
                    $x = (int)$match[4];
                    $y = (int)$match[5];
                    $w = (int)$match[6];
                    $h = (int)$match[7];
                    
                    error_log("  📍 SIGN_{$globalIndex}: recipient={$recipientIndex}, página={$page}, x={$x}%, y={$y}%, w={$w}%, h={$h}%");
                    
                    // Determinar el temp_id basado en el recipientIndex
                    // 99 es el representante legal y 98 el director general:
                    // los dos firman por la organizacion, no por el cliente.
                    if ($recipientIndex == 99) {
                        $tempId = 'temp_representante';
                    } else if ($recipientIndex == 98) {
                        $tempId = 'temp_director';
                    } else {
                        // Para representantes: mapear al índice correcto
                        // Si recipientIndex > numFirmantes, usar módulo para ciclar
                        $mappedIndex = $recipientIndex;
                        if ($numFirmantes > 0 && $recipientIndex > $numFirmantes) {
                            $mappedIndex = (($recipientIndex - 1) % $numFirmantes) + 1;
                        }
                        $tempId = 'temp_' . $mappedIndex;
                    }
                    
                    $fields[] = [
                        'type' => 'signature',
                        'page_number' => $page,
                        'position' => [
                            'x' => $x,
                            'y' => $y,
                            'width' => $w,
                            'height' => $h
                        ],
                        'recipient_id' => $tempId,
                        'required' => true
                    ];
                }
                
                // Log resumen de campos por firmante
                $summary = [];
                foreach ($fields as $field) {
                    $rid = $field['recipient_id'];
                    if (!isset($summary[$rid])) {
                        $summary[$rid] = 0;
                    }
                    $summary[$rid]++;
                }
                
                error_log("📊 Resumen de campos por firmante:");
                foreach ($summary as $rid => $count) {
                    error_log("   - {$rid}: {$count} campos de firma");
                }
                
                return $fields;
            }
            
            // =====================================================
            // MÉTODO 2: Buscar placeholders con formato ANTIGUO (sin recipient)
            // Formato: [[SIGN_{index}:P{page}:X{x}:Y{y}:W{w}:H{h}]]
            // =====================================================
            $patternOld = '/\[\[SIGN_(\d+):P(\d+):X(\d+):Y(\d+):W(\d+):H(\d+)\]\]/';
            
            if (preg_match_all($patternOld, $content, $matches, PREG_SET_ORDER)) {
                error_log("✅ Encontrados " . count($matches) . " placeholders con formato ANTIGUO");
                
                foreach ($matches as $match) {
                    $signIndex = (int)$match[1];
                    $page = (int)$match[2];
                    $x = (int)$match[3];
                    $y = (int)$match[4];
                    $w = (int)$match[5];
                    $h = (int)$match[6];
                    
                    error_log("  📍 SIGN_{$signIndex}: página {$page}, x={$x}%, y={$y}%, w={$w}%, h={$h}%");
                    
                    $fields[] = [
                        'type' => 'signature',
                        'page_number' => $page,
                        'position' => [
                            'x' => $x,
                            'y' => $y,
                            'width' => $w,
                            'height' => $h
                        ],
                        'recipient_id' => 'temp_' . $signIndex,
                        'required' => true
                    ];
                }
                
                return $fields;
            }
            
            // =====================================================
            // MÉTODO 3: Fallback - Buscar placeholders muy antiguos
            // Formato: [SIGN_REPRESENTANTE_1], [SIGN_REPRESENTANTE_2], [SIGN_REPRESENTANTE]
            // =====================================================
            error_log("⚠️ No se encontraron placeholders codificados, buscando formato legacy...");
            
            $placeholders = [];
            
            if (preg_match('/\[SIGN_REPRESENTANTE_1\]/', $content)) {
                $placeholders[] = 'SIGN_REPRESENTANTE_1';
            }
            if (preg_match('/\[SIGN_REPRESENTANTE_2\]/', $content)) {
                $placeholders[] = 'SIGN_REPRESENTANTE_2';
            }
            if (preg_match('/\[SIGN_REPRESENTANTE_3\]/', $content)) {
                $placeholders[] = 'SIGN_REPRESENTANTE_3';
            }
            if (preg_match('/\[SIGN_REPRESENTANTE_4\]/', $content)) {
                $placeholders[] = 'SIGN_REPRESENTANTE_4';
            }
            
            if (empty($placeholders)) {
                error_log("⚠️ No se encontraron placeholders en el PDF");
                error_log("ℹ️ Usando posiciones por defecto basadas en número de firmantes");
                
                // Fallback: usar posiciones predeterminadas
                return self::getDefaultPositions($pageCount, $numFirmantes);
            }
            
            error_log("📌 Placeholders legacy encontrados: " . implode(', ', $placeholders));
            
            // Generar campos de firma con posiciones aproximadas
            $signaturePage = $pageCount; // Última página
            $recipientIndex = 1;
            
            foreach ($placeholders as $placeholder) {
                $position = self::getPositionForPlaceholder($placeholder, $recipientIndex, count($placeholders));
                
                $fields[] = [
                    'type' => 'signature',
                    'page_number' => $signaturePage,
                    'position' => [
                        'x' => $position['x'],
                        'y' => $position['y'],
                        'width' => $position['width'],
                        'height' => $position['height']
                    ],
                    'recipient_id' => 'temp_' . $recipientIndex,
                    'required' => true
                ];
                
                $recipientIndex++;
            }
            
            error_log("✅ Generados " . count($fields) . " campos de firma (formato legacy)");
            
            return $fields;
            
        } catch (Exception $e) {
            error_log("❌ Error extrayendo placeholders: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtiene posiciones por defecto cuando no hay placeholders
     * Genera campos para cada firmante en cada sección del documento
     */
    private static function getDefaultPositions($pageCount, $numFirmantes)
    {
        $fields = [];
        $numFirmantes = max(1, $numFirmantes ?: 2);
        
        // Calcular página de firma (última del contrato principal, aprox 70% del doc)
        $signaturePage = max(1, (int)($pageCount * 0.7));
        
        // Posiciones base para 2 firmantes lado a lado
        $positions = [
            ['x' => 15, 'y' => 75],  // Izquierda
            ['x' => 53, 'y' => 75],  // Derecha
        ];
        
        for ($i = 0; $i < min($numFirmantes, 2); $i++) {
            $fields[] = [
                'type' => 'signature',
                'page_number' => $signaturePage,
                'position' => [
                    'x' => $positions[$i]['x'],
                    'y' => $positions[$i]['y'],
                    'width' => 32,
                    'height' => 7
                ],
                'recipient_id' => 'temp_' . ($i + 1),
                'required' => true
            ];
        }
        
        error_log("📍 Generadas " . count($fields) . " posiciones por defecto");
        
        return $fields;
    }
    
    /**
     * Calcula posición para placeholder antiguo
     */
    private static function getPositionForPlaceholder($placeholder, $index, $totalPlaceholders)
    {
        // Configuración base
        $baseY = 75; // Porcentaje desde arriba
        $fieldWidth = 32;
        $fieldHeight = 7;
        
        // Calcular posición X basada en el número de firmantes
        if ($totalPlaceholders === 1) {
            // Un firmante: centrado
            $x = 34; // Centro aproximado
        } elseif ($totalPlaceholders === 2) {
            // Dos firmantes: lado a lado
            $positions = [15, 53];
            $x = $positions[$index - 1] ?? 15;
        } else {
            // Más firmantes: distribuir en filas
            $positions = [15, 53, 15, 53];
            $x = $positions[$index - 1] ?? 15;
            
            // Si es fila 2, ajustar Y
            if ($index > 2) {
                $baseY = 85;
            }
        }
        
        return [
            'x' => $x,
            'y' => $baseY,
            'width' => $fieldWidth,
            'height' => $fieldHeight
        ];
    }
    
    /**
     * Obtiene el número de páginas de un PDF
     */
    private static function getPdfPageCount($pdfPath)
    {
        try {
            $content = file_get_contents($pdfPath);
            
            // Método 1: Buscar el marcador de páginas
            if (preg_match_all('/\/Type\s*\/Page[^s]/', $content, $matches)) {
                $count = count($matches[0]);
                if ($count > 0) {
                    return $count;
                }
            }
            
            // Método 2: Buscar /Count
            if (preg_match('/\/Count\s+(\d+)/', $content, $matches)) {
                return (int)$matches[1];
            }
            
            // Método 3: Usar pdfinfo si está disponible
            $output = [];
            exec("pdfinfo " . escapeshellarg($pdfPath) . " 2>/dev/null | grep 'Pages'", $output);
            if (!empty($output)) {
                if (preg_match('/Pages:\s*(\d+)/', $output[0], $matches)) {
                    return (int)$matches[1];
                }
            }
            
            // Fallback: asumir 10 páginas (valor típico de contrato con anexos)
            error_log("⚠️ No se pudo determinar número de páginas, usando 10 por defecto");
            return 10;
            
        } catch (Exception $e) {
            error_log("⚠️ Error obteniendo páginas: " . $e->getMessage());
            return 10;
        }
    }
    
    /**
     * Valida que las coordenadas extraídas sean coherentes
     */
    public static function validateFields($fields, $expectedSigners)
    {
        $issues = [];
        
        if (count($fields) === 0) {
            $issues[] = "No se encontraron campos de firma en el PDF";
        }
        
        // Con múltiples campos por firmante, esperamos más campos que firmantes
        // Típicamente: firmantes * secciones (ej: 2 firmantes * 4 secciones = 8 campos)
        $uniqueRecipients = [];
        foreach ($fields as $field) {
            $uniqueRecipients[$field['recipient_id']] = true;
        }
        $numUniqueRecipients = count($uniqueRecipients);
        
        if ($numUniqueRecipients !== $expectedSigners) {
            $issues[] = "Se esperaban {$expectedSigners} firmantes únicos pero se encontraron {$numUniqueRecipients}";
        }
        
        foreach ($fields as $i => $field) {
            // Validar que las coordenadas estén en rango válido
            $pos = $field['position'];
            if ($pos['x'] < 0 || $pos['x'] > 100) {
                $issues[] = "Campo " . ($i+1) . ": coordenada X fuera de rango ({$pos['x']}%)";
            }
            if ($pos['y'] < 0 || $pos['y'] > 100) {
                $issues[] = "Campo " . ($i+1) . ": coordenada Y fuera de rango ({$pos['y']}%)";
            }
            if ($pos['width'] < 5 || $pos['width'] > 50) {
                $issues[] = "Campo " . ($i+1) . ": ancho fuera de rango ({$pos['width']}%)";
            }
            if ($pos['height'] < 2 || $pos['height'] > 20) {
                $issues[] = "Campo " . ($i+1) . ": alto fuera de rango ({$pos['height']}%)";
            }
        }
        
        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'total_fields' => count($fields),
            'unique_recipients' => $numUniqueRecipients,
            'fields_per_recipient' => count($fields) > 0 ? count($fields) / max(1, $numUniqueRecipients) : 0
        ];
    }
    
    /**
     * Agrupa los campos por recipient_id para debugging
     */
    public static function groupFieldsByRecipient($fields)
    {
        $grouped = [];
        
        foreach ($fields as $field) {
            $rid = $field['recipient_id'];
            if (!isset($grouped[$rid])) {
                $grouped[$rid] = [];
            }
            $pos = $field['position'];
            $grouped[$rid][] = [
                'page' => $field['page_number'],
                'position' => "x={$pos['x']}%, y={$pos['y']}%"
            ];
        }
        
        return $grouped;
    }
}