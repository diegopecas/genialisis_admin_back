<?php
/**
 * IaVision — capa de proveedores de IA con visión (leer imágenes o PDF).
 *
 * La cadena de proveedores NO está quemada en el código: se lee de la clave
 * ia_vision_cadena en ia_configuracion. Cada paso es "proveedor|modelo", y los
 * pasos se separan con ";" o con saltos de línea. Ejemplo:
 *
 *   gemini|gemini-2.5-flash-lite;openrouter|nvidia/nemotron-nano-12b-v2-vl:free;qwen|qwen-vl-plus
 *
 * IaVision recorre la cadena en orden e intenta cada paso hasta que uno
 * responda. Reordenar, agregar o quitar proveedores se hace editando esa clave
 * en la tabla, sin tocar código. Solo agregar un TIPO de proveedor nuevo
 * (uno que hable un formato distinto) requiere código.
 *
 * Proveedores que entiende hoy:
 *   - gemini      -> formato Gemini (único que procesa PDF). Key: gemini_api_key.
 *   - openrouter  -> formato OpenAI-compatible. Key: openrouter_api_key.
 *   - qwen        -> formato OpenAI-compatible (DashScope directo, URL dedicada por
 *                    tenant). Keys: qwen_api_key + qwen_base_url (base hasta /v1).
 *   - groq        -> formato OpenAI-compatible. Key: groq_api_key.
 *
 * No conoce reglas de negocio (montos, pagos, etc.): recibe imagen + prompt y
 * devuelve el texto crudo del modelo, el proveedor que respondió y los tokens.
 * Interpretar ese texto es tarea de quien la llama.
 */
class IaVision
{
    // Valores por defecto; solo se usan si falta la clave en ia_configuracion.
    const DEFAULT_REINTENTOS = 2;
    const DEFAULT_ESPERA_MS  = 1000;
    // Timeout por llamada en segundos; se sobreescribe con la clave ia_vision_timeout.
    const TIMEOUT_SEGUNDOS   = 30;

    // URLs de cada proveedor compatible con OpenAI (constantes técnicas del proveedor).
    const URL_OPENROUTER = 'https://openrouter.ai/api/v1/chat/completions';
    const URL_GROQ       = 'https://api.groq.com/openai/v1/chat/completions';

