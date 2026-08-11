<?php
/**
 * Tabla migracion_mensajes.
 *
 * El hilo del asistente. Como esta tabla es la conversacion, aqui vive
 * tambien la llamada a la IA y el armado del contexto.
 *
 * Sigue el patron de IaChat: cadena de proveedores configurable desde
 * ia_configuracion con el formato "proveedor|modelo;proveedor|modelo", y
 * se cae al siguiente si uno falla. Se agrega 'anthropic'.
 *
 * Regla dura: el asistente PROPONE SQL, no lo ejecuta. Lo que venga entre
 * marcadores [SQL] se guarda como script y pasa por el validador y por la
 * aprobacion de una persona.
 */
class MigracionMensajes
{
    const URL_ANTHROPIC  = 'https://api.anthropic.com/v1/messages';
    const URL_OPENROUTER = 'https://openrouter.ai/api/v1/chat/completions';
    const URL_GROQ       = 'https://api.groq.com/openai/v1/chat/completions';

    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, rol, mensaje, proveedor, modelo,
                                             tokens_entrada, tokens_salida, tiempo_ms, fecha
                                      FROM migracion_mensajes
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                      ORDER BY fecha");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionMensajes::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, rol, mensaje, proveedor, modelo,
                                             tokens_entrada, tokens_salida, tiempo_ms, fecha
                                      FROM migracion_mensajes
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionMensajes::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Manda el mensaje del usuario al asistente y guarda las dos puntas
     * del turno. Si la respuesta trae SQL, lo deja como script propuesto.
     */
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $data = Flight::request()->data;
            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $mensaje = isset($data['mensaje']) ? trim($data['mensaje']) : '';
            $codigo_bloque = isset($data['codigo_bloque']) ? $data['codigo_bloque'] : null;

