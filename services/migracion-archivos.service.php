<?php
/**
 * Tabla migracion_archivos.
 *
 * Lo que entrega el cliente: hojas de matricula en PDF, el Excel de pagos,
 * contratos, el PEI. Se sube todo junto y sin orden.
 *
 * La extraccion de texto se hace aqui y no en el modelo: es mas barata y
 * deja el texto guardado para los turnos siguientes. Un PDF escaneado se
 * marca sin texto y se avisa en pantalla.
 */
class MigracionArchivos
{
    const TIPOS = array('matricula', 'pagos', 'contrato', 'nomina', 'pei', 'otro', 'descartable');

    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.archivos');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, nombre_original, extension, tamano, hash_sha256,
                                             tipo_detectado, estado, mensaje_error, fecha_carga,
                                             CHAR_LENGTH(COALESCE(texto_extraido, '')) AS caracteres_texto
                                      FROM migracion_archivos
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                      ORDER BY fecha_carga");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionArchivos::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.archivos');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, nombre_original, extension, tamano, hash_sha256,
                                             tipo_detectado, estado, mensaje_error, fecha_carga, texto_extraido
                                      FROM migracion_archivos
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionArchivos::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Subida multiple. El front manda multipart/form-data con id_sesion y
     * el campo archivos[].
     */
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.archivos');