    /**
     * Lee un documento (imagen o PDF) con un prompt, recorriendo la cadena de
     * proveedores configurada en ia_vision_cadena hasta que uno responda.
     *
     * @param array  $config   Config de ia_configuracion como [clave => valor].
     * @param string $base64   Contenido del archivo en base64.
     * @param string $mimeType MIME del archivo (image/jpeg, image/png, application/pdf).
     * @param string $prompt   Instrucción para el modelo.
     * @param bool   $esPdf    true si el archivo es PDF (solo Gemini lo procesa).
     * @param int    $maxTokens Límite de tokens de la respuesta del modelo. Por
     *                          defecto 500 (suficiente para un comprobante). Se sube
     *                          cuando la respuesta esperada es más larga (por ejemplo
     *                          leer un registro civil con niño + padres).
     * @return array [
     *   'success'   => bool,
     *   'texto'     => string|null,   // texto crudo devuelto por el modelo
     *   'proveedor' => string|null,   // 'gemini' | 'openrouter' | 'qwen' | 'groq'
     *   'tokens'    => ['input'=>int, 'output'=>int, 'total'=>int],
     *   'error'     => string|null    // motivo si ningún proveedor respondió
     * ]
     */
    public static function extraerDeImagen($config, $base64, $mimeType, $prompt, $esPdf = false, $maxTokens = 500)
    {
        $tokensVacios = array('input' => 0, 'output' => 0, 'total' => 0);

        $cadenaRaw = isset($config['ia_vision_cadena']) ? trim($config['ia_vision_cadena']) : '';
        if ($cadenaRaw === '') {
            return array('success' => false, 'texto' => null, 'proveedor' => null, 'tokens' => $tokensVacios,
                'error' => 'No hay cadena de proveedores de IA configurada (ia_vision_cadena)', 'fallidos' => array());
        }

        $pasos = self::parsearCadena($cadenaRaw);
        if (empty($pasos)) {
            return array('success' => false, 'texto' => null, 'proveedor' => null, 'tokens' => $tokensVacios,
                'error' => 'La cadena ia_vision_cadena no tiene pasos válidos (formato esperado: proveedor|modelo)', 'fallidos' => array());
        }

        $ultimoError = 'Ningún proveedor de la cadena pudo procesar el documento';
        $fallidos = array();

        // Timeout por llamada, configurable por tenant; si falta la clave se usa el default.
        $timeout = (isset($config['ia_vision_timeout']) && $config['ia_vision_timeout'] !== '')
            ? intval($config['ia_vision_timeout'])
            : self::TIMEOUT_SEGUNDOS;

        foreach ($pasos as $paso) {
            $proveedor = $paso['proveedor'];
            $modelo = $paso['modelo'];

            // El PDF solo lo procesa Gemini; los demás proveedores se saltan.
            if ($esPdf && $proveedor !== 'gemini') {
                error_log("IaVision - se salta '$proveedor' porque no procesa PDF");
                continue;
            }

            $r = null;

            if ($proveedor === 'gemini') {
                $key = isset($config['gemini_api_key']) ? $config['gemini_api_key'] : null;
                if (!$key) {
                    error_log("IaVision - se salta 'gemini': falta gemini_api_key");
                    continue;
                }
                $reintentos = (isset($config['gemini_reintentos']) && $config['gemini_reintentos'] !== '')
                    ? intval($config['gemini_reintentos'])
                    : self::DEFAULT_REINTENTOS;
                $esperaMs = (isset($config['gemini_espera_ms']) && $config['gemini_espera_ms'] !== '')
                    ? intval($config['gemini_espera_ms'])
                    : self::DEFAULT_ESPERA_MS;
                $r = self::llamarGeminiVision($key, $modelo, $base64, $mimeType, $prompt, $reintentos, $esperaMs, $timeout, $maxTokens);
            } elseif ($proveedor === 'openrouter') {
                $key = isset($config['openrouter_api_key']) ? $config['openrouter_api_key'] : null;
                if (!$key) {
                    error_log("IaVision - se salta 'openrouter': falta openrouter_api_key");
                    continue;
                }
                $r = self::llamarOpenAICompatibleVision(self::URL_OPENROUTER, $key, $modelo, $base64, $mimeType, $prompt, $timeout, $maxTokens);
            } elseif ($proveedor === 'qwen') {
                $key = isset($config['qwen_api_key']) ? $config['qwen_api_key'] : null;
                $baseUrl = isset($config['qwen_base_url']) ? trim($config['qwen_base_url']) : null;
                if (!$key) {
                    error_log("IaVision - se salta 'qwen': falta qwen_api_key");
                    continue;
                }
                if (!$baseUrl) {
                    error_log("IaVision - se salta 'qwen': falta qwen_base_url");
                    continue;
                }
                // Cada tenant tiene su propia URL dedicada de DashScope (qwen_base_url,
                // hasta /v1); aquí se le agrega el path del endpoint de chat.
                $url = rtrim($baseUrl, '/') . '/chat/completions';
                $r = self::llamarOpenAICompatibleVision($url, $key, $modelo, $base64, $mimeType, $prompt, $timeout, $maxTokens);
            } elseif ($proveedor === 'groq') {
                $key = isset($config['groq_api_key']) ? $config['groq_api_key'] : null;
                if (!$key) {
                    error_log("IaVision - se salta 'groq': falta groq_api_key");
                    continue;
                }
                $r = self::llamarOpenAICompatibleVision(self::URL_GROQ, $key, $modelo, $base64, $mimeType, $prompt, $timeout, $maxTokens);
            } else {
                error_log("IaVision - proveedor desconocido en la cadena: '$proveedor'");
                continue;
            }

            if ($r['success']) {
                return array('success' => true, 'texto' => $r['texto'], 'proveedor' => $proveedor, 'tokens' => $r['tokens'], 'error' => null, 'fallidos' => $fallidos);
            }

            $fallidos[] = array('proveedor' => $proveedor, 'error' => $r['error']);
            $ultimoError = $proveedor . ': ' . $r['error'];
            error_log("IaVision - '$proveedor' falló: " . $r['error']);
        }

        return array('success' => false, 'texto' => null, 'proveedor' => null, 'tokens' => $tokensVacios, 'error' => $ultimoError, 'fallidos' => $fallidos);
    }

