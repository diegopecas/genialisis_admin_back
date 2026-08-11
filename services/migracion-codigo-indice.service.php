<?php
/**
 * Tabla migracion_codigo_indice.
 *
 * El zip del back y del front de Genialisis producto queda guardado y aqui
 * va su indice: ruta, tamano y firmas (clases y funciones).
 *
 * Al asistente se le manda solo el indice. Cuando necesita ver como se
 * crea un estudiante, pide el archivo por ruta y el back se lo entrega.
 * Mandarle 369 archivos en cada turno no cabe ni sirve.
 */
class MigracionCodigoIndice
{
    const EXTENSIONES = array('php', 'ts', 'html', 'scss', 'css', 'json');

    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $origen = isset(Flight::request()->query['origen']) ? Flight::request()->query['origen'] : null;
            $db = Flight::db();
            $filtroOrigen = $origen ? ' AND origen = :origen' : '';

            $sentence = $db->prepare("SELECT id, origen, version, ruta_archivo, tamano, firmas, fecha
                                      FROM migracion_codigo_indice
                                      WHERE id_tenant = :id_tenant {$filtroOrigen}
                                      ORDER BY origen, ruta_archivo");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            if ($origen) {
                $sentence->bindParam(':origen', $origen);
            }
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, origen, version, ruta_zip, ruta_archivo, tamano, firmas, fecha
                                      FROM migracion_codigo_indice
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Sube el zip del codigo y lo indexa. Reemplaza el indice anterior de
     * ese origen: si se sube un back nuevo, el viejo deja de existir.
     */
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $origen = isset($_POST['origen']) ? $_POST['origen'] : null;
            $version = isset($_POST['version']) && $_POST['version'] !== '' ? $_POST['version'] : date('Y-m-d');

            if (!in_array($origen, array('back', 'front'), true)) {
                Flight::json(array('error' => 'El origen debe ser "back" o "front"'), 400);
                return;
            }
            if (!isset($_FILES['zip']) || $_FILES['zip']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No llegó el archivo zip'), 400);
                return;
            }
            if (!class_exists('ZipArchive')) {
                Flight::json(array('error' => 'ZipArchive no está habilitado en PHP'), 500);
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
                Flight::json(array('error' => 'No se pudo guardar el zip'), 500);
                return;
            }

            $indexados = self::indexar($rutaZip, $origen, $version);

            Flight::json(array(
                'success' => true,
                'origen' => $origen,
                'version' => $version,
                'archivos_indexados' => $indexados
            ));
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $origen = isset(Flight::request()->data['origen']) ? Flight::request()->data['origen'] : null;

