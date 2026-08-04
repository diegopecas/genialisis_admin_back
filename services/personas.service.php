<?php
class Personas
{
    /**
     * Normaliza un campo de texto antes de guardarlo:
     * quita espacios sobrantes y convierte la cadena vacia en NULL.
     * Se usa en los nombres y apellidos para que la concatenacion del nombre
     * completo no produzca espacios dobles ni valores basura.
     */
    private static function normalizarTexto($valor)
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim((string) $valor);

        return $valor === '' ? null : $valor;
    }

    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.id_tenant = :id_tenant
        ORDER BY p.id DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            error_log("getAll: Se encontraron " . count($response) . " registros de personas");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las personas'), 500);
        }
    }

    public static function getById($id)
    {
        try {
            error_log("getById: Buscando persona con ID: $id");

            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.id = :id AND p.id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                error_log("getById: No se encontró persona con ID: $id");
                Flight::json(array('error' => 'No se encontró la persona con el ID especificado'), 404);
                return;
            }

            error_log("getById: Persona encontrada con ID: $id");
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getById: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener la persona'), 500);
        }
    }

    public static function getByIdentificacion($id_tipo_identificacion, $numero_identificacion)
    {
        try {
            error_log("getByIdentificacion: Buscando persona con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");

            $db = Flight::db();
            $sentence = $db->prepare("SELECT 
            p.*, 
            ti.nombre AS tipo_identificacion,
            g.nombre AS nombre_genero,
            c.nombre AS nombre_ciudad
        FROM personas p
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE p.id_tipo_identificacion = :id_tipo_identificacion 
        AND p.numero_identificacion = :numero_identificacion
        AND p.id_tenant = :id_tenant");

            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                error_log("getByIdentificacion: No se encontró persona con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");
                Flight::json(array());
                return;
            }

            error_log("getByIdentificacion: Persona encontrada con tipo ID: $id_tipo_identificacion y número: $numero_identificacion");
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getByIdentificacion: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al buscar la persona por identificación'), 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            // Obtener datos de la solicitud
            $primer_nombre = self::normalizarTexto(isset(Flight::request()->data['primer_nombre']) ? Flight::request()->data['primer_nombre'] : null);
            $segundo_nombre = self::normalizarTexto(isset(Flight::request()->data['segundo_nombre']) ? Flight::request()->data['segundo_nombre'] : null);
            $primer_apellido = self::normalizarTexto(isset(Flight::request()->data['primer_apellido']) ? Flight::request()->data['primer_apellido'] : null);
            $segundo_apellido = self::normalizarTexto(isset(Flight::request()->data['segundo_apellido']) ? Flight::request()->data['segundo_apellido'] : null);
            $id_tipo_identificacion = Flight::request()->data['id_tipo_identificacion'];
            $numero_identificacion = Flight::request()->data['numero_identificacion'];
            $nacionalidad = isset(Flight::request()->data['nacionalidad']) ? Flight::request()->data['nacionalidad'] : null;
            $fecha_nacimiento = isset(Flight::request()->data['fecha_nacimiento']) ? Flight::request()->data['fecha_nacimiento'] : null;
            $id_genero = isset(Flight::request()->data['id_genero']) ? Flight::request()->data['id_genero'] : null;
            $direccion = isset(Flight::request()->data['direccion']) ? Flight::request()->data['direccion'] : null;
            $id_ciudad = isset(Flight::request()->data['id_ciudad']) ? Flight::request()->data['id_ciudad'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $telefono = isset(Flight::request()->data['telefono']) ? Flight::request()->data['telefono'] : null;
            $ocupacion = isset(Flight::request()->data['ocupacion']) ? Flight::request()->data['ocupacion'] : null;
            $rh = isset(Flight::request()->data['rh']) ? Flight::request()->data['rh'] : null;
            $razon_social = isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null;

            error_log("Datos recibidos para crear: razon_social=$razon_social, primer_nombre=$primer_nombre, primer_apellido=$primer_apellido, numero_identificacion=$numero_identificacion");

            $idTenant = TenantContext::id();
            $id = Uuid::generar();

            // Preparar la sentencia SQL
            $sentence = $db->prepare("INSERT INTO personas (
                id,
                id_tenant,
                primer_nombre, 
                segundo_nombre, 
                primer_apellido, 
                segundo_apellido, 
                id_tipo_identificacion, 
                numero_identificacion,
                nacionalidad,
                fecha_nacimiento, 
                id_genero, 
                direccion,
                id_ciudad,
                correo_electronico,
                telefono,
                ocupacion,
                rh,
                razon_social
            ) VALUES (
                :id,
                :id_tenant,
                :primer_nombre, 
                :segundo_nombre, 
                :primer_apellido, 
                :segundo_apellido, 
                :id_tipo_identificacion, 
                :numero_identificacion,
                :nacionalidad,
                :fecha_nacimiento, 
                :id_genero, 
                :direccion,
                :id_ciudad,
                :correo_electronico,
                :telefono,
                :ocupacion,
                :rh,
                :razon_social
            )");

            // Vincular los parámetros
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $sentence->bindParam(':primer_nombre', $primer_nombre);
            $sentence->bindParam(':segundo_nombre', $segundo_nombre);
            $sentence->bindParam(':primer_apellido', $primer_apellido);
            $sentence->bindParam(':segundo_apellido', $segundo_apellido);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindParam(':nacionalidad', $nacionalidad);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_genero', $id_genero);
            $sentence->bindParam(':direccion', $direccion);
            $sentence->bindParam(':id_ciudad', $id_ciudad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':telefono', $telefono);
            $sentence->bindParam(':ocupacion', $ocupacion);
            $sentence->bindParam(':rh', $rh);
            $sentence->bindParam(':razon_social', $razon_social);

            // Ejecutar la sentencia
            $ok = $sentence->execute();

            if (!$ok) {
                error_log("Error: el INSERT de persona no se ejecutó correctamente.");
                Flight::json(array('error' => 'No se pudo crear la persona. Intente de nuevo.'), 500);
                return;
            }

            error_log("ID insertado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $primer_nombre = self::normalizarTexto(isset(Flight::request()->data['primer_nombre']) ? Flight::request()->data['primer_nombre'] : null);
            $segundo_nombre = self::normalizarTexto(isset(Flight::request()->data['segundo_nombre']) ? Flight::request()->data['segundo_nombre'] : null);
            $primer_apellido = self::normalizarTexto(isset(Flight::request()->data['primer_apellido']) ? Flight::request()->data['primer_apellido'] : null);
            $segundo_apellido = self::normalizarTexto(isset(Flight::request()->data['segundo_apellido']) ? Flight::request()->data['segundo_apellido'] : null);
            $id_tipo_identificacion = Flight::request()->data['id_tipo_identificacion'];
            $numero_identificacion = Flight::request()->data['numero_identificacion'];
            $nacionalidad = isset(Flight::request()->data['nacionalidad']) ? Flight::request()->data['nacionalidad'] : null;
            $fecha_nacimiento = isset(Flight::request()->data['fecha_nacimiento']) ? Flight::request()->data['fecha_nacimiento'] : null;
            $id_genero = isset(Flight::request()->data['id_genero']) ? Flight::request()->data['id_genero'] : null;
            $direccion = isset(Flight::request()->data['direccion']) ? Flight::request()->data['direccion'] : null;
            $id_ciudad = isset(Flight::request()->data['id_ciudad']) ? Flight::request()->data['id_ciudad'] : null;
            $correo_electronico = isset(Flight::request()->data['correo_electronico']) ? Flight::request()->data['correo_electronico'] : null;
            $telefono = isset(Flight::request()->data['telefono']) ? Flight::request()->data['telefono'] : null;
            $ocupacion = isset(Flight::request()->data['ocupacion']) ? Flight::request()->data['ocupacion'] : null;
            $rh = isset(Flight::request()->data['rh']) ? Flight::request()->data['rh'] : null;
            $razon_social = isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null;

            error_log("Datos recibidos para actualización: id=$id, razon_social=$razon_social, primer_nombre=$primer_nombre, numero_identificacion=$numero_identificacion");

            // Validar solo los datos mínimos necesarios
            if (!$id || !$id_tipo_identificacion || !$numero_identificacion) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // Preparar la sentencia SQL
            $sentence = $db->prepare("UPDATE personas SET 
                primer_nombre = :primer_nombre,
                segundo_nombre = :segundo_nombre,
                primer_apellido = :primer_apellido,
                segundo_apellido = :segundo_apellido,
                id_tipo_identificacion = :id_tipo_identificacion,
                numero_identificacion = :numero_identificacion,
                nacionalidad = :nacionalidad,
                fecha_nacimiento = :fecha_nacimiento,
                id_genero = :id_genero,
                direccion = :direccion,
                id_ciudad = :id_ciudad,
                correo_electronico = :correo_electronico,
                telefono = :telefono,
                ocupacion = :ocupacion,
                rh = :rh,
                razon_social = :razon_social
            WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':primer_nombre', $primer_nombre);
            $sentence->bindParam(':segundo_nombre', $segundo_nombre);
            $sentence->bindParam(':primer_apellido', $primer_apellido);
            $sentence->bindParam(':segundo_apellido', $segundo_apellido);
            $sentence->bindParam(':id_tipo_identificacion', $id_tipo_identificacion);
            $sentence->bindParam(':numero_identificacion', $numero_identificacion);
            $sentence->bindParam(':nacionalidad', $nacionalidad);
            $sentence->bindParam(':fecha_nacimiento', $fecha_nacimiento);
            $sentence->bindParam(':id_genero', $id_genero);
            $sentence->bindParam(':direccion', $direccion);
            $sentence->bindParam(':id_ciudad', $id_ciudad);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':telefono', $telefono);
            $sentence->bindParam(':ocupacion', $ocupacion);
            $sentence->bindParam(':rh', $rh);
            $sentence->bindParam(':razon_social', $razon_social);

            // Ejecutar la sentencia
            $sentence->execute();

            error_log("ID actualizado: $id");

            // Obtener y devolver los datos actualizados
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en la ejecución del método replace: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al actualizar la persona. Inténtalo más tarde.'), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            error_log("Datos recibidos para eliminar persona: id=$id");

            if (!$id) {
                Flight::json(array('error' => 'Falta el ID de la persona a eliminar'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró la persona con el ID especificado'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'message' => 'Persona eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método delete: " . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al eliminar la persona. Inténtalo más tarde.'), 500);
        }
    }


    public static function uploadFoto($id)
    {
        try {
            $db = Flight::db();

            if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error'), 400);
                return;
            }

            $archivo = $_FILES['foto'];
            $tamanio_bytes = $archivo['size'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            $extensiones_permitidas = ['jpg', 'jpeg', 'png'];
            if (!in_array($extension, $extensiones_permitidas)) {
                Flight::json(array('error' => 'Solo se permiten archivos JPG, JPEG o PNG'), 400);
                return;
            }

            if ($tamanio_bytes > 10 * 1024 * 1024) {
                Flight::json(array('error' => 'El archivo excede el tamaño máximo de 10MB'), 400);
                return;
            }

            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            // Eliminar foto anterior si existe
            if ($persona['foto']) {
                UploadHelper::deleteFile($persona['foto']);
            }

            // Obtener directorio de uploads por tenant
            $directorio_base = UploadHelper::getUploadPath('fotos');
            UploadHelper::ensureDirectoryExists($directorio_base);

            // Eliminar cualquier foto anterior con este ID (independiente de la extensión)
            $patron = $directorio_base . $id . '.*';
            $archivos_anteriores = glob($patron);
            foreach ($archivos_anteriores as $archivo_anterior) {
                if (file_exists($archivo_anterior)) {
                    unlink($archivo_anterior);
                }
            }

            $nombre_archivo = $id . '.' . $extension;
            $ruta_completa = $directorio_base . $nombre_archivo;
            $ruta_relativa = UploadHelper::getRelativePath('fotos', $nombre_archivo);

            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                Flight::json(array('error' => 'Error al guardar el archivo'), 500);
                return;
            }

            $sentence = $db->prepare("UPDATE personas SET foto = :foto WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':foto', $ruta_relativa);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Foto subida exitosamente',
                'ruta_foto' => $ruta_relativa
            ));

        } catch (Exception $e) {
            error_log("Error en Personas::uploadFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function deleteFoto($id)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            if ($persona['foto']) {
                UploadHelper::deleteFile($persona['foto']);
            }

            $sentence = $db->prepare("UPDATE personas SET foto = NULL WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Foto eliminada exitosamente'
            ));

        } catch (Exception $e) {
            error_log("Error en Personas::deleteFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getFoto($id)
    {
        try {
            $db = Flight::db();
            
            $sentence = $db->prepare("SELECT foto FROM personas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $persona = $sentence->fetch();

            if (!$persona) {
                Flight::json(array('error' => 'Persona no encontrada'), 404);
                return;
            }

            if (!$persona['foto']) {
                Flight::json(array('foto' => null));
                return;
            }

            $ruta_completa = UploadHelper::getFullPath($persona['foto']);
            
            if (!file_exists($ruta_completa)) {
                Flight::json(array('foto' => null));
                return;
            }

            Flight::json(array('foto' => $persona['foto']));

        } catch (Exception $e) {
            error_log("Error en Personas::getFoto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtiene todos los cumpleañeros del día de hoy
     * Incluye: estudiantes activos y colaboradores activos
     * Para colaboradores: devuelve género, sobrenombre, si es docente y nombre_corto del cargo
     */
    public static function getCumpleanosHoy()
    {
        try {
            $db = Flight::db();

            // Colaboradores activos que cumplen años hoy
            // NÚCLEO: la consulta de estudiantes y el JOIN a docentes pertenecen al dominio educativo;
            // se conservan los campos del contrato (tipo, es_docente, cargo_corto) para no romper el front.
            $stmtColaboradores = $db->prepare("
                SELECT 
                    p.id AS id_persona,
                    p.primer_nombre,
                    p.primer_apellido,
                    p.id_genero,
                    'colaborador' AS tipo,
                    col.sobrenombre,
                    0 AS es_docente,
                    ca.nombre_corto AS cargo_corto
                FROM personas p
                INNER JOIN colaboradores col ON col.id_persona = p.id AND col.activo = 1
                LEFT JOIN cargos ca ON col.id_cargo = ca.id
                WHERE DAY(p.fecha_nacimiento) = DAY(CURDATE())
                AND MONTH(p.fecha_nacimiento) = MONTH(CURDATE())
                AND p.id_tenant = :id_tenant
                ORDER BY p.primer_nombre ASC
            ");
            $stmtColaboradores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtColaboradores->execute();
            $cumpleaneros = $stmtColaboradores->fetchAll();

            Flight::json($cumpleaneros);
        } catch (Exception $e) {
            error_log("Error en getCumpleanosHoy: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener cumpleañeros del día'), 500);
        }
    }
}