            $id_sesion = isset($_POST['id_sesion']) ? $_POST['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
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
            if (!isset($_FILES['archivos'])) {
                Flight::json(array('error' => 'No llegó ningún archivo'), 400);
                return;
            }

            if (!defined('MIGRACION_MAX_ARCHIVO')) {
                require_once __DIR__ . '/../config/migracion.env.php';
            }

            $db = Flight::db();
            $carpeta = self::carpetaSesion($id_sesion);

            $nombres = $_FILES['archivos']['name'];
            $temporales = $_FILES['archivos']['tmp_name'];
            $tamanos = $_FILES['archivos']['size'];
            $errores = $_FILES['archivos']['error'];

            // Un solo archivo llega como escalar, no como arreglo.
            if (!is_array($nombres)) {
                $nombres = array($nombres);
                $temporales = array($temporales);
                $tamanos = array($tamanos);
                $errores = array($errores);
            }

            $resultado = array();

            for ($i = 0; $i < count($nombres); $i++) {
                $nombre = $nombres[$i];

                if ($errores[$i] !== UPLOAD_ERR_OK) {
                    $resultado[] = array('archivo' => $nombre, 'estado' => 'error', 'mensaje' => 'Error de carga (' . $errores[$i] . ')');
                    continue;
                }
                if ($tamanos[$i] > MIGRACION_MAX_ARCHIVO) {
                    $resultado[] = array('archivo' => $nombre, 'estado' => 'error', 'mensaje' => 'El archivo supera el tamaño máximo permitido');
                    continue;
                }

                $hash = hash_file('sha256', $temporales[$i]);

                $sentence = $db->prepare("SELECT id FROM migracion_archivos
                                          WHERE id_sesion = :id_sesion AND hash_sha256 = :hash AND id_tenant = :id_tenant");
                $sentence->bindValue(':id_sesion', $id_sesion);
                $sentence->bindValue(':hash', $hash);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                if ($sentence->fetch()) {
                    $resultado[] = array('archivo' => $nombre, 'estado' => 'repetido', 'mensaje' => 'Ya estaba cargado');
                    continue;
                }

                $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
                $rutaFisica = $carpeta . DIRECTORY_SEPARATOR . $hash . ($extension ? '.' . $extension : '');

                if (!move_uploaded_file($temporales[$i], $rutaFisica)) {
                    $resultado[] = array('archivo' => $nombre, 'estado' => 'error', 'mensaje' => 'No se pudo guardar el archivo');
                    continue;
                }

                $extraccion = self::extraerTexto($rutaFisica, $extension);
                $tipo = self::clasificar($nombre, $extraccion['texto']);
                $estado = $extraccion['texto'] === '' ? 'error' : 'clasificado';

                $idNew = Uuid::generar();
                $sentence = $db->prepare("INSERT INTO migracion_archivos
                    (id, id_tenant, id_sesion, nombre_original, ruta_fisica, extension, tamano, hash_sha256,
                     tipo_detectado, texto_extraido, estado, mensaje_error)
                    VALUES (:id, :id_tenant, :id_sesion, :nombre, :ruta, :extension, :tamano, :hash,
                            :tipo, :texto, :estado, :error)");
                $sentence->bindValue(':id', $idNew);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':id_sesion', $id_sesion);
                $sentence->bindValue(':nombre', $nombre);
                $sentence->bindValue(':ruta', $rutaFisica);
                $sentence->bindValue(':extension', $extension);
                $sentence->bindValue(':tamano', $tamanos[$i], PDO::PARAM_INT);
                $sentence->bindValue(':hash', $hash);
                $sentence->bindValue(':tipo', $tipo);
                $sentence->bindValue(':texto', $extraccion['texto']);
                $sentence->bindValue(':estado', $estado);
                $sentence->bindValue(':error', $extraccion['mensaje']);
                $sentence->execute();

                $resultado[] = array(
                    'id' => $idNew,
                    'archivo' => $nombre,
                    'estado' => $extraccion['texto'] === '' ? 'sin_texto' : 'cargado',
                    'tipo_detectado' => $tipo,
                    'caracteres' => strlen($extraccion['texto']),
                    'mensaje' => $extraccion['mensaje']
                );
            }

            Flight::json(array('success' => true, 'archivos' => $resultado));
        } catch (Exception $e) {
            error_log("MigracionArchivos::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Solo cambia el tipo detectado: es la correccion a mano cuando la
     * clasificacion automatica se equivoca.
     */
    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.archivos');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id = $data['id'];
            $tipo_detectado = isset($data['tipo_detectado']) ? $data['tipo_detectado'] : null;

            if (!in_array($tipo_detectado, self::TIPOS, true)) {
                Flight::json(array('error' => 'Tipo de archivo inválido'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE migracion_archivos SET tipo_detectado = :tipo
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':tipo', $tipo_detectado);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionArchivos::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.archivos');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("SELECT ruta_fisica FROM migracion_archivos
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $archivo = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($archivo && is_file($archivo['ruta_fisica'])) {
                @unlink($archivo['ruta_fisica']);
            }

            $sentence = $db->prepare("DELETE FROM migracion_archivos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionArchivos::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Texto de los archivos de una sesion, para el contexto del asistente.
     * Se recorta por archivo para no reventar el prompt.
     *
     * @return string
     */
    public static function textoParaContexto($id_sesion, $maxPorArchivo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT nombre_original, tipo_detectado, texto_extraido
                                  FROM migracion_archivos
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                    AND estado <> 'descartado' AND tipo_detectado <> 'descartable'
                                    AND texto_extraido IS NOT NULL AND texto_extraido <> ''
                                  ORDER BY tipo_detectado, nombre_original");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $partes = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $archivo) {
            $texto = $archivo['texto_extraido'];
            if (strlen($texto) > $maxPorArchivo) {
                $texto = substr($texto, 0, $maxPorArchivo) . "\n[...recortado...]";
            }
            $partes[] = "--- ARCHIVO: {$archivo['nombre_original']} (tipo: {$archivo['tipo_detectado']}) ---\n" . $texto;
        }

        return implode("\n\n", $partes);
    }

    /**
     * Borra los archivos fisicos de una sesion. Devuelve cuantos borro.
     * Lo llama la purga y el borrado de la sesion.
     *
     * @return int
     */
    public static function borrarCarpeta($id_sesion)
    {
        $carpeta = self::carpetaSesion($id_sesion);
        $borrados = 0;

        if (is_dir($carpeta)) {
            foreach (glob($carpeta . DIRECTORY_SEPARATOR . '*') as $archivo) {
                if (is_file($archivo) && @unlink($archivo)) {
                    $borrados++;
                }
            }
            @rmdir($carpeta);
        }

        return $borrados;
    }

    public static function carpetaSesion($id_sesion)
    {
        if (!defined('MIGRACION_RUTA_ARCHIVOS')) {
            require_once __DIR__ . '/../config/migracion.env.php';
        }
        $ruta = rtrim(MIGRACION_RUTA_ARCHIVOS, '/\\') . DIRECTORY_SEPARATOR . $id_sesion;
        if (!is_dir($ruta)) {
            @mkdir($ruta, 0775, true);
        }
        return $ruta;
    }

    // =====================================================
    // EXTRACCION DE TEXTO
    // =====================================================

    private static function extraerTexto($ruta, $extension)
    {
        try {
            switch ($extension) {
                case 'pdf':
                    return self::extraerPdf($ruta);
                case 'xlsx':
                case 'xlsm':
                    return self::extraerXlsx($ruta);
                case 'docx':
                    return self::extraerDocx($ruta);
                case 'csv':
                case 'txt':
                case 'md':
                case 'json':
                    return array('texto' => (string)file_get_contents($ruta), 'mensaje' => null);
                default:
                    return array('texto' => '', 'mensaje' => 'Formato no soportado para extracción automática (' . $extension . ')');
            }
        } catch (Exception $e) {
            return array('texto' => '', 'mensaje' => $e->getMessage());
        }
    }

    /**
     * PDF con capa de texto. Si el cliente manda escaneos esto devuelve
     * vacio y el archivo queda marcado para capturarlo a mano.
     */
    private static function extraerPdf($ruta)
    {
        $contenido = file_get_contents($ruta);
        $texto = '';

        // Streams comprimidos: es como vienen las hojas de matricula
        // generadas desde Word o desde un formulario.
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contenido, $coincidencias)) {
            foreach ($coincidencias[1] as $stream) {
                $plano = @gzuncompress($stream);
                if ($plano === false) {
                    $plano = @gzinflate($stream);
                }
                if ($plano === false) {
                    continue;
                }
                if (preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/s', $plano, $trozos)) {
                    foreach ($trozos[0] as $trozo) {
                        $trozo = substr($trozo, 1, -1);
                        $trozo = str_replace(array('\\(', '\\)', '\\\\'), array('(', ')', '\\'), $trozo);
                        $texto .= $trozo;
                    }
                    $texto .= "\n";
                }
            }
        }

        $texto = trim(preg_replace('/[ \t]+/', ' ', $texto));

        if ($texto === '') {
            return array('texto' => '', 'mensaje' => 'El PDF no tiene capa de texto (parece escaneado). Hay que capturarlo a mano.');
        }
        return array('texto' => $texto, 'mensaje' => null);
    }

    /**
     * XLSX sin librerias externas: es un zip con XML adentro. Devuelve las
     * hojas con las celdas separadas por tabulacion, que es el formato que
     * mejor lee el modelo.
     */
    private static function extraerXlsx($ruta)
    {
        if (!class_exists('ZipArchive')) {
            return array('texto' => '', 'mensaje' => 'ZipArchive no está habilitado en PHP; no se puede leer el Excel');
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            return array('texto' => '', 'mensaje' => 'No se pudo abrir el archivo Excel');
        }

        $compartidas = array();
        $xmlCompartidas = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlCompartidas !== false) {
            $doc = @simplexml_load_string($xmlCompartidas);
            if ($doc) {
                foreach ($doc->si as $si) {
                    $compartidas[] = (string)strip_tags($si->asXML());
                }
            }
        }

        $texto = '';
        for ($i = 1; $i <= 20; $i++) {
            $xmlHoja = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($xmlHoja === false) {
                continue;
            }
            $doc = @simplexml_load_string($xmlHoja);
            if (!$doc) {
                continue;
            }

            $texto .= "\n--- Hoja {$i} ---\n";
            foreach ($doc->sheetData->row as $fila) {
                $celdas = array();
                foreach ($fila->c as $celda) {
                    $tipo = (string)$celda['t'];
                    if ($tipo === 's') {
                        $indice = (int)$celda->v;
                        $valor = isset($compartidas[$indice]) ? $compartidas[$indice] : '';
                    } elseif ($tipo === 'inlineStr') {
                        $valor = (string)strip_tags($celda->is->asXML());
                    } else {
                        $valor = (string)$celda->v;
                    }
                    $celdas[] = trim($valor);
                }
                $linea = implode("\t", $celdas);
                if (trim($linea) !== '') {
                    $texto .= $linea . "\n";
                }
            }
        }

        $zip->close();

        if (trim($texto) === '') {
            return array('texto' => '', 'mensaje' => 'El Excel no tiene contenido legible');
        }
        return array('texto' => $texto, 'mensaje' => null);
    }

    private static function extraerDocx($ruta)
    {
        if (!class_exists('ZipArchive')) {
            return array('texto' => '', 'mensaje' => 'ZipArchive no está habilitado en PHP');
        }

        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            return array('texto' => '', 'mensaje' => 'No se pudo abrir el documento Word');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return array('texto' => '', 'mensaje' => 'El documento no tiene contenido legible');
        }

        // Los saltos de parrafo se conservan para no pegar todo el texto.
        $xml = str_replace(array('</w:p>', '<w:br/>'), "\n", $xml);
        $texto = trim(html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8'));

        return array('texto' => $texto, 'mensaje' => null);
    }

    /**
     * Clasificacion por nombre y contenido. El asistente puede corregirla
     * despues, y el usuario tambien desde la pantalla.
     */
    private static function clasificar($nombre, $texto)
    {
        $n = mb_strtolower($nombre);
        $t = mb_strtolower(substr($texto, 0, 4000));

        if (strpos($n, 'matricula') !== false || strpos($n, 'matrícula') !== false
            || strpos($t, 'hoja de matricula') !== false || strpos($t, 'hoja de matrícula') !== false) {
            return 'matricula';
        }
        if (strpos($n, 'pago') !== false || strpos($n, 'cartera') !== false
            || strpos($n, 'pension') !== false || strpos($n, 'pensión') !== false
            || strpos($n, 'control') !== false) {
            return 'pagos';
        }
        if (strpos($n, 'contrato') !== false || strpos($t, 'cláusula') !== false || strpos($t, 'clausula') !== false) {
            return 'contrato';
        }
        if (strpos($n, 'nomina') !== false || strpos($n, 'nómina') !== false
            || strpos($n, 'docente') !== false || strpos($n, 'profesor') !== false) {
            return 'nomina';
        }
        if (strpos($n, 'pei') !== false || strpos($t, 'proyecto educativo institucional') !== false) {
            return 'pei';
        }
        // Un Excel sin mas pistas casi siempre es la cartera.
        if (in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), array('xlsx', 'xlsm', 'csv'), true)) {
            return 'pagos';
        }

        return 'otro';
    }
}