    /**
     * Registra el uso por proveedor en la clave ia_vision_uso (JSON) del tenant.
     * Por cada proveedor acumula: total (histórico, nunca se borra) y dia (se
     * reinicia al cambiar de fecha), cada uno con llamadas, tokens y fallos.
     * Es best-effort: si algo falla, se registra en el log pero NUNCA rompe el
     * flujo que la llamó.
     *
     * @param PDO   $db        Conexión (Flight::db()).
     * @param int   $idTenant  Tenant.
     * @param array $resultado Lo que devolvió extraerDeImagen (usa 'success',
     *                         'proveedor', 'tokens' y 'fallidos').
     * @return void
     */
    public static function registrarUso($db, $idTenant, $resultado)
    {
        try {
            $hoy = date('Y-m-d');

            $stmt = $db->prepare("SELECT valor FROM ia_configuracion WHERE clave = 'ia_vision_uso' AND id_tenant = :t LIMIT 1");
            $stmt->bindValue(':t', $idTenant, PDO::PARAM_INT);
            $stmt->execute();
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);

            $existe = ($fila !== false);
            $uso = $existe ? json_decode($fila['valor'], true) : null;
            if (!is_array($uso)) {
                $uso = array('fecha_dia' => $hoy);
            }

            // Reinicio diario: al cambiar la fecha, poner en cero los "dia" de todos.
            if (!isset($uso['fecha_dia']) || $uso['fecha_dia'] !== $hoy) {
                foreach ($uso as $claveProv => $datos) {
                    if ($claveProv === 'fecha_dia') {
                        continue;
                    }
                    $uso[$claveProv]['dia'] = array('llamadas' => 0, 'tokens' => 0, 'fallos' => 0);
                }
                $uso['fecha_dia'] = $hoy;
            }

            // Garantiza la estructura de un proveedor antes de sumarle.
            $asegurar = function (&$uso, $prov) {
                if (!isset($uso[$prov]) || !is_array($uso[$prov])) {
                    $uso[$prov] = array(
                        'total' => array('llamadas' => 0, 'tokens' => 0, 'fallos' => 0),
                        'dia'   => array('llamadas' => 0, 'tokens' => 0, 'fallos' => 0)
                    );
                }
            };

            // Proveedor que acertó: +1 llamada y +tokens (total y dia).
            if (!empty($resultado['success']) && !empty($resultado['proveedor'])) {
                $p = $resultado['proveedor'];
                $asegurar($uso, $p);
                $tk = isset($resultado['tokens']['total']) ? intval($resultado['tokens']['total']) : 0;
                $uso[$p]['total']['llamadas'] += 1;
                $uso[$p]['total']['tokens'] += $tk;
                $uso[$p]['dia']['llamadas'] += 1;
                $uso[$p]['dia']['tokens'] += $tk;
            }

            // Proveedores que fallaron: +1 fallo cada uno (total y dia) y se guarda
            // el ultimo error, recortado para no inflar el JSON.
            if (!empty($resultado['fallidos']) && is_array($resultado['fallidos'])) {
                foreach ($resultado['fallidos'] as $f) {
                    $p = is_array($f) ? $f['proveedor'] : $f;
                    $asegurar($uso, $p);
                    $uso[$p]['total']['fallos'] += 1;
                    $uso[$p]['dia']['fallos'] += 1;
                    if (is_array($f) && isset($f['error'])) {
                        $uso[$p]['ultimo_error'] = mb_substr(trim($f['error']), 0, 120);
                        $uso[$p]['ultimo_error_fecha'] = date('Y-m-d H:i');
                    }
                }
            }

            $json = json_encode($uso, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if ($existe) {
                $up = $db->prepare("UPDATE ia_configuracion SET valor = :v, fecha_actualizacion = NOW() WHERE clave = 'ia_vision_uso' AND id_tenant = :t");
                $up->bindValue(':v', $json);
                $up->bindValue(':t', $idTenant, PDO::PARAM_INT);
                $up->execute();
            } else {
                $ins = $db->prepare("INSERT INTO ia_configuracion (id, id_tenant, clave, valor, descripcion, fecha_actualizacion) VALUES (UUID(), :t, 'ia_vision_uso', :v, 'Uso de IA de vision por proveedor (JSON: total/dia con llamadas, tokens, fallos)', NOW())");
                $ins->bindValue(':t', $idTenant, PDO::PARAM_INT);
                $ins->bindValue(':v', $json);
                $ins->execute();
            }
        } catch (Exception $e) {
            error_log("IaVision - registrarUso falló (no afecta la lectura): " . $e->getMessage());
        }
    }

    /**
     * Parsea la cadena de ia_vision_cadena a una lista ordenada de pasos.
     * Pasos separados por ";" o saltos de línea; cada paso es "proveedor|modelo".
     * Los pasos mal formados se ignoran.
     *
     * @return array Lista de ['proveedor' => string, 'modelo' => string]
     */
    private static function parsearCadena($cadena)
    {
        $cadena = str_replace(array("\r\n", "\r"), "\n", $cadena);
        $trozos = preg_split('/[;\n]+/', $cadena);

        $pasos = array();
        foreach ($trozos as $trozo) {
            $trozo = trim($trozo);
            if ($trozo === '') {
                continue;
            }
            $partes = explode('|', $trozo, 2);
            if (count($partes) !== 2) {
                continue;
            }
            $proveedor = strtolower(trim($partes[0]));
            $modelo = trim($partes[1]);
            if ($proveedor === '' || $modelo === '') {
                continue;
            }
            $pasos[] = array('proveedor' => $proveedor, 'modelo' => $modelo);
        }
        return $pasos;
    }

