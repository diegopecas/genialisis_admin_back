<?php
class Upload
{
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $maxSize = 5242880; // 5MB

    // Videos permitidos para la galería (publicación como Reel sin reprocesar).
    private static $allowedVideoTypes = ['video/mp4', 'video/quicktime'];

    // Defaults de galería (MB) si la clave no está en configuracion_global.
    private static $defaultImagenMb = 10;
    private static $defaultVideoMb = 32;

    // Calidad al reescribir un JPEG (corrección de orientación).
    private static $jpegQuality = 90;

    public static function uploadProductImage()
    {
        try {
            // Obtener directorio de uploads por tenant
            $uploadDir = UploadHelper::getUploadPath('productos');
            
            // Crear directorio si no existe
            UploadHelper::ensureDirectoryExists($uploadDir);

            // Verificar si se recibió archivo
            if (!isset($_FILES['imagen'])) {
                Flight::json(['error' => 'No se recibió ningún archivo'], 400);
                return;
            }

            $file = $_FILES['imagen'];

            // Verificar errores de upload (incluye exceso de tamaño a nivel PHP)
            if ($file['error'] !== UPLOAD_ERR_OK) {
                Flight::json(['error' => 'Error al cargar el archivo'], 400);
                return;
            }

            // Verificar tipo de archivo
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, self::$allowedTypes)) {
                Flight::json(['error' => 'Tipo de archivo no permitido. Solo se permiten imágenes JPG, PNG, GIF y WEBP'], 400);
                return;
            }

            // Verificar tamaño
            if ($file['size'] > self::$maxSize) {
                Flight::json(['error' => 'El archivo excede el tamaño máximo permitido (5MB)'], 400);
                return;
            }

            // Obtener ID del producto si existe
            $idProducto = isset($_POST['id_producto']) ? $_POST['id_producto'] : 'temp';
            
            // Generar nombre único
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = time();
            $fileName = 'producto_' . $idProducto . '_' . $timestamp . '.' . $extension;
            
            $uploadPath = $uploadDir . $fileName;

            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Retornar ruta relativa para guardar en BD
                $relativePath = UploadHelper::getRelativePath('productos', $fileName);
                