            if (!$id_sesion || $mensaje === '') {
                Flight::json(array('error' => 'Faltan la sesión o el mensaje'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($id_sesion);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }
            if (!MigracionSesiones::estaAbierta($sesion)) {
                Flight::json(array('error' => 'La sesión está en estado "' . $sesion['estado'] . '" y ya no admite cambios'), 400);
                return;
            }

            $config = self::obtenerConfiguracion();

            self::guardar($id_sesion, 'user', $mensaje);

            $contexto = self::armarContexto($sesion, $codigo_bloque, $config);
            $historial = self::obtenerHistorial($id_sesion, isset($config['ia_migracion_historial']) ? (int)$config['ia_migracion_historial'] : 12);

            $inicio = microtime(true);
            $respuesta = self::llamarIA($config, $contexto, $historial, $mensaje);
            $tiempo = round((microtime(true) - $inicio) * 1000);

            self::guardar(
                $id_sesion,
                'assistant',
                $respuesta['respuesta'],
                $respuesta['proveedor'],
                isset($respuesta['modelo']) ? $respuesta['modelo'] : null,
                isset($respuesta['tokens_entrada']) ? $respuesta['tokens_entrada'] : null,
                isset($respuesta['tokens_salida']) ? $respuesta['tokens_salida'] : null,
                $tiempo
            );

            // El SQL nunca se ejecuta aqui: se guarda y se valida.
            $script = null;
            $sql = self::extraerSql($respuesta['respuesta']);
            if ($sql !== null) {
                $script = MigracionScripts::registrar(
                    $id_sesion,
                    $codigo_bloque,
                    'Propuesto por el asistente',
                    $sql,
                    self::extraerResumen($respuesta['respuesta'])
                );
            }

            $id_bloque = MigracionBloques::obtenerIdPorCodigo($id_sesion, $codigo_bloque);
            $preguntasNuevas = 0;
            foreach (self::extraerPreguntas($respuesta['respuesta']) as $pregunta) {
                if (MigracionPreguntas::registrar($id_sesion, $id_bloque, $pregunta)) {
                    $preguntasNuevas++;
                }
            }

            Flight::json(array(
                'success' => true,
                'respuesta' => $respuesta['respuesta'],
                'proveedor' => $respuesta['proveedor'],
                'modelo' => isset($respuesta['modelo']) ? $respuesta['modelo'] : null,
                'tiempo_ms' => $tiempo,
                'tokens_entrada' => isset($respuesta['tokens_entrada']) ? $respuesta['tokens_entrada'] : null,
                'tokens_salida' => isset($respuesta['tokens_salida']) ? $respuesta['tokens_salida'] : null,
                'script' => $script,
                'preguntas_nuevas' => $preguntasNuevas
            ));
        } catch (Exception $e) {
            error_log("MigracionMensajes::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM migracion_mensajes WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionMensajes::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Consumo acumulado de la sesion, para ver el costo real por cliente.
     */
    public static function getConsumo()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("SELECT COUNT(*) AS turnos,
                                             COALESCE(SUM(tokens_entrada), 0) AS tokens_entrada,
                                             COALESCE(SUM(tokens_salida), 0) AS tokens_salida,
                                             COALESCE(AVG(tiempo_ms), 0) AS tiempo_promedio
                                      FROM migracion_mensajes
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant AND rol = 'assistant'");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetch(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionMensajes::getConsumo - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // CONTEXTO
    // =====================================================

    /**
     * Arma el contexto completo del asistente. Esto es lo que hace que la
     * conversacion sirva: el esquema real, los catalogos reales, el indice
     * del codigo y el expediente de lo que ya se resolvio.
     */
    public static function armarContexto($sesion, $codigo_bloque, $config)
    {
        $partes = array();
        $partes[] = self::instrucciones($sesion, $codigo_bloque);

        try {
            $esquema = MigracionEsquemaCache::leerEsquema($sesion['id_conexion'], $codigo_bloque);
            $partes[] = "== ESQUEMA REAL DE LA BASE DESTINO ==\n"
                . "Base: {$esquema['base_datos']} | Tablas: {$esquema['total_tablas']}\n"
                . self::esquemaATexto($esquema['esquema']);
        } catch (Exception $e) {
            $partes[] = "== ESQUEMA ==\nNo se pudo leer el esquema del destino: " . $e->getMessage();
        }

        try {
            $catalogos = MigracionEsquemaCache::leerCatalogos($sesion);
            $partes[] = "== CATÁLOGOS DEL DESTINO ==\n" . self::catalogosATexto($catalogos['catalogos']);
        } catch (Exception $e) {
            $partes[] = "== CATÁLOGOS ==\nNo se pudieron leer: " . $e->getMessage();
        }

        $indice = MigracionCodigoIndice::indiceParaContexto('back');
        if ($indice !== '') {
            $partes[] = "== ÍNDICE DEL BACK DE GENIALISIS ==\n"
                . "No tienes el código completo. Si necesitas ver un archivo, pídelo así:\n"
                . "[PEDIR_ARCHIVO: services/estudiantes.service.php]\n\n" . $indice;
        }

        $maxTexto = isset($config['ia_migracion_max_texto_archivo']) ? (int)$config['ia_migracion_max_texto_archivo'] : 15000;
        $textoArchivos = MigracionArchivos::textoParaContexto($sesion['id'], $maxTexto);
        if ($textoArchivos !== '') {
            $partes[] = "== ARCHIVOS QUE ENTREGÓ EL CLIENTE ==\n" . $textoArchivos;
        }

        $partes[] = "== EXPEDIENTE DE LA SESIÓN ==\n" . self::expediente($sesion);

        return implode("\n\n", $partes);
    }

    private static function instrucciones($sesion, $codigo_bloque)
    {
        $idTenantDestino = $sesion['id_tenant_destino'] !== null ? $sesion['id_tenant_destino'] : '(sin asignar todavía)';

        return <<<TXT
Eres el asistente de migración de Genialisis. Acompañas a una persona del equipo
que ya se reunió con la dirección del cliente y trae sus archivos. Esa persona
conoce el negocio pero NO conoce el modelo de datos: no le hables de FK ni de
constraints, háblale de estudiantes, acudientes, grupos y pagos.

CLIENTE EN MONTAJE: {$sesion['nombre_cliente']}
TENANT DESTINO: {$sesion['codigo_tenant_destino']} (id_tenant = {$idTenantDestino})
AÑO: {$sesion['anno']}
BLOQUE EN CURSO: {$codigo_bloque}

CÓMO TRABAJAS

1. No inventas. Si un dato no está en los archivos ni en la conversación, lo
   preguntas. Nunca lo rellenas con un valor plausible.
2. Declaras tu interpretación ANTES de aplicarla. Si en los archivos los grados
   vienen escritos de diez formas y entiendes que son cinco, lo dices y esperas
   confirmación.
3. Normalizas contra los catálogos REALES que tienes arriba, no contra lo que
   suena razonable. Si el catálogo de parentesco distingue abuela materna de
   paterna y el archivo solo dice "Abuela", eso es una pregunta.
4. Respetas el orden de los bloques. Si te piden cargar estudiantes y los grupos
   todavía no existen, lo dices y no lo intentas.
5. El cruce entre archivos se hace por documento, nunca por nombre. Los nombres
   traen tildes, erratas y apellidos cambiados.
6. Cuando el PDF y el Excel se contradigan, manda el PDF: está firmado por los padres.

CÓMO ESCRIBES SQL

Propones el script; no lo ejecutas. Otra pieza lo valida y una persona lo aprueba.

Reglas que tu SQL debe cumplir o será rechazado automáticamente:
- Nada de DROP, TRUNCATE, ALTER, CREATE ni sentencias administrativas.
- Todo INSERT en una tabla que tenga id_tenant debe traerlo con el valor {$idTenantDestino}.
- Todo UPDATE y DELETE debe filtrar por id_tenant = {$idTenantDestino}.
- Las llaves primarias son char(36) con DEFAULT uuid(). Genera los UUID por
  adelantado como literales y encadena las FK con esos mismos literales. NO uses
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

Cuando necesites confirmación antes de escribir, marca cada punto así:

[PREGUNTA] Los grados vienen en diez variantes ortográficas y entiendo que son cinco reales. ¿Confirmas?

Si necesitas ver un archivo del código, pídelo así y espera la respuesta:

[PEDIR_ARCHIVO: services/estudiantes.service.php]
TXT;
    }

    private static function esquemaATexto($tablas)
    {
        $lineas = array();
        foreach ($tablas as $tabla) {
            $columnas = array();
            foreach ($tabla['columnas'] as $columna) {
                $marca = $columna['llave'] === 'PRI' ? ' PK' : '';
                $nulo = $columna['nulo'] ? '' : ' NOT NULL';
                $columnas[] = "    {$columna['nombre']} {$columna['tipo']}{$nulo}{$marca}";
            }
            $tenant = $tabla['tiene_tenant'] ? ' [lleva id_tenant]' : ' [GLOBAL, sin id_tenant]';
            $linea = "{$tabla['tabla']}{$tenant}\n" . implode("\n", $columnas);

            if (!empty($tabla['fk'])) {
                $fks = array();
                foreach ($tabla['fk'] as $fk) {
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
        $lineas = array();
        foreach ($catalogos as $catalogo) {
            $marca = $catalogo['global'] ? ' [GLOBAL]' : '';
            $valores = array();
            foreach (array_slice($catalogo['filas'], 0, 60) as $fila) {
                $id = isset($fila['id']) ? $fila['id'] : '?';
                if (isset($fila['nombre'])) {
                    $nombre = $fila['nombre'];
                } elseif (isset($fila['descripcion'])) {
                    $nombre = $fila['descripcion'];
                } else {
                    $nombre = json_encode($fila, JSON_UNESCAPED_UNICODE);
                }
                $valores[] = "{$id} = {$nombre}";
            }
            $lineas[] = "{$catalogo['tabla']}{$marca} ({$catalogo['total']}): " . implode(' | ', $valores);
        }
        return implode("\n", $lineas);
    }

    private static function expediente($sesion)
    {
        $partes = array();

        $bloques = array();
        foreach (MigracionBloques::obtenerDeSesion($sesion['id']) as $bloque) {
            $bloques[] = "{$bloque['codigo']}: {$bloque['estado']}";
        }
        $partes[] = "Bloques: " . implode(' | ', $bloques);

        $registros = array();
        foreach (MigracionRegistros::resumen($sesion['id']) as $registro) {
            $registros[] = "{$registro['tabla_destino']} ({$registro['estado']}): {$registro['total']}";
        }
        if ($registros) {
            $partes[] = "Registros ya resueltos: " . implode(' | ', $registros);
        }

        $preguntas = MigracionPreguntas::obtenerDeSesion($sesion['id']);
        if ($preguntas) {
            $lista = array();
            foreach ($preguntas as $pregunta) {
                $lista[] = $pregunta['estado'] === 'respondida'
                    ? "RESUELTO: {$pregunta['pregunta']} -> {$pregunta['respuesta']}"
                    : "ABIERTO: {$pregunta['pregunta']}";
            }
            $partes[] = "Preguntas:\n" . implode("\n", $lista);
        }

        $ejecuciones = MigracionEjecuciones::obtenerDeSesion($sesion['id'], 10);
        if ($ejecuciones) {
            $lista = array();
            foreach ($ejecuciones as $ejecucion) {
                if ((int)$ejecucion['deshecho'] === 1) {
                    $estado = 'DESHECHO';
                } elseif ((int)$ejecucion['exito'] === 1) {
                    $estado = 'OK';
                } else {
                    $estado = 'FALLÓ';
                }
                $lista[] = "{$ejecucion['fecha']} {$ejecucion['codigo_bloque']}: {$estado} ({$ejecucion['filas_afectadas']} filas)";
            }
            $partes[] = "Ya se ejecutó:\n" . implode("\n", $lista);
        }

        return implode("\n\n", $partes);
    }

    // =====================================================
    // LECTURA DE LA RESPUESTA
    // =====================================================

    public static function extraerSql($respuesta)
    {
        if (preg_match('/\[SQL\](.*?)\[\/SQL\]/s', $respuesta, $coincidencias)) {
            $sql = trim($coincidencias[1]);
            return $sql === '' ? null : $sql;
        }
        return null;
    }

    public static function extraerPreguntas($respuesta)
    {
        $preguntas = array();
        if (preg_match_all('/\[PREGUNTA\]\s*(.+)$/m', $respuesta, $coincidencias)) {
            foreach ($coincidencias[1] as $pregunta) {
                $pregunta = trim($pregunta);
                if ($pregunta !== '') {
                    $preguntas[] = $pregunta;
                }
            }
        }
        return $preguntas;
    }

    /**
     * Conteos que declara el asistente, para poder contrastarlos contra lo
     * que realmente hizo el script.
     */
    private static function extraerResumen($respuesta)
    {
        if (preg_match('/\[RESUMEN\](.*?)\[\/RESUMEN\]/s', $respuesta, $coincidencias)) {
            $json = json_decode(trim($coincidencias[1]), true);
            return $json ? $json : null;
        }
        return null;
    }

    // =====================================================
    // PERSISTENCIA Y PROVEEDORES
    // =====================================================

    private static function guardar($id_sesion, $rol, $mensaje, $proveedor = null, $modelo = null,
                                    $tokens_entrada = null, $tokens_salida = null, $tiempo_ms = null)
    {
        $db = Flight::db();
        $sentence = $db->prepare("INSERT INTO migracion_mensajes
            (id, id_tenant, id_sesion, rol, mensaje, proveedor, modelo, tokens_entrada, tokens_salida, tiempo_ms)
            VALUES (:id, :id_tenant, :id_sesion, :rol, :mensaje, :proveedor, :modelo, :te, :ts, :tiempo)");
        $sentence->bindValue(':id', Uuid::generar());
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':rol', $rol);
        $sentence->bindValue(':mensaje', $mensaje);
        $sentence->bindValue(':proveedor', $proveedor);
        $sentence->bindValue(':modelo', $modelo);
        $sentence->bindValue(':te', $tokens_entrada, $tokens_entrada === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':ts', $tokens_salida, $tokens_salida === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':tiempo', $tiempo_ms, $tiempo_ms === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->execute();
    }

    private static function obtenerHistorial($id_sesion, $limite)
    {
        $db = Flight::db();
        $limite = (int)$limite;
        $sentence = $db->prepare("SELECT rol, mensaje FROM migracion_mensajes
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                  ORDER BY fecha DESC LIMIT {$limite}");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return array_reverse($sentence->fetchAll(PDO::FETCH_ASSOC));
    }

    private static function obtenerConfiguracion()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $config = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $config[$fila['clave']] = $fila['valor'];
        }
        return $config;
    }

    private static function llamarIA($config, $contexto, $historial, $mensaje)
    {
        $cadenaRaw = isset($config['ia_migracion_cadena']) ? trim($config['ia_migracion_cadena']) : '';
        if ($cadenaRaw === '') {
            $cadenaRaw = isset($config['ia_chat_cadena']) ? trim($config['ia_chat_cadena']) : '';
        }

        $pasos = self::parsearCadena($cadenaRaw);
        if (empty($pasos)) {
            return array(
                'respuesta' => 'No hay cadena de proveedores configurada. Revisa ia_migracion_cadena en Configuración IA.',
                'proveedor' => 'fallback'
            );
        }

        $maxTokens = isset($config['ia_migracion_max_tokens']) ? (int)$config['ia_migracion_max_tokens'] : 8000;

        foreach ($pasos as $paso) {
            $proveedor = $paso['proveedor'];
            $modelo = $paso['modelo'];
            $r = null;

            if ($proveedor === 'anthropic') {
                $key = isset($config['anthropic_api_key']) ? $config['anthropic_api_key'] : null;
                if (!$key) {
                    error_log("MigracionMensajes - se salta 'anthropic': falta anthropic_api_key");
                    continue;
                }
                $r = self::llamarAnthropic($key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'openrouter') {
                $key = isset($config['openrouter_api_key']) ? $config['openrouter_api_key'] : null;
                if (!$key) {
                    error_log("MigracionMensajes - se salta 'openrouter': falta openrouter_api_key");
                    continue;
                }
                $r = self::llamarChatOpenAI(self::URL_OPENROUTER, $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'groq') {
                $key = isset($config['groq_api_key']) ? $config['groq_api_key'] : null;
                if (!$key) {
                    error_log("MigracionMensajes - se salta 'groq': falta groq_api_key");
                    continue;
                }
                $r = self::llamarChatOpenAI(self::URL_GROQ, $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } elseif ($proveedor === 'qwen') {
                $key = isset($config['qwen_api_key']) ? $config['qwen_api_key'] : null;
                $baseUrl = isset($config['qwen_base_url']) ? trim($config['qwen_base_url']) : null;
                if (!$key || !$baseUrl) {
                    error_log("MigracionMensajes - se salta 'qwen': falta qwen_api_key o qwen_base_url");
                    continue;
                }
                $r = self::llamarChatOpenAI(rtrim($baseUrl, '/') . '/chat/completions', $key, $modelo, $contexto, $historial, $mensaje, $maxTokens);
            } else {
                error_log("MigracionMensajes - proveedor no soportado en la cadena: {$proveedor}");
                continue;
            }

            if (!empty($r['success'])) {
                return array(
                    'respuesta' => $r['respuesta'],
                    'proveedor' => $proveedor,
                    'modelo' => $modelo,
                    'tokens_entrada' => isset($r['tokens_entrada']) ? $r['tokens_entrada'] : null,
                    'tokens_salida' => isset($r['tokens_salida']) ? $r['tokens_salida'] : null
                );
            }
            error_log("MigracionMensajes - proveedor '{$proveedor}' ({$modelo}) falló: " . (isset($r['error']) ? $r['error'] : 'desconocido'));
        }

        return array(
            'respuesta' => 'No pude conectarme con ningún proveedor de IA. Revisa la configuración y vuelve a intentar.',
            'proveedor' => 'fallback'
        );
    }

    /**
     * Parsea "proveedor|modelo;proveedor|modelo" a una lista ordenada.
     */
    private static function parsearCadena($cadena)
    {
        $pasos = array();
        if (trim($cadena) === '') {
            return $pasos;
        }
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
                $pasos[] = array('proveedor' => $proveedor, 'modelo' => $modelo);
            }
        }
        return $pasos;
    }

    /**
     * El contexto va como system para que el prompt caching lo reutilice
     * entre turnos: el esquema y los catálogos no cambian dentro de una
     * sesión, y son la mayor parte del prompt.
     */
    private static function llamarAnthropic($api_key, $modelo, $contexto, $historial, $mensaje_usuario, $max_tokens)
    {
        try {
            $mensajes = array();
            foreach ($historial as $msg) {
                $mensajes[] = array(
                    'role' => $msg['rol'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['mensaje']
                );
            }
            if (empty($historial) || end($historial)['mensaje'] !== $mensaje_usuario) {
                $mensajes[] = array('role' => 'user', 'content' => $mensaje_usuario);
            }

            $body = json_encode(array(
                'model' => $modelo,
                'max_tokens' => $max_tokens,
                'system' => array(array(
                    'type' => 'text',
                    'text' => $contexto,
                    'cache_control' => array('type' => 'ephemeral')
                )),
                'messages' => $mensajes
            ), JSON_UNESCAPED_UNICODE);

            $ch = curl_init(self::URL_ANTHROPIC);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: 2023-06-01'
            ));
            // El contexto es grande y las respuestas con SQL son largas.
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return array('success' => false, 'error' => 'conexión: ' . $curl_error);
            }
            if ($http_code !== 200) {
                return array('success' => false, 'error' => 'HTTP ' . $http_code . ' - ' . substr((string)$response, 0, 300));
            }

            $data = json_decode($response, true);

            // El contenido llega en bloques; se juntan los de tipo texto.
            $texto = '';
            if (isset($data['content']) && is_array($data['content'])) {
                foreach ($data['content'] as $bloque) {
                    if (isset($bloque['type']) && $bloque['type'] === 'text') {
                        $texto .= $bloque['text'];
                    }
                }
            }

            if (trim($texto) === '') {
                return array('success' => false, 'error' => 'Respuesta vacía o formato inesperado');
            }

            return array(
                'success' => true,
                'respuesta' => trim($texto),
                'tokens_entrada' => isset($data['usage']['input_tokens']) ? $data['usage']['input_tokens'] : null,
                'tokens_salida' => isset($data['usage']['output_tokens']) ? $data['usage']['output_tokens'] : null
            );
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }

    private static function llamarChatOpenAI($url, $api_key, $modelo, $contexto, $historial, $mensaje_usuario, $max_tokens)
    {
        try {
            $messages = array();
            $messages[] = array('role' => 'system', 'content' => $contexto);

            foreach ($historial as $msg) {
                $messages[] = array(
                    'role' => $msg['rol'] === 'user' ? 'user' : 'assistant',
                    'content' => $msg['mensaje']
                );
            }
            if (empty($historial) || end($historial)['mensaje'] !== $mensaje_usuario) {
                $messages[] = array('role' => 'user', 'content' => $mensaje_usuario);
            }

            $body = json_encode(array(
                'model' => $modelo,
                'messages' => $messages,
                'temperature' => 0.2,
                'max_tokens' => $max_tokens
            ), JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key
            ));
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                return array('success' => false, 'error' => 'conexión: ' . $curl_error);
            }
            if ($http_code !== 200) {
                return array('success' => false, 'error' => 'HTTP ' . $http_code);
            }

            $data = json_decode($response, true);

            if (isset($data['choices'][0]['message']['content'])) {
                return array(
                    'success' => true,
                    'respuesta' => trim($data['choices'][0]['message']['content']),
                    'tokens_entrada' => isset($data['usage']['prompt_tokens']) ? $data['usage']['prompt_tokens'] : null,
                    'tokens_salida' => isset($data['usage']['completion_tokens']) ? $data['usage']['completion_tokens'] : null
                );
            }

            return array('success' => false, 'error' => 'Formato inesperado');
        } catch (Exception $e) {
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
}