    /**
     * Llama a Gemini (visión) con reintentos y espera exponencial.
     * Solo reintenta ante 503 (modelo saturado) o error de conexión; los demás
     * errores no se reintentan porque reintentar no ayudaría.
     */
    private static function llamarGeminiVision($apiKey, $modelo, $base64, $mimeType, $prompt, $reintentos, $esperaMs, $timeout, $maxTokens = 500)
    {
        $tokens = array('input' => 0, 'output' => 0, 'total' => 0);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . $modelo . ":generateContent?key=" . $apiKey;

        $payload = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('inlineData' => array('mimeType' => $mimeType, 'data' => $base64)),
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array('temperature' => 0.1, 'maxOutputTokens' => $maxTokens)
        );
        $body = json_encode($payload);

        $ultimoError = 'desconocido';

        // Un intento inicial + $reintentos reintentos. La espera exponencial
        // (esperaMs, 2*esperaMs, ...) se aplica ANTES de cada reintento.
        for ($intento = 0; $intento <= $reintentos; $intento++) {
            if ($intento > 0) {
                usleep(intval($esperaMs * 1000 * pow(2, $intento - 1)));
            }

            try {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);

                if ($curlError) {
                    $ultimoError = 'conexión: ' . $curlError; // transitorio -> reintentar
                    continue;
                }

                if ($httpCode === 503) {
                    $ultimoError = 'HTTP 503 (modelo saturado)'; // transitorio -> reintentar
                    continue;
                }

                if ($httpCode !== 200) {
                    // Otro error (400, 401, 500, ...): reintentar no ayuda.
                    return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => 'HTTP ' . $httpCode);
                }

                $data = json_decode($response, true);
                if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                    return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => 'formato de respuesta inesperado');
                }

                if (isset($data['usageMetadata'])) {
                    $tokens['input'] = intval(isset($data['usageMetadata']['promptTokenCount']) ? $data['usageMetadata']['promptTokenCount'] : 0);
                    $tokens['output'] = intval(isset($data['usageMetadata']['candidatesTokenCount']) ? $data['usageMetadata']['candidatesTokenCount'] : 0);
                    $tokens['total'] = $tokens['input'] + $tokens['output'];
                }

                return array('success' => true, 'texto' => trim($data['candidates'][0]['content']['parts'][0]['text']), 'tokens' => $tokens, 'error' => null);
            } catch (Exception $e) {
                $ultimoError = $e->getMessage(); // por si es transitorio, se reintenta
                continue;
            }
        }

        return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => $ultimoError);
    }

    /**
     * Llama a un proveedor compatible con la API de OpenAI (OpenRouter, Qwen/DashScope,
     * Groq) en modo visión. La imagen va como data URL en base64. Un mismo método
     * sirve para todos porque comparten el formato: solo cambian URL, key y modelo.
     */
    private static function llamarOpenAICompatibleVision($url, $apiKey, $modelo, $base64, $mimeType, $prompt, $timeout, $maxTokens = 500)
    {
        $tokens = array('input' => 0, 'output' => 0, 'total' => 0);

        $payload = array(
            'model' => $modelo,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => array(
                        array('type' => 'text', 'text' => $prompt),
                        array('type' => 'image_url', 'image_url' => array('url' => 'data:' . $mimeType . ';base64,' . $base64))
                    )
                )
            ),
            'temperature' => 0.1,
            'max_tokens' => $maxTokens
        );
        $body = json_encode($payload);

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => 'conexión: ' . $curlError);
            }
            if ($httpCode !== 200) {
                return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => 'HTTP ' . $httpCode);
            }

            $data = json_decode($response, true);
            if (!isset($data['choices'][0]['message']['content'])) {
                return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => 'formato de respuesta inesperado');
            }

            if (isset($data['usage'])) {
                $tokens['input'] = intval(isset($data['usage']['prompt_tokens']) ? $data['usage']['prompt_tokens'] : 0);
                $tokens['output'] = intval(isset($data['usage']['completion_tokens']) ? $data['usage']['completion_tokens'] : 0);
                $tokens['total'] = intval(isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : ($tokens['input'] + $tokens['output']));
            }

            return array('success' => true, 'texto' => trim($data['choices'][0]['message']['content']), 'tokens' => $tokens, 'error' => null);
        } catch (Exception $e) {
            return array('success' => false, 'texto' => null, 'tokens' => $tokens, 'error' => $e->getMessage());
        }
    }
}