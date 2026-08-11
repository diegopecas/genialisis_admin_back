<?php
/**
 * MigracionCodigo
 *
 * El zip del back y del front de Genialisis producto queda guardado y se
 * indexa: ruta, tamano y firmas (clases y funciones). La IA recibe SOLO
 * el indice, y cuando necesita ver como se crea un estudiante pide el
 * archivo por ruta y el back se lo entrega.
 *
 * Nunca se le manda el codigo completo: son 369 archivos en el back y
 * 638 en el front, y no cabe en ningun contexto util.
 */
class MigracionCodigo
{
    public static function getIndice()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $origen = Flight::request()->query['origen'] ?? null;

            $db = MigracionDb::mig();
            if ($origen) {
                $stmt = $db->prepare("SELECT id, origen, version, ruta_archivo, tamano, firmas, fecha
                                      FROM mig_codigo_indice WHERE origen = :origen ORDER BY ruta_archivo");
                $stmt->bindValue(':origen', $origen);
            } else {
                $stmt = $db->prepare("SELECT id, origen, version, ruta_archivo, tamano, firmas, fecha
                                      FROM mig_codigo_indice ORDER BY origen, ruta_archivo");
            }
            $stmt->execute();
            Flight::json($stmt->fetchAll());
        } catch (Exception $e) {
            error_log("MigracionCodigo::getIndice - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sube el zip del codigo y lo indexa. Reemplaza el indice anterior de
     * ese origen: si se sube un back nuevo, el viejo deja de existir.
     */
    public static function subirZip()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $origen = $_POST['origen'] ?? null;
            $version = $_POST['version'] ?? date('Y-m-d');

            if (!in_array($origen, ['back', 'front'], true)) {
                Flight::json(['error' => 'El origen debe ser "back" o "front"'], 400);
                return;
            }
            if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(['error' => 'No llegó el archivo zip'], 400);
                return;
            }
            if (!class_exists('ZipArchive')) {
                Flight::json(['error' => 'ZipArchive no está habilitado en PHP'], 500);
                return;
            }

            if (!defined('MIGRACION_RUTA_CODIGO')) {
                require_once __DIR__ . '/../config/migracion.env.php';
            }

            $carpeta = rtrim(MIGRACION_RUTA_CODIGO, '/\\');
            if (!is_dir($carpeta)) {
                @mkdir($carpeta, 0775, true);
            }

            $rutaZip = $carpeta . DIRECTORY_SEPARATOR . 'genialisis_' . $origen . '.zip';
            if (!move_uploaded_file($_FILES['zip']['tmp_name'], $rutaZip)) {
                Flight::json(['error' => 'No se pudo guardar el zip'], 500);
                return;
            }

            $indexados = self::indexar($rutaZip, $origen, $version);

            Flight::json([
                'success' => true,
                'origen' => $origen,
                'version' => $version,
                'archivos_indexados' => $indexados
            ]);
        } catch (Exception $e) {
            error_log("MigracionCodigo::subirZip - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Devuelve el contenido de un archivo del zip. Es lo que llama la IA
     * cuando pide "muéstrame services/estudiantes.service.php".
     */
    public static function verArchivo()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $origen = Flight::request()->query['origen'] ?? null;
            $ruta = Flight::request()->query['ruta'] ?? null;

            if (!$origen || !$ruta) {
                Flight::json(['error' => 'Faltan el origen y la ruta del archivo'], 400);
                return;
            }

            $contenido = self::leerArchivo($origen, $ruta);
            if ($contenido === null) {
                Flight::json(['error' => 'El archivo no está en el índice'], 404);
                return;
            }

            Flight::json([
                'origen' => $origen,
                'ruta' => $ruta,
                'tamano' => strlen($contenido),
                'contenido' => $contenido
            ]);
        } catch (Exception $e) {
            error_log("MigracionCodigo::verArchivo - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lectura interna, usada tambien por MigracionIa cuando el asistente
     * pide un archivo dentro de su respuesta.
     *
     * @return string|null
     */
    public static function leerArchivo($origen, $rutaArchivo)
    {
        $db = MigracionDb::mig();
        $stmt = $db->prepare("SELECT ruta_zip, ruta_archivo FROM mig_codigo_indice
                              WHERE origen = :origen AND ruta_archivo = :ruta LIMIT 1");
        $stmt->bindValue(':origen', $origen);
        $stmt->bindValue(':ruta', $rutaArchivo);
        $stmt->execute();
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila || !is_file($fila['ruta_zip'])) {
            return null;
        }

        $zip = new ZipArchive();
        if ($zip->open($fila['ruta_zip']) !== true) {
            return null;
        }
        $contenido = $zip->getFromName($fila['ruta_archivo']);
        $zip->close();

        return $contenido === false ? null : $contenido;
    }

    /**
     * Busca en el indice por palabra: la IA pregunta "donde se crea un
     * estudiante" y esto le devuelve las rutas candidatas.
     */
    public static function buscar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $termino = Flight::request()->query['q'] ?? '';
            if (trim($termino) === '') {
                Flight::json(['error' => 'Falta el término de búsqueda'], 400);
                return;
            }

            Flight::json(self::buscarEnIndice($termino));
        } catch (Exception $e) {
            error_log("MigracionCodigo::buscar - " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function buscarEnIndice($termino, $limite = 20)
    {
        $db = MigracionDb::mig();
        $stmt = $db->prepare("SELECT origen, ruta_archivo, tamano, firmas
                              FROM mig_codigo_indice
                              WHERE ruta_archivo LIKE :t1 OR firmas LIKE :t2
                              ORDER BY origen, ruta_archivo
                              LIMIT {$limite}");
        $patron = '%' . $termino . '%';
        $stmt->bindValue(':t1', $patron);
        $stmt->bindValue(':t2', $patron);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Indice compacto para meterle a la IA en el contexto.
     */
    public static function indiceParaContexto($origen = 'back', $limite = 400)
    {
        $db = MigracionDb::mig();
        $stmt = $db->prepare("SELECT ruta_archivo, tamano, firmas FROM mig_codigo_indice
                              WHERE origen = :origen ORDER BY ruta_archivo LIMIT {$limite}");
        $stmt->bindValue(':origen', $origen);
        $stmt->execute();

        $lineas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $firmas = $f['firmas'] ? ' :: ' . $f['firmas'] : '';
            $lineas[] = $f['ruta_archivo'] . ' (' . round($f['tamano'] / 1024) . ' KB)' . $firmas;
        }

        return implode("\n", $lineas);
    }

    // =====================================================
    // INDEXADO
    // =====================================================

    private static function indexar($rutaZip, $origen, $version)
    {
        $db = MigracionDb::mig();

        $stmt = $db->prepare("DELETE FROM mig_codigo_indice WHERE origen = :origen");
        $stmt->bindValue(':origen', $origen);
        $stmt->execute();

        $zip = new ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            throw new Exception('No se pudo abrir el zip del código');
        }

        $extensiones = ['php', 'ts', 'html', 'scss', 'css', 'json'];
        $insertar = $db->prepare("INSERT INTO mig_codigo_indice
            (id, origen, version, ruta_zip, ruta_archivo, tamano, firmas)
            VALUES (:id, :origen, :version, :ruta_zip, :ruta_archivo, :tamano, :firmas)");

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $info = $zip->statIndex($i);
            $ruta = $info['name'];

            if (substr($ruta, -1) === '/') {
                continue;
            }
            $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensiones, true)) {
                continue;
            }
            // Nada de dependencias: no aportan y son miles de archivos.
            if (strpos($ruta, 'node_modules/') !== false || strpos($ruta, 'vendor/') !== false) {
                continue;
            }

            $firmas = '';
            // Solo se leen archivos razonables para sacarles las firmas.
            if ($info['size'] < 600000 && in_array($ext, ['php', 'ts'], true)) {
                $contenido = $zip->getFromIndex($i);
                if ($contenido !== false) {
                    $firmas = self::extraerFirmas($contenido, $ext);
                }
            }

            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':origen', $origen);
            $insertar->bindValue(':version', $version);
            $insertar->bindValue(':ruta_zip', $rutaZip);
            $insertar->bindValue(':ruta_archivo', $ruta);
            $insertar->bindValue(':tamano', $info['size'], PDO::PARAM_INT);
            $insertar->bindValue(':firmas', $firmas);
            $insertar->execute();

            $total++;
        }

        $zip->close();
        return $total;
    }

    private static function extraerFirmas($contenido, $extension)
    {
        $firmas = [];

        if ($extension === 'php') {
            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $contenido, $m)) {
                $firmas[] = 'class ' . $m[1];
            }
            if (preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', $contenido, $m)) {
                foreach (array_slice($m[1], 0, 30) as $f) {
                    $firmas[] = $f . '()';
                }
            }
        } else {
            if (preg_match_all('/export\s+class\s+([A-Za-z0-9_]+)/', $contenido, $m)) {
                foreach ($m[1] as $c) {
                    $firmas[] = 'class ' . $c;
                }
            }
            if (preg_match_all('/^\s{2}([a-zA-Z0-9_]+)\s*\(/m', $contenido, $m)) {
                foreach (array_slice($m[1], 0, 25) as $f) {
                    $firmas[] = $f . '()';
                }
            }
        }

        return implode(', ', array_unique($firmas));
    }
}
