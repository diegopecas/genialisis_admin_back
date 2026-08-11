<?php
/**
 * MigracionIa
 *
 * El chat del modulo. Sigue el patron de IaChat: cadena de proveedores
 * configurable desde ia_configuracion ("proveedor|modelo;proveedor|modelo")
 * y se cae al siguiente si uno falla. Se agrega 'anthropic' porque es el
 * proveedor que pidio Genialisis para esto.
 *
 * La diferencia con IaChat es el contexto. Aqui el asistente recibe:
 *   1. El esquema en vivo del bloque en curso (INFORMATION_SCHEMA)
 *   2. Los catalogos reales del destino
 *   3. El indice del codigo de Genialisis producto (no el codigo)
 *   4. El texto de los archivos que entrego el jardin
 *   5. El expediente: que ya se resolvio y que falta
 *
 * Y una regla dura: propone SQL, no lo ejecuta. La escritura pasa por
 * MigracionScripts, que valida y exige aprobacion.
 */
class MigracionIa
{
    const URL_ANTHROPIC  = 'https://api.anthropic.com/v1/messages';
    const URL_OPENROUTER = 'https://openrouter.ai/api/v1/chat/completions';
    const URL_GROQ       = 'https://api.groq.com/openai/v1/chat/completions';

    // =====================================================
    // ENDPOINTS
    // =====================================================

    public static function enviarMensaje()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $data = Flight::request()->data;
            $idSesion = $data['id_sesion'] ?? null;
            $mensaje = trim($data['mensaje'] ?? '');
            $bloque = $data['bloque'] ?? null;

            if (!$idSesion || $mensaje === '') {
                Flight::json(['error' => 'Faltan la sesión o el mensaje'], 400);
                return;
            }

            $sesion = MigracionDb::sesion($idSesion);
            MigracionDb::exigirSesionAbierta($sesion);

            $db = MigracionDb::mig();
            $config = self::obtenerConfiguracion();

            self::guardarMensaje($idSesion, 'user', $mensaje);

            $contexto = self::armarContexto($sesion, $bloque);
            $historial = self::obtenerHistorial($idSesion, 12);

            $inicio = microtime(true);
            $respuesta = self::llamarIA($config, $contexto, $historial, $mensaje);
            $tiempo = round((microtime(true) - $inicio) * 1000);

            self::guardarMensaje(
                $idSesion,
                'assistant',
                $respuesta['respuesta'],
                $respuesta['proveedor'],
                $respuesta['modelo'] ?? null,
                $respuesta['tokens_entrada'] ?? null,
                $respuesta['tokens_salida'] ?? null,
                $tiempo
            );

            // Si la respuesta trae SQL en un bloque marcado, se guarda como
            // script propuesto y se valida de una vez. Nunca se ejecuta aqui.
            $script = null;
            $sql = self::extraerSql($respuesta['respuesta']);
            if ($sql !== null) {
                $script = MigracionScripts::guardar(
                    $idSesion,
                    $bloque,
                    'Propuesto por el asistente',
                    $sql,
                    self::extraerResumen($respuesta['respuesta'])
                );
            }

            // Preguntas que el asistente dejo abiertas.
            $preguntas = self::extraerPreguntas($respuesta['respuesta']);
            foreach ($preguntas as $p) {
                self::guardarPregunta($idSesion, $bloque, $p);
            }