                Flight::json([
                    'success' => true,
                    'filename' => $fileName,
                    'path' => $relativePath
                ]);
            } else {
                Flight::json(['error' => 'Error al guardar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en uploadProductImage: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function deleteProductImage()
    {
        try {
            $fileName = Flight::request()->data['filename'];
            
            if (!$fileName) {
                Flight::json(['error' => 'Nombre de archivo no proporcionado'], 400);
                return;
            }

            // Usar helper para eliminar archivo del tenant actual
            if (UploadHelper::deleteFileByName('productos', $fileName)) {
                Flight::json(['success' => true]);
            } else {
                Flight::json(['error' => 'No se pudo eliminar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en deleteProductImage: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function getProductImage($filename)
    {
        try {
            $uploadDir = UploadHelper::getUploadPath('productos');
            $filePath = $uploadDir . basename($filename);
            
            if (!file_exists($filePath)) {
                Flight::response()->status(404);
                return;
            }

            $fileType = mime_content_type($filePath);
            
            Flight::response()->header('Content-Type', $fileType);
            Flight::response()->header('Content-Length', filesize($filePath));
            Flight::response()->header('Cache-Control', 'public, max-age=31536000');
            
            readfile($filePath);
            
        } catch (Exception $e) {
            error_log("Error en getProductImage: " . $e->getMessage());
            Flight::response()->status(500);
        }
    }

    /**
     * Tamaño máximo en bytes para una clave de configuracion_global.
     * El valor configurado nunca supera lo que el servidor puede recibir de
     * verdad: se topa contra upload_max_filesize / post_max_size. Así el límite
     * que se anuncia es el que se cumple.
     *
     * @param string $clave      clave en configuracion_global (valor_numero, en MB)
     * @param float  $defaultMb  valor a usar si la clave no está configurada
     * @return int   bytes
     */
    private static function getMaxBytes($clave, $defaultMb)
    {
        $mb = $defaultMb;
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT valor_numero
                FROM configuracion_global
                WHERE clave = :clave AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $stmt->bindValue(':clave', $clave);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['valor_numero'] !== null && (float)$row['valor_numero'] > 0) {
                $mb = (float)$row['valor_numero'];
            }
        } catch (Exception $e) {
            error_log("getMaxBytes({$clave}): no se pudo leer config, uso default {$defaultMb}MB. " . $e->getMessage());
        }

        $bytes = (int)round($mb * 1024 * 1024);
        return min($bytes, self::getLimiteServidorBytes());
    }

    /**
     * Lo que el servidor realmente acepta en un POST con un archivo.
     * Es el menor entre upload_max_filesize y post_max_size, menos un margen
     * para el overhead del multipart (cabeceras + campos del formulario).
     * Si ninguno declara límite, no hay tope.
     */
    private static function getLimiteServidorBytes()
    {
        $limites = [];
        foreach (['upload_max_filesize', 'post_max_size'] as $directiva) {
            $bytes = self::iniABytes(ini_get($directiva));
            if ($bytes > 0) {
                $limites[] = $bytes;
            }
        }
        if (empty($limites)) {
            return PHP_INT_MAX;
        }

        $margen = 262144; // 256KB para cabeceras multipart y campos del form
        return max(1, min($limites) - $margen);
    }

    /**
     * Convierte un valor de php.ini ('32M', '512K', '1G') a bytes.
     * Devuelve 0 si no hay límite declarado ('0' o vacío).
     */
    private static function iniABytes($valor)
    {
        $valor = trim((string)$valor);
        if ($valor === '' || $valor === '0') {
            return 0;
        }
        $numero = (int)$valor;
        switch (strtolower(substr($valor, -1))) {
            case 'g':
                return $numero * 1024 * 1024 * 1024;
            case 'm':
                return $numero * 1024 * 1024;
            case 'k':
                return $numero * 1024;
            default:
                return $numero;
        }
    }

    /**
     * Endereza una imagen segun su etiqueta EXIF de orientacion y borra la
     * etiqueta, dejando los pixeles como unica verdad.
     *
     * Solo aplica a JPEG: es el unico formato de los permitidos que lleva EXIF.
     * Si no hay EXIF, la orientacion ya es normal, o la extension exif no esta
     * disponible, no toca el archivo.
     *
     * No lanza: un fallo aqui no debe tumbar una subida que ya es valida. Deja
     * rastro en el log y la imagen queda como llego (el usuario puede girarla a
     * mano desde la galeria).
     *
     * @param string $ruta  ruta absoluta del archivo ya movido
     * @return bool  true si se reescribio el archivo
     */
    private static function corregirOrientacionExif($ruta)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return false;
        }

        $info = @getimagesize($ruta);
        if (!$info || $info[2] !== IMAGETYPE_JPEG) {
            return false;
        }

        try {
            $exif = @exif_read_data($ruta);
            if (!$exif || empty($exif['Orientation'])) {
                return false;
            }

            // 1 = normal. 2/4/5/7 incluyen espejado; se corrige la rotacion y se
            // ignora el espejo, que en fotos de celular no se da en la practica.
            $orientacion = (int)$exif['Orientation'];
            $grados = 0;
            switch ($orientacion) {
                case 3:
                case 4:
                    $grados = 180;
                    break;
                case 5:
                case 6:
                    $grados = -90; // imagerotate gira antihorario
                    break;
                case 7:
                case 8:
                    $grados = 90;
                    break;
                default:
                    return false; // 1 o valor desconocido: nada que hacer
            }

            $img = @imagecreatefromjpeg($ruta);
            if (!$img) {
                return false;
            }

            $rotada = @imagerotate($img, $grados, 0);
            if (!$rotada) {
                imagedestroy($img);
                return false;
            }

            // imagejpeg no escribe EXIF: al reescribir, la etiqueta desaparece
            // sola y no queda nadie que vuelva a girar la imagen.
            $ok = imagejpeg($rotada, $ruta, self::$jpegQuality);

            imagedestroy($img);
            imagedestroy($rotada);

            if ($ok) {
                error_log("corregirOrientacionExif: {$ruta} enderezada (Orientation={$orientacion}, {$grados} grados)");
            }
            return (bool)$ok;
        } catch (Exception $e) {
            error_log("corregirOrientacionExif: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Límites vigentes de la galería, ya topados contra el servidor.
     * El front los consulta para anunciar el número real y validar antes de subir.
     */
    public static function getLimitesGaleria()
    {
        $imagen = self::getMaxBytes('galeria_imagen_max_mb', self::$defaultImagenMb);
        $video = self::getMaxBytes('galeria_video_max_mb', self::$defaultVideoMb);

        Flight::json([
            'imagen_max_mb' => round($imagen / 1048576, 2),
            'video_max_mb' => round($video / 1048576, 2),
            'imagen_max_bytes' => $imagen,
            'video_max_bytes' => $video
        ]);
    }

    /**
     * Subir imagen O video de galería.
     * - Imágenes: JPG/PNG/GIF/WEBP, máx galeria_imagen_max_mb (configuracion_global).
     * - Videos: MP4/MOV, máx galeria_video_max_mb (configuracion_global).
     * Devuelve además 'tipo_media' ('imagen' | 'video') para que el front lo guarde.
     */
    public static function uploadGaleriaImagen()
    {
        try {
            // Obtener tenant del header
            $tenant = isset($_SERVER['HTTP_X_TENANT']) ? $_SERVER['HTTP_X_TENANT'] : 'default';
            $tenant = preg_replace('/[^a-z0-9\-_]/i', '', $tenant);
            
            // Obtener ID de galería
            $idGaleria = isset($_POST['id_galeria']) ? $_POST['id_galeria'] : 'temp';
            
            // Directorio: galeria_privada/{tenant}/{id_galeria}/
            $baseDir = __DIR__ . '/../galeria_privada/' . $tenant . '/' . $idGaleria . '/';
            
            // Crear directorio si no existe
            if (!file_exists($baseDir)) {
                mkdir($baseDir, 0755, true);
            }

            // Si el POST superó post_max_size, PHP descarta el cuerpo entero:
            // $_FILES y $_POST llegan vacíos. Lo detectamos para dar un mensaje útil
            // en vez del genérico "No se recibió ningún archivo".
            $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
            if ($contentLength > 0 && empty($_FILES) && empty($_POST)) {
                $postMax = ini_get('post_max_size');
                Flight::json(['error' => 'El archivo supera el límite del servidor (post_max_size = ' . $postMax . '). Usa un archivo más pequeño o aumenta el límite del hosting.'], 400);
                return;
            }

            // Verificar si se recibió archivo
            if (!isset($_FILES['imagen'])) {
                Flight::json(['error' => 'No se recibió ningún archivo'], 400);
                return;
            }

            $file = $_FILES['imagen'];

            // Verificar errores de upload (incluye exceso de tamaño a nivel PHP)
            if ($file['error'] !== UPLOAD_ERR_OK) {
                if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                    Flight::json(['error' => 'El archivo excede el tamaño permitido por el servidor. Revisa el límite del hosting o reduce el archivo.'], 400);
                } else {
                    Flight::json(['error' => 'Error al cargar el archivo'], 400);
                }
                return;
            }

            // Detectar tipo real del archivo
            $fileType = mime_content_type($file['tmp_name']);
            $esImagen = in_array($fileType, self::$allowedTypes);
            $esVideo = in_array($fileType, self::$allowedVideoTypes);

            if (!$esImagen && !$esVideo) {
                Flight::json(['error' => 'Tipo de archivo no permitido. Imágenes: JPG, PNG, GIF, WEBP. Videos: MP4, MOV.'], 400);
                return;
            }

            // Validar tamaño según tipo. Los topes salen de configuracion_global,
            // ya recortados a lo que el servidor puede recibir.
            if ($esImagen) {
                $maxImagenBytes = self::getMaxBytes('galeria_imagen_max_mb', self::$defaultImagenMb);
                if ($file['size'] > $maxImagenBytes) {
                    $mb = round($maxImagenBytes / 1048576);
                    Flight::json(['error' => 'La imagen excede el tamaño máximo permitido (' . $mb . 'MB)'], 400);
                    return;
                }
                $tipoMedia = 'imagen';
            } else {
                $maxVideoBytes = self::getMaxBytes('galeria_video_max_mb', self::$defaultVideoMb);
                if ($file['size'] > $maxVideoBytes) {
                    $mb = round($maxVideoBytes / 1048576);
                    Flight::json(['error' => 'El video excede el tamaño máximo permitido (' . $mb . 'MB)'], 400);
                    return;
                }
                $tipoMedia = 'video';
            }
            
            // Generar nombre único (sin el id_galeria porque ya está en la carpeta)
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $timestamp = time();
            $random = substr(md5(uniqid()), 0, 8);
            $fileName = $timestamp . '_' . $random . '.' . $extension;
            
            $uploadPath = $baseDir . $fileName;

            // Mover archivo
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Enderezar segun EXIF. Los celulares guardan la foto acostada y
                // marcan la rotacion en una etiqueta: el navegador la respeta, GD no.
                // Sin esto la imagen se ve bien en la galeria pero sale acostada en
                // Instagram. Se giran los pixeles y se borra la etiqueta.
                if ($esImagen) {
                    self::corregirOrientacionExif($uploadPath);
                }

                // Retornar ruta relativa: {id_galeria}/{filename}
                $relativePath = $idGaleria . '/' . $fileName;
                
                Flight::json([
                    'success' => true,
                    'filename' => $fileName,
                    'ruta' => $relativePath,
                    'tipo_media' => $tipoMedia
                ]);
            } else {
                Flight::json(['error' => 'Error al guardar el archivo'], 500);
            }

        } catch (Exception $e) {
            error_log("Error en uploadGaleriaImagen: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}