            if (!in_array($origen, array('back', 'front'), true)) {
                Flight::json(array('error' => 'El origen debe ser "back" o "front"'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM migracion_codigo_indice
                                      WHERE origen = :origen AND id_tenant = :id_tenant");
            $sentence->bindParam(':origen', $origen);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('origen' => $origen, 'borrados' => $sentence->rowCount()));
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Contenido de un archivo del zip. Es lo que se llama cuando el
     * asistente pide "muéstrame services/estudiantes.service.php".
     */
    public static function getArchivo()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $origen = isset(Flight::request()->query['origen']) ? Flight::request()->query['origen'] : null;
            $ruta = isset(Flight::request()->query['ruta']) ? Flight::request()->query['ruta'] : null;

            if (!$origen || !$ruta) {
                Flight::json(array('error' => 'Faltan el origen y la ruta del archivo'), 400);
                return;
            }

            $contenido = self::leerArchivo($origen, $ruta);
            if ($contenido === null) {
                Flight::json(array('error' => 'El archivo no está en el índice'), 404);
                return;
            }

            Flight::json(array(
                'origen' => $origen,
                'ruta' => $ruta,
                'tamano' => strlen($contenido),
                'contenido' => $contenido
            ));
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::getArchivo - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Busca por ruta o por firma: "dónde se crea un estudiante" devuelve
     * las rutas candidatas.
     */
    public static function getBusqueda()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $termino = isset(Flight::request()->query['q']) ? trim(Flight::request()->query['q']) : '';
            if ($termino === '') {
                Flight::json(array('error' => 'Falta el término de búsqueda'), 400);
                return;
            }

            Flight::json(self::buscar($termino));
        } catch (Exception $e) {
            error_log("MigracionCodigoIndice::getBusqueda - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * @return string|null
     */
    public static function leerArchivo($origen, $ruta_archivo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ruta_zip, ruta_archivo FROM migracion_codigo_indice
                                  WHERE origen = :origen AND ruta_archivo = :ruta AND id_tenant = :id_tenant
                                  LIMIT 1");
        $sentence->bindValue(':origen', $origen);
        $sentence->bindValue(':ruta', $ruta_archivo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

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
     * @return array
     */
    public static function buscar($termino, $limite = 20)
    {
        $db = Flight::db();
        $limite = (int)$limite;
        $sentence = $db->prepare("SELECT origen, ruta_archivo, tamano, firmas
                                  FROM migracion_codigo_indice
                                  WHERE id_tenant = :id_tenant AND (ruta_archivo LIKE :t1 OR firmas LIKE :t2)
                                  ORDER BY origen, ruta_archivo
                                  LIMIT {$limite}");
        $patron = '%' . $termino . '%';
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':t1', $patron);
        $sentence->bindValue(':t2', $patron);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Indice compacto para el contexto del asistente.
     *
     * @return string
     */
    public static function indiceParaContexto($origen, $limite = 400)
    {
        $db = Flight::db();
        $limite = (int)$limite;
        $sentence = $db->prepare("SELECT ruta_archivo, tamano, firmas
                                  FROM migracion_codigo_indice
                                  WHERE origen = :origen AND id_tenant = :id_tenant
                                  ORDER BY ruta_archivo LIMIT {$limite}");
        $sentence->bindValue(':origen', $origen);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $lineas = array();
        foreach ($sentence->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $firmas = $fila['firmas'] ? ' :: ' . $fila['firmas'] : '';
            $lineas[] = $fila['ruta_archivo'] . ' (' . round($fila['tamano'] / 1024) . ' KB)' . $firmas;
        }

        return implode("\n", $lineas);
    }

    // =====================================================
    // INDEXADO
    // =====================================================

    private static function indexar($rutaZip, $origen, $version)
    {
        $db = Flight::db();

        $sentence = $db->prepare("DELETE FROM migracion_codigo_indice
                                  WHERE origen = :origen AND id_tenant = :id_tenant");
        $sentence->bindValue(':origen', $origen);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        $zip = new ZipArchive();
        if ($zip->open($rutaZip) !== true) {
            throw new Exception('No se pudo abrir el zip del código');
        }

        $insertar = $db->prepare("INSERT INTO migracion_codigo_indice
            (id, id_tenant, origen, version, ruta_zip, ruta_archivo, tamano, firmas)
            VALUES (:id, :id_tenant, :origen, :version, :ruta_zip, :ruta_archivo, :tamano, :firmas)");

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $info = $zip->statIndex($i);
            $ruta = $info['name'];

            if (substr($ruta, -1) === '/') {
                continue;
            }
            $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            if (!in_array($extension, self::EXTENSIONES, true)) {
                continue;
            }
            // Las dependencias no aportan y son miles de archivos.
            if (strpos($ruta, 'node_modules/') !== false || strpos($ruta, 'vendor/') !== false) {
                continue;
            }

            $firmas = '';
            if ($info['size'] < 600000 && in_array($extension, array('php', 'ts'), true)) {
                $contenido = $zip->getFromIndex($i);
                if ($contenido !== false) {
                    $firmas = self::extraerFirmas($contenido, $extension);
                }
            }

            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
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
        $firmas = array();

        if ($extension === 'php') {
            if (preg_match('/class\s+([A-Za-z0-9_]+)/', $contenido, $coincidencias)) {
                $firmas[] = 'class ' . $coincidencias[1];
            }
            if (preg_match_all('/function\s+([A-Za-z0-9_]+)\s*\(/', $contenido, $coincidencias)) {
                foreach (array_slice($coincidencias[1], 0, 30) as $funcion) {
                    $firmas[] = $funcion . '()';
                }
            }
        } else {
            if (preg_match_all('/export\s+class\s+([A-Za-z0-9_]+)/', $contenido, $coincidencias)) {
                foreach ($coincidencias[1] as $clase) {
                    $firmas[] = 'class ' . $clase;
                }
            }
            if (preg_match_all('/^\s{2}([a-zA-Z0-9_]+)\s*\(/m', $contenido, $coincidencias)) {
                foreach (array_slice($coincidencias[1], 0, 25) as $metodo) {
                    $firmas[] = $metodo . '()';
                }
            }
        }

        return implode(', ', array_unique($firmas));
    }
}