            Flight::json([
                'success' => true,
                'respuesta' => $respuesta['respuesta'],
                'proveedor' => $respuesta['proveedor'],
                'modelo' => $respuesta['modelo'] ?? null,
                'tiempo_ms' => $tiempo,
                'tokens_entrada' => $respuesta['tokens_entrada'] ?? null,
                'tokens_salida' => $respuesta['tokens_salida'] ?? null,
                'script' => $script,
                'preguntas_nuevas' => count($preguntas)
            ]);
        } catch (Exception $e) {
            error_log("MigracionIa::enviarMensaje - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function historial()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $idSesion = Flight::request()->query['id_sesion'] ?? null;
            if (!$idSesion) {
                Flight::json(['error' => 'Falta el id de la sesión'], 400);
                return;
            }

            $db = MigracionDb::mig();
            $stmt = $db->prepare("SELECT id, rol, mensaje, proveedor, modelo,
                                         tokens_entrada, tokens_salida, tiempo_ms, fecha
                                  FROM mig_mensajes WHERE id_sesion = :id ORDER BY fecha");
            $stmt->bindValue(':id', $idSesion);
            $stmt->execute();
            Flight::json($stmt->fetchAll());
        } catch (Exception $e) {
            error_log("MigracionIa::historial - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Costo acumulado de la sesion en tokens. Sirve para verificar en la
     * practica lo que dijo el analisis: centavos por jardin.
     */
    public static function consumo()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $idSesion = Flight::request()->query['id_sesion'] ?? null;
            if (!$idSesion) {
                Flight::json(['error' => 'Falta el id de la sesión'], 400);
                return;
            }

            $db = MigracionDb::mig();
            $stmt = $db->prepare("SELECT COUNT(*) AS turnos,
                                         COALESCE(SUM(tokens_entrada), 0) AS tokens_entrada,
                                         COALESCE(SUM(tokens_salida), 0) AS tokens_salida,
                                         COALESCE(AVG(tiempo_ms), 0) AS tiempo_promedio
                                  FROM mig_mensajes WHERE id_sesion = :id AND rol = 'assistant'");
            $stmt->bindValue(':id', $idSesion);
            $stmt->execute();
            Flight::json($stmt->fetch(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            error_log("MigracionIa::consumo - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function responderPregunta()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $data = Flight::request()->data;
            $id = $data['id'] ?? null;
            $respuesta = $data['respuesta'] ?? '';

            if (!$id || trim($respuesta) === '') {
                Flight::json(['error' => 'Faltan la pregunta o la respuesta'], 400);
                return;
            }

            $db = MigracionDb::mig();
            $stmt = $db->prepare("UPDATE mig_preguntas
                                  SET respuesta = :respuesta, estado = 'respondida', fecha_respuesta = NOW()
                                  WHERE id = :id");
            $stmt->bindValue(':respuesta', $respuesta);
            $stmt->bindValue(':id', $id);
            $stmt->execute();

            Flight::json(['success' => true, 'id' => $id]);
        } catch (Exception $e) {
            error_log("MigracionIa::responderPregunta - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =====================================================
    // CONTEXTO
    // =====================================================

    /**
     * Arma el contexto completo. Esto es la pieza que hace que la
     * conversacion sirva: sin el expediente, el asistente se le olvida
     * todo y pregunta tres veces el NIT.
     */
    public static function armarContexto($sesion, $bloque = null)
    {
        $partes = [];

        $partes[] = self::instrucciones($sesion, $bloque);

        // 1. Esquema en vivo del bloque
        try {
            $esquema = MigracionEsquema::leerEsquema($sesion['id_conexion'], $bloque);
            $partes[] = "== ESQUEMA REAL DE LA BASE DESTINO ==\n"
                . "Base: {$esquema['base_datos']} | Tablas: {$esquema['total_tablas']}\n"
                . self::esquemaATexto($esquema['esquema']);
        } catch (Exception $e) {
            $partes[] = "== ESQUEMA ==\nNo se pudo leer el esquema del destino: " . $e->getMessage();
        }

        // 2. Catalogos reales
        try {
            $catalogos = MigracionEsquema::leerCatalogos($sesion);
            $partes[] = "== CATÁLOGOS DEL DESTINO ==\n" . self::catalogosATexto($catalogos['catalogos']);
        } catch (Exception $e) {
            $partes[] = "== CATÁLOGOS ==\nNo se pudieron leer: " . $e->getMessage();
        }

        // 3. Indice del codigo (no el codigo)
        $indice = MigracionCodigo::indiceParaContexto('back', 250);
        if ($indice !== '') {
            $partes[] = "== ÍNDICE DEL BACK DE GENIALISIS ==\n"
                . "No tienes el código completo. Si necesitas ver un archivo, pídelo así:\n"
                . "[PEDIR_ARCHIVO: services/estudiantes.service.php]\n\n" . $indice;
        }

        // 4. Archivos del jardin
        $textoArchivos = MigracionArchivos::textoParaContexto($sesion['id'], 15000);
        if ($textoArchivos !== '') {
            $partes[] = "== ARCHIVOS QUE ENTREGÓ EL CLIENTE ==\n" . $textoArchivos;
        }

        // 5. Expediente
        $partes[] = "== EXPEDIENTE DE LA SESIÓN ==\n" . self::expediente($sesion);

        return implode("\n\n", $partes);
    }

    private static function instrucciones($sesion, $bloque)
    {
        $idTenant = $sesion['id_tenant_destino'] !== null ? $sesion['id_tenant_destino'] : '(sin asignar todavía)';

        $texto = <<<TXT
Eres el asistente de migración de Genialisis. Acompañas a una persona del equipo
que ya se reunió con la directora del jardín y trae los archivos del cliente. Esa
persona conoce el negocio pero NO conoce el modelo de datos: no le hables de FK ni
de constraints, háblale de estudiantes, acudientes, grupos y pagos.

CLIENTE EN MONTAJE: {$sesion['nombre_cliente']}
TENANT DESTINO: {$sesion['codigo_tenant_destino']} (id_tenant = {$idTenant})
AÑO: {$sesion['anno']}
BLOQUE EN CURSO: {$bloque}

CÓMO TRABAJAS

1. No inventas. Si un dato no está en los archivos ni en la conversación, lo
   preguntas. Nunca lo rellenas con un valor plausible.
2. Declaras tu interpretación ANTES de aplicarla. Si en los archivos los grados
   vienen escritos de diez formas y entiendes que son cinco, lo dices y esperas
   confirmación.
3. Normalizas contra los catálogos REALES que tienes arriba, no contra lo que
   suena razonable. Si el catálogo de parentesco distingue abuela materna de
   paterna y el archivo solo dice "Abuela", eso es una pregunta.
4. Respetas el orden de dependencias: tenant, catálogos, académico, personas,
   contratos, cartera. Si te piden cargar estudiantes y los grupos todavía no
   existen, lo dices y no lo intentas.
5. El cruce entre archivos se hace por documento, nunca por nombre. Los nombres
   traen tildes, erratas y apellidos cambiados.
6. Cuando el PDF y el Excel se contradigan, manda el PDF: está firmado por los padres.

CÓMO ESCRIBES SQL

Propones el script; no lo ejecutas. Otra pieza lo valida y una persona lo aprueba.

Reglas que tu SQL debe cumplir o será rechazado automáticamente:
- Nada de DROP, TRUNCATE, ALTER, CREATE ni sentencias administrativas.
- Todo INSERT en una tabla que tenga id_tenant debe traerlo con el valor {$idTenant}.
- Todo UPDATE y DELETE debe filtrar por id_tenant = {$idTenant}.
- Las llaves primarias son char(36) con DEFAULT uuid(). Genera los UUID por
  adelantado con literales y encadena las FK con esos mismos literales. NO uses
  LAST_INSERT_ID(). Así el script sirve igual en pruebas y en producción.
- Sigue el patrón de Estudiantes::registroRapidoCompleto del back: buscar la
  persona por tipo y número de identificación antes de crearla, no pisar teléfono
  ni correo que ya existan, y verificar que el estudiante no exista antes de
  insertarlo. El script debe poder correrse dos veces sin duplicar.
- El usuario del portal de padres NO lo crea el registro rápido: se crea aparte
  en la tabla usuarios, con usuario y clave iguales al número de identificación.
  Si el bloque es de personas, inclúyelo.

FORMATO DE RESPUESTA

Habla normal, en español, con frases cortas. Cuando propongas un script,
enciérralo así:

[SQL]
INSERT INTO ...;
[/SQL]

Cuando necesites confirmación de algo antes de escribir, marca cada punto así:

[PREGUNTA] Los grados vienen en diez variantes ortográficas y entiendo que son cinco reales. ¿Confirmas?

Si necesitas ver un archivo del código, pídelo así y espera la respuesta:

[PEDIR_ARCHIVO: services/estudiantes.service.php]
TXT;

        return $texto;
    }

    private static function esquemaATexto($tablas)
    {
        $lineas = [];
        foreach ($tablas as $t) {
            $cols = [];
            foreach ($t['columnas'] as $c) {
                $marca = $c['llave'] === 'PRI' ? ' PK' : '';
                $nulo = $c['nulo'] ? '' : ' NOT NULL';
                $cols[] = "    {$c['nombre']} {$c['tipo']}{$nulo}{$marca}";
            }
            $tenant = $t['tiene_tenant'] ? ' [lleva id_tenant]' : ' [GLOBAL, sin id_tenant]';
            $linea = "{$t['tabla']}{$tenant}\n" . implode("\n", $cols);

            if (!empty($t['fk'])) {
                $fks = [];
                foreach ($t['fk'] as $fk) {
                    $fks[] = "{$fk['columna']} -> {$fk['tabla_referida']}.{$fk['columna_referida']}";
                }
                $linea .= "\n    FK: " . implode('; ', $fks);
            }
            $lineas[] = $linea;
        }
        return implode("\n\n", $lineas);
    }

    private static function catalogosATexto($catalogos)
    {
        $lineas = [];
        foreach ($catalogos as $c) {
            $marca = $c['global'] ? ' [GLOBAL]' : '';
            $valores = [];
            foreach (array_slice($c['filas'], 0, 60) as $fila) {
                $id = $fila['id'] ?? '?';
                $nombre = $fila['nombre'] ?? ($fila['descripcion'] ?? json_encode($fila, JSON_UNESCAPED_UNICODE));
                $valores[] = "{$id} = {$nombre}";
            }
            $lineas[] = "{$c['tabla']}{$marca} ({$c['total']}): " . implode(' | ', $valores);
        }
        return implode("\n", $lineas);
    }

    private static function expediente($sesion)
    {
        $db = MigracionDb::mig();
        $partes = [];

        $stmt = $db->prepare("SELECT codigo, nombre, estado FROM mig_bloques WHERE id_sesion = :id ORDER BY orden");
        $stmt->bindValue(':id', $sesion['id']);
        $stmt->execute();
        $bloques = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $bloques[] = "{$b['codigo']}: {$b['estado']}";
        }
        $partes[] = "Bloques: " . implode(' | ', $bloques);

        $stmt = $db->prepare("SELECT tabla_destino, estado, COUNT(*) AS total
                              FROM mig_registros WHERE id_sesion = :id
                              GROUP BY tabla_destino, estado");
        $stmt->bindValue(':id', $sesion['id']);
        $stmt->execute();
        $registros = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $registros[] = "{$r['tabla_destino']} ({$r['estado']}): {$r['total']}";
        }
        if ($registros) {
            $partes[] = "Registros ya resueltos: " . implode(' | ', $registros);
        }

        $stmt = $db->prepare("SELECT pregunta, respuesta, estado FROM mig_preguntas
                              WHERE id_sesion = :id ORDER BY fecha_creacion DESC LIMIT 25");
        $stmt->bindValue(':id', $sesion['id']);
        $stmt->execute();
        $preguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($preguntas) {
            $lista = [];
            foreach ($preguntas as $p) {
                $lista[] = $p['estado'] === 'respondida'
                    ? "RESUELTO: {$p['pregunta']} -> {$p['respuesta']}"
                    : "ABIERTO: {$p['pregunta']}";
            }
            $partes[] = "Preguntas:\n" . implode("\n", $lista);
        }

        $stmt = $db->prepare("SELECT codigo_bloque, filas_afectadas, exito, deshecho, fecha
                              FROM mig_ejecuciones WHERE id_sesion = :id ORDER BY fecha DESC LIMIT 10");
        $stmt->bindValue(':id', $sesion['id']);
        $stmt->execute();
        $ejecuciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($ejecuciones) {
            $lista = [];
            foreach ($ejecuciones as $e) {
                $estado = (int)$e['deshecho'] === 1 ? 'DESHECHO' : ((int)$e['exito'] === 1 ? 'OK' : 'FALLÓ');
                $lista[] = "{$e['fecha']} {$e['codigo_bloque']}: {$estado} ({$e['filas_afectadas']} filas)";
            }
            $partes[] = "Ya se ejecutó:\n" . implode("\n", $lista);
        }

        return implode("\n\n", $partes);
    }

    // =====================================================
    // EXTRACCION DE LA RESPUESTA
    // =====================================================

    public static function extraerSql($respuesta)
    {
        if (preg_match('/\[SQL\](.*?)\[\/SQL\]/s', $respuesta, $m)) {
            $sql = trim($m[1]);
            return $sql === '' ? null : $sql;
        }
        return null;
    }

    public static function extraerPreguntas($respuesta)
    {
        $preguntas = [];
        if (preg_match_all('/\[PREGUNTA\]\s*(.+)$/m', $respuesta, $m)) {
            foreach ($m[1] as $p) {
                $p = trim($p);
                if ($p !== '') {
                    $preguntas[] = $p;
                }
            }
        }
        return $preguntas;
    }

    private static function extraerResumen($respuesta)
    {
        // Si el asistente declara conteos, se guardan para poder
        // contrastarlos contra lo que realmente hizo el script.
        if (preg_match('/\[RESUMEN\](.*?)\[\/RESUMEN\]/s', $respuesta, $m)) {
            $json = json_decode(trim($m[1]), true);
            return $json ?: null;
        }
        return null;
    }

    // =====================================================
    // PERSISTENCIA
    // =====================================================

    private static function guardarMensaje($idSesion, $rol, $mensaje, $proveedor = null, $modelo = null,
                                           $tokensEntrada = null, $tokensSalida = null, $tiempo = null)
    {
        $db = MigracionDb::mig();
        $stmt = $db->prepare("INSERT INTO mig_mensajes
            (id, id_sesion, rol, mensaje, proveedor, modelo, tokens_entrada, tokens_salida, tiempo_ms)
            VALUES (:id, :id_sesion, :rol, :mensaje, :proveedor, :modelo, :te, :ts, :tiempo)");
        $stmt->bindValue(':id', Uuid::generar());
        $stmt->bindValue(':id_sesion', $idSesion);
        $stmt->bindValue(':rol', $rol);
        $stmt->bindValue(':mensaje', $mensaje);
        $stmt->bindValue(':proveedor', $proveedor);
        $stmt->bindValue(':modelo', $modelo);
        $stmt->bindValue(':te', $tokensEntrada, $tokensEntrada === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ts', $tokensSalida, $tokensSalida === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':tiempo', $tiempo, $tiempo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function guardarPregunta($idSesion, $codigoBloque, $pregunta)
    {
        $db = MigracionDb::mig();

        // No se repite una pregunta que ya esta abierta.
        $stmt = $db->prepare("SELECT id FROM mig_preguntas
                              WHERE id_sesion = :id_sesion AND pregunta = :pregunta AND estado = 'abierta'");
        $stmt->bindValue(':id_sesion', $idSesion);
        $stmt->bindValue(':pregunta', $pregunta);
        $stmt->execute();
        if ($stmt->fetch()) {
            return;
        }

        $idBloque = null;
        if ($codigoBloque) {
            $stmt = $db->prepare("SELECT id FROM mig_bloques WHERE id_sesion = :id_sesion AND codigo = :codigo");
            $stmt->bindValue(':id_sesion', $idSesion);
            $stmt->bindValue(':codigo', $codigoBloque);
            $stmt->execute();
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $idBloque = $fila ? $fila['id'] : null;
        }

        $stmt = $db->prepare("INSERT INTO mig_preguntas (id, id_sesion, id_bloque, pregunta, estado)
                              VALUES (:id, :id_sesion, :id_bloque, :pregunta, 'abierta')");
        $stmt->bindValue(':id', Uuid::generar());
        $stmt->bindValue(':id_sesion', $idSesion);
        $stmt->bindValue(':id_bloque', $idBloque);
        $stmt->bindValue(':pregunta', $pregunta);
        $stmt->execute();
    }

    private static function obtenerHistorial($idSesion, $limite = 12)
    {
        $db = MigracionDb::mig();
        $stmt = $db->prepare("SELECT rol, mensaje FROM mig_mensajes
                              WHERE id_sesion = :id ORDER BY fecha DESC LIMIT {$limite}");
        $stmt->bindValue(':id', $idSesion);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Lee la configuracion de IA del Admin (g_admin_prod.ia_configuracion),
     * la misma tabla que usa IaChat.
     */
    private static function obtenerConfiguracion()
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        $config = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }
        return $config;
    }

    // =====================================================
    // PROVEEDORES
    // =====================================================

    private static function llamarIA($config, $contexto, $historial, $mensaje)
    {
        $cadenaRaw = isset($config['ia_migracion_cadena']) ? trim($config['ia_migracion_cadena']) : '';
        if ($cadenaRaw === '') {
            $cadenaRaw = isset($config['ia_chat_cadena']) ? trim($config['ia_chat_cadena']) : '';
        }

        $pasos = self::parsearCadena($cadenaRaw);
        if (empty($pasos)) {
            return ['respuesta' => 'No hay cadena de proveedores configurada (ia_migracion_cadena en ia_configuracion).', 'proveedor' => 'fallback'];
        }

        $maxTokens = isset($config['ia_migracion_max_tokens']) ? (int)$config['ia_migracion_max_tokens'] : 8000;

        foreach ($pasos as $paso) {
            $proveedor = $paso['proveedor'];
            $modelo = $paso['modelo'];
            $r = null;

            if ($proveedor === 'anthropic') {
                $key = $config['anthropic_api_key'] ?? null;
                if (!$key) {
                    error_log("MigracionIa - se salta 'anthropic': falta anthropic_api_key");
                    continue;
                }
                $r = self::llamarAnthropic($key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'openrouter') {
                $key = $config['openrouter_api_key'] ?? null;
                if (!$key) {
                    continue;
                }
                $r = self::llamarOpenAI(self::URL_OPENROUTER, $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'groq') {
                $key = $config['groq_api_key'] ?? null;
                if (!$key) {
                    continue;
                }
                $r = self::llamarOpenAI(self::URL_GROQ, $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'qwen') {
                $key = $config['qwen_api_key'] ?? null;
                $baseUrl = isset($config['qwen_base_url']) ? trim($config['qwen_base_url']) : null;
                if (!$key || !$baseUrl) {
                    continue;
                }
                $r = self::llamarOpenAI(rtrim($baseUrl, '/') . '/chat/completions', $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } else {
                error_log("MigracionIa - proveedor no soportado: {$proveedor}");
                continue;
            }

            if (!empty($r['success'])) {
                return [
                    'respuesta' => $r['respuesta'],
                    'proveedor' => $proveedor,
                    'modelo' => $modelo,
                    'tokens_entrada' => $r['tokens_entrada'] ?? null,
                    'tokens_salida' => $r['tokens_salida'] ?? null
                ];
            }
            error_log("MigracionIa - proveedor '{$proveedor}' ({$modelo}) falló: " . ($r['error'] ?? 'desconocido'));
        }

        return [
            'respuesta' => 'No pude conectarme con ningún proveedor de IA. Revisa la configuración y vuelve a intentar.',
            'proveedor' => 'fallback'
        ];
    }

    private static function parsearCadena($cadena)
    {
        $pasos = [];
        foreach (explode(';', $cadena) as $trozo) {
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
            if ($proveedor !== '' && $modelo !== '') {
                $pasos[] = ['proveedor' => $proveedor, 'modelo' => $modelo];
            }
        }
        return $pasos;
    }

    /**
     * API de mensajes de Anthropic. El contexto va como system: es lo que
     * permite que el prompt caching lo reutilice entre turnos, porque el
     * esquema y los catálogos no cambian dentro de una sesión.
     */
    private static function llamarAnthropic($apiKey, $modelo, $contexto, $historial, $mensaje, $maxTokens)
    {
        try {
            $mensajes = [];
            foreach ($historial as $m) {
                $mensajes[] = [
                    'role' => $m['rol'] === 'user' ? 'user' : 'assistant',
                    'content' => $m['mensaje']
                ];
            }
            if (empty($historial) || end($historial)['mensaje'] !== $mensaje) {
                $mensajes[] = ['role' => 'user', 'content' => $mensaje];
            }

            $body = json_encode([
                'model' => $modelo,
                'max_tokens' => $maxTokens,
                'system' => [[
                    'type' => 'text',
                    'text' => $contexto,
                    'cache_control' => ['type' => 'ephemeral']
                ]],
                'messages' => $mensajes
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init(self::URL_ANTHROPIC);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ]);
            // El contexto es grande y las respuestas con SQL son largas.
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);

            $respuesta = curl_exec($ch);
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCurl = curl_error($ch);
            curl_close($ch);

            if ($errorCurl) {
                return ['success' => false, 'error' => 'conexión: ' . $errorCurl];
            }
            if ($codigo !== 200) {
                return ['success' => false, 'error' => 'HTTP ' . $codigo . ' - ' . substr((string)$respuesta, 0, 300)];
            }

            $datos = json_decode($respuesta, true);

            // El contenido llega como bloques; se juntan los de tipo texto.
            $texto = '';
            if (isset($datos['content']) && is_array($datos['content'])) {
                foreach ($datos['content'] as $bloque) {
                    if (isset($bloque['type']) && $bloque['type'] === 'text') {
                        $texto .= $bloque['text'];
                    }
                }
            }

            if (trim($texto) === '') {
                return ['success' => false, 'error' => 'Respuesta vacía o formato inesperado'];
            }

            return [
                'success' => true,
                'respuesta' => trim($texto),
                'tokens_entrada' => $datos['usage']['input_tokens'] ?? null,
                'tokens_salida' => $datos['usage']['output_tokens'] ?? null
            ];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private static function llamarOpenAI($url, $apiKey, $modelo, $contexto, $historial, $mensaje, $maxTokens)
    {
        try {
            $mensajes = [['role' => 'system', 'content' => $contexto]];
            foreach ($historial as $m) {
                $mensajes[] = [
                    'role' => $m['rol'] === 'user' ? 'user' : 'assistant',
                    'content' => $m['mensaje']
                ];
            }
            if (empty($historial) || end($historial)['mensaje'] !== $mensaje) {
                $mensajes[] = ['role' => 'user', 'content' => $mensaje];
            }

            $body = json_encode([
                'model' => $modelo,
                'messages' => $mensajes,
                'temperature' => 0.2,
                'max_tokens' => $maxTokens
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);

            $respuesta = curl_exec($ch);
            $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCurl = curl_error($ch);
            curl_close($ch);

            if ($errorCurl) {
                return ['success' => false, 'error' => 'conexión: ' . $errorCurl];
            }
            if ($codigo !== 200) {
                return ['success' => false, 'error' => 'HTTP ' . $codigo];
            }

            $datos = json_decode($respuesta, true);
            if (isset($datos['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'respuesta' => trim($datos['choices'][0]['message']['content']),
                    'tokens_entrada' => $datos['usage']['prompt_tokens'] ?? null,
                    'tokens_salida' => $datos['usage']['completion_tokens'] ?? null
                ];
            }

            return ['success' => false, 'error' => 'Formato inesperado'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
