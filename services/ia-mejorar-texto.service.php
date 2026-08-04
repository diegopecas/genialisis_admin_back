<?php
class IaMejorarTexto
{
    /**
     * Mejora la redacción de un texto con IA aplicando estilo técnico docente.
     * Body: { texto: string, contexto?: string }
     * Respuesta: { success, texto_mejorado, proveedor, tiempo_ms }
     */
    public static function mejorar()
    {
        try {
            $db = Flight::db();
            $data = json_decode(Flight::request()->getBody(), true);

            $texto = trim($data['texto'] ?? '');
            $contexto = trim($data['contexto'] ?? '');

            if ($texto === '') {
                Flight::json(["error" => "El campo texto es requerido"], 400);
                return;
            }

            $bloqueContexto = $contexto !== ''
                ? "CONTEXTO ADICIONAL (úsalo solo como referencia, no lo incluyas en la respuesta):\n\"{$contexto}\"\n\n"
                : "";

            $prompt = <<<PROMPT
Eres un redactor experto en lenguaje técnico docente para instituciones educativas de preescolar y primaria en Colombia.

{$bloqueContexto}TEXTO ORIGINAL A MEJORAR:
"{$texto}"

REGLAS:
1. Mantén TODOS los hechos del texto original. No inventes información ni agregues detalles que no estén.
2. Mejora la redacción, ortografía, puntuación y claridad.
3. Usa lenguaje objetivo, profesional y técnico docente.
4. Evita juicios de valor injustificados y términos que generen incomodidad al lector (por ejemplo: "agresión", "ataque", "violento", "tonto", "malo", "pésimo", "fatal"). Reemplázalos por equivalentes profesionales como "comportamiento disruptivo", "conducta inadecuada", "que requiere apoyo", "que necesita acompañamiento".
5. Mantén aproximadamente la misma longitud del texto original.
6. NO uses formato markdown (sin asteriscos, sin negritas, sin encabezados, sin viñetas).
7. NO incluyas prefijos como "Observación:", "Texto mejorado:", ni comentarios adicionales.
8. Responde ÚNICAMENTE con el texto mejorado en texto plano.
PROMPT;

            $config = self::obtenerConfiguracion($db);
            $inicio_tiempo = microtime(true);
            $respuesta_ia = self::llamarIA($config, $prompt);
            $tiempo_ms = round((microtime(true) - $inicio_tiempo) * 1000);

            if (!$respuesta_ia['success']) {
                Flight::json([
                    "error" => "Error al mejorar el texto: " . ($respuesta_ia['error'] ?? 'desconocido')
                ], 500);
                return;
            }

            $textoMejorado = trim($respuesta_ia['respuesta']);
            $textoMejorado = preg_replace('/^```(?:\w+)?\s*/s', '', $textoMejorado);
            $textoMejorado = preg_replace('/\s*```$/s', '', $textoMejorado);
            $textoMejorado = trim($textoMejorado, " \t\n\r\0\x0B\"'");

            if ($textoMejorado === '') {
                Flight::json([
                    "error" => "La IA devolvió una respuesta vacía",
                    "proveedor" => $respuesta_ia['proveedor'],
                    "tiempo_ms" => $tiempo_ms
                ], 500);
                return;
            }

            Flight::json([
                "success" => true,
                "texto_mejorado" => $textoMejorado,
                "proveedor" => $respuesta_ia['proveedor'],
                "tiempo_ms" => $tiempo_ms
            ]);

        } catch (Exception $e) {
            error_log("Error en IaMejorarTexto::mejorar: " . $e->getMessage());
            Flight::json(["error" => $e->getMessage()], 500);
        }
    }

    private static function obtenerConfiguracion($db)
    {
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);
        $config = [];
        foreach ($rows as $row) {
            $config[$row['clave']] = $row['valor'];
        }
        return $config;
    }

    private static function llamarIA($config, $prompt)
    {
        $gemini_key = $config['gemini_api_key'] ?? null;
        if ($gemini_key) {
            $resultado = self::llamarGemini($gemini_key, $prompt);
            if ($resultado['success']) {
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => "gemini"];
            }
            error_log("IaMejorarTexto - Gemini falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        $groq_key = $config['groq_api_key'] ?? null;
        if ($groq_key) {
            $resultado = self::llamarGroq($groq_key, $prompt);
            if ($resultado['success']) {
                return ["success" => true, "respuesta" => $resultado['respuesta'], "proveedor" => "groq"];
            }
            error_log("IaMejorarTexto - Groq falló: " . ($resultado['error'] ?? 'desconocido'));
        }

        return ["success" => false, "error" => "No hay proveedores de IA disponibles"];
    }

    private static function llamarGemini($api_key, $prompt)
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $api_key;

            $body = json_encode([
                "contents" => [["role" => "user", "parts" => [["text" => $prompt]]]],
                "generationConfig" => ["temperature" => 0.3, "maxOutputTokens" => 2048]
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code . " - " . substr($response, 0, 300)];
            }

            $data = json_decode($response, true);

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return ["success" => true, "respuesta" => trim($data['candidates'][0]['content']['parts'][0]['text'])];
            }

            return ["success" => false, "error" => "Formato inesperado de Gemini"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    private static function llamarGroq($api_key, $prompt)
    {
        try {
            $url = "https://api.groq.com/openai/v1/chat/completions";

            $body = json_encode([
                "model" => "llama-3.3-70b-versatile",
                "messages" => [
                    ["role" => "system", "content" => "Eres un redactor experto en lenguaje técnico docente. Responde SOLO con el texto mejorado en texto plano, sin markdown ni comentarios adicionales."],
                    ["role" => "user", "content" => $prompt]
                ],
                "temperature" => 0.3,
                "max_tokens" => 2048
            ]);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $api_key]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code !== 200) {
                return ["success" => false, "error" => "HTTP " . $http_code];
            }

            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                return ["success" => true, "respuesta" => trim($data['choices'][0]['message']['content'])];
            }

            return ["success" => false, "error" => "Formato inesperado de Groq"];
        } catch (Exception $e) {
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}