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

    /**
     * Calcula el dígito de verificación (DV) de un NIT con el algoritmo de la
     * DIAN: módulo 11 sobre los dígitos del NIT con los pesos primos oficiales,
     * de derecha a izquierda. Recibe el NIT sin DV; ignora puntos, guiones y
     * espacios. Devuelve el DV como string de un dígito, o null si no hay dígitos.
     */
    public static function calcularDigitoVerificacion($nit)
    {
        $digitos = preg_replace('/\D/', '', (string) $nit);
        if ($digitos === '') {
            return null;
        }

        $pesos = array(3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71);
        $suma = 0;
        $invertido = strrev($digitos);
        $largo = strlen($invertido);
        for ($i = 0; $i < $largo && $i < count($pesos); $i++) {
            $suma += intval($invertido[$i]) * $pesos[$i];
        }

        $residuo = $suma % 11;
        return (string) ($residuo > 1 ? 11 - $residuo : $residuo);
    }

    /**
     * Indica si el tipo de identificación es NIT. Se resuelve por nombre en
     * tipos_identificacion (tabla global, sin id_tenant) para no quemar el id.
     */
    public static function esTipoNit($db, $id_tipo_identificacion)
    {
        if (!$id_tipo_identificacion) {
            return false;
        }

        $sentence = $db->prepare("SELECT nombre FROM tipos_identificacion WHERE id = :id");
        $sentence->bindValue(':id', $id_tipo_identificacion);
        $sentence->execute();
        $nombre = $sentence->fetchColumn();

        return $nombre !== false && strtoupper(trim($nombre)) === 'NIT';
    }

    /**
     * Normaliza el número y el DV de una identificación antes de guardarla.
     * Si el tipo es NIT: el número queda solo con dígitos y el DV es el recibido
     * (el que trae el RUT) cuando es un dígito válido, o el calculado si no llega.
     * Para los demás tipos el número no se toca y el DV queda en null.
     *
     * @return array [numero_identificacion, digito_verificacion]
     */
    public static function normalizarIdentificacion($db, $id_tipo_identificacion, $numero_identificacion, $digito_verificacion = null)
    {
        if (!self::esTipoNit($db, $id_tipo_identificacion)) {
            return array($numero_identificacion, null);
        }

        $numero = preg_replace('/\D/', '', (string) $numero_identificacion);
        $dv = trim((string) $digito_verificacion);
        if (!preg_match('/^\d$/', $dv)) {
            $dv = self::calcularDigitoVerificacion($numero);
        }

        return array($numero, $dv);
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

    /**
     * Lista plana de personas para el buscador del menu principal.
     *
     * Devuelve una fila por DESTINO, no por persona: una misma persona puede
     * ser colaboradora y ademas representante de dos clientes, y en ese caso
     * salen tres filas con el mismo id_persona. El front las agrupa y le
     * muestra la lista al usuario para que escoja a donde ir.
     *
     * El `id_destino` es el id del registro al que se navega (cliente,
     * colaborador o representante). El `id_secundario` solo lo usa el
     * representante y trae el id del cliente, porque la pantalla de editar
     * representante pide los dos en la ruta.
     *
     * El nombre del cliente es la razon social cuando la tiene (empresas) y
     * los nombres y apellidos cuando es persona natural.
     *
     * Solo se traen los campos que el buscador necesita (nombre, documento,
     * tipo, id del destino, estado y un detalle corto). Nada de foto,
     * direccion ni telefono, porque esta consulta se carga completa al abrir
     * la aplicacion y el peso del JSON si importa.
     *
     * Los inactivos NO se filtran: vienen con activo = 0 para que el front los
     * pinte en gris y los ordene de ultimos.
     *
     * Tampoco valida permisos. El filtrado por permiso se hace en el front,
     * igual que en el resto del sistema.
     */
    public static function getBuscador()
    {
        try {
            $db = Flight::db();

            // El id_tenant va con tres nombres distintos porque PDO sin
            // emulacion de prepares no permite repetir un parametro nombrado.
            $sentence = $db->prepare("
            SELECT
                p.id AS id_persona,
                COALESCE(NULLIF(TRIM(p.razon_social), ''), TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))) AS nombre_completo,
                p.numero_identificacion,
                'cliente' AS tipo,
                c.id AS id_destino,
                NULL AS id_secundario,
                c.activo AS activo,
                NULL AS detalle
            FROM clientes c
            INNER JOIN personas p ON p.id = c.id_persona AND p.id_tenant = c.id_tenant
            WHERE c.id_tenant = :id_tenant_cli

            UNION ALL

            SELECT
                p.id AS id_persona,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                p.numero_identificacion,
                'colaborador' AS tipo,
                co.id AS id_destino,
                NULL AS id_secundario,
                co.activo AS activo,
                car.nombre AS detalle
            FROM colaboradores co
            INNER JOIN personas p ON p.id = co.id_persona AND p.id_tenant = co.id_tenant
            LEFT JOIN cargos car ON car.id = co.id_cargo AND car.id_tenant = co.id_tenant
            WHERE co.id_tenant = :id_tenant_col

            UNION ALL

            SELECT
                p.id AS id_persona,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                p.numero_identificacion,
                'representante' AS tipo,
                r.id AS id_destino,
                r.id_cliente AS id_secundario,
                CASE WHEN r.activo = 1 AND c.activo = 1 THEN 1 ELSE 0 END AS activo,
                CONCAT(COALESCE(tr.nombre, 'Representante'), ' de ',
                       COALESCE(NULLIF(TRIM(pc.razon_social), ''), TRIM(CONCAT_WS(' ', pc.primer_nombre, pc.primer_apellido)))) AS detalle
            FROM representantes r
            INNER JOIN personas p ON p.id = r.id_persona AND p.id_tenant = r.id_tenant
            INNER JOIN clientes c ON c.id = r.id_cliente AND c.id_tenant = r.id_tenant
            INNER JOIN personas pc ON pc.id = c.id_persona AND pc.id_tenant = c.id_tenant
            LEFT JOIN tipos_representante tr ON tr.id = r.id_tipo_representante
            WHERE r.id_tenant = :id_tenant_rep

            ORDER BY nombre_completo, tipo");

            $idTenant = TenantContext::id();
            $sentence->bindValue(':id_tenant_cli', $idTenant, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_col', $idTenant, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant_rep', $idTenant, PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            // PDO devuelve el activo como cadena y en JavaScript la cadena "0"
            // es verdadera, asi que se entrega como entero para que el front
            // pueda evaluarlo directo.
            foreach ($response as $indice => $fila) {
                $response[$indice]['activo'] = (int) $fila['activo'];
            }

            error_log("getBuscador: Se devolvieron " . count($response) . " destinos de personas");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getBuscador: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las personas del buscador'), 500);
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
            $razon_social = self::normalizarTexto(isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null);
            $digito_verificacion = isset(Flight::request()->data['digito_verificacion']) ? Flight::request()->data['digito_verificacion'] : null;

            // NIT: número solo con dígitos y DV recibido (RUT) o calculado.
            list($numero_identificacion, $digito_verificacion) = self::normalizarIdentificacion($db, $id_tipo_identificacion, $numero_identificacion, $digito_verificacion);

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
                razon_social,
                digito_verificacion
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
                :razon_social,
                :digito_verificacion
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
            $sentence->bindParam(':digito_verificacion', $digito_verificacion);

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
            $razon_social = self::normalizarTexto(isset(Flight::request()->data['razon_social']) ? Flight::request()->data['razon_social'] : null);
            $digito_verificacion = isset(Flight::request()->data['digito_verificacion']) ? Flight::request()->data['digito_verificacion'] : null;

            error_log("Datos recibidos para actualización: id=$id, razon_social=$razon_social, primer_nombre=$primer_nombre, numero_identificacion=$numero_identificacion");

            // Validar solo los datos mínimos necesarios
            if (!$id || !$id_tipo_identificacion || !$numero_identificacion) {
                Flight::json(array('error' => 'Faltan datos obligatorios'), 400);
                return;
            }

            // NIT: número solo con dígitos y DV recibido (RUT) o calculado.
            list($numero_identificacion, $digito_verificacion) = self::normalizarIdentificacion($db, $id_tipo_identificacion, $numero_identificacion, $digito_verificacion);

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
                razon_social = :razon_social,
                digito_verificacion = :digito_verificacion
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
            $sentence->bindParam(':digito_verificacion', $digito_verificacion);

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
     * Incluye: clientes activos y colaboradores activos
     * Para colaboradores: devuelve género, sobrenombre, si es docente y nombre_corto del cargo
     */
    public static function getCumpleanosHoy()
    {
        try {
            $db = Flight::db();

            // Colaboradores activos que cumplen años hoy
            // NÚCLEO: la consulta de clientes y el JOIN a docentes pertenecen al dominio educativo;
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