<?php
class DocumentosPersonas
{
    // Obtener documentos por persona
    public static function getByPersona($idPersona)
    {
        error_log("=== getByPersona llamado para persona: $idPersona ===");
        $db = Flight::db();

        // Obtener parámetros opcionales
        $idContrato = isset($_GET['id_contrato']) ? $_GET['id_contrato'] : null;
        $idTipoDocumento = isset($_GET['id_tipo_documento']) ? $_GET['id_tipo_documento'] : null;
        
        error_log("Parámetros - idContrato: " . ($idContrato ?? 'NULL') . ", idTipoDocumento: " . ($idTipoDocumento ?? 'NULL'));

        $sql = "
            SELECT 
                dp.id,
                dp.id_persona,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_persona,
                dp.id_tipo_documento,
                dp.id_contrato,
                td.codigo AS codigo_documento,
                td.nombre AS nombre_documento,
                td.requiere_vencimiento,
                td.requiere_firma,
                td.dias_alerta_vencimiento,
                dp.nombre_archivo,
                dp.ruta_archivo,
                dp.tamanio_bytes,
                dp.fecha_subida,
                dp.fecha_vencimiento,
                CASE 
                    WHEN td.requiere_vencimiento = 1 AND dp.fecha_vencimiento IS NOT NULL THEN
                        CASE 
                            WHEN dp.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                            WHEN dp.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL td.dias_alerta_vencimiento DAY) THEN 'PROXIMO_VENCER'
                            ELSE 'VIGENTE'
                        END
                    ELSE 'NO_APLICA'
                END AS estado_vencimiento,
                DATEDIFF(dp.fecha_vencimiento, CURDATE()) AS dias_para_vencer,
                dp.observaciones,
                dp.id_usuario_subio,
                CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS nombre_usuario_subio,
                dp.activo,
                dp.firma_digital_id,
                dp.firma_digital_estado,
                dp.firma_digital_url,
                dp.fecha_envio_firma,
                dp.fecha_firmado,
                dp.proveedor_firma
            FROM documentos_personas dp
            INNER JOIN personas p ON dp.id_persona = p.id
            INNER JOIN tipos_documentos td ON dp.id_tipo_documento = td.id
            LEFT JOIN usuarios u ON dp.id_usuario_subio = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            WHERE dp.id_persona = :id_persona
              AND dp.activo = 1
              AND dp.id_tenant = :id_tenant
        ";

        // Filtro por contrato
        if ($idContrato !== null) {
            $sql .= " AND dp.id_contrato = :id_contrato";
        }

        // Filtro por tipo de documento
        if ($idTipoDocumento !== null) {
            $sql .= " AND dp.id_tipo_documento = :id_tipo_documento";
        }

        $sql .= " ORDER BY dp.fecha_subida DESC";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if ($idContrato !== null) {
            $sentence->bindParam(':id_contrato', $idContrato);
        }

        if ($idTipoDocumento !== null) {
            $sentence->bindParam(':id_tipo_documento', $idTipoDocumento);
        }

        $sentence->execute();
        $response = $sentence->fetchAll();
        
        error_log("Documentos obtenidos: " . count($response));
        if (count($response) > 0) {
            error_log("Primer documento:");
            error_log("  - nombre: " . ($response[0]['nombre_archivo'] ?? 'NULL'));
            error_log("  - requiere_firma: " . ($response[0]['requiere_firma'] ?? 'NULL'));
            error_log("  - firma_digital_id: " . ($response[0]['firma_digital_id'] ?? 'NULL'));
            error_log("  - firma_digital_estado: " . ($response[0]['firma_digital_estado'] ?? 'NULL'));
        }
        
        Flight::json($response);
    }

    // Obtener documentos por persona y tipo de documento
    public static function getByPersonaTipoDoc($idPersona, $idTipoDocumento)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                dp.id,
                dp.id_persona,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_persona,
                dp.id_tipo_documento,
                dp.id_contrato,
                td.codigo AS codigo_documento,
                td.nombre AS nombre_documento,
                td.requiere_vencimiento,
                td.dias_alerta_vencimiento,
                dp.nombre_archivo,
                dp.ruta_archivo,
                dp.tamanio_bytes,
                dp.fecha_subida,
                dp.fecha_vencimiento,
                CASE 
                    WHEN td.requiere_vencimiento = 1 AND dp.fecha_vencimiento IS NOT NULL THEN
                        CASE 
                            WHEN dp.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                            WHEN dp.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL td.dias_alerta_vencimiento DAY) THEN 'PROXIMO_VENCER'
                            ELSE 'VIGENTE'
                        END
                    ELSE 'NO_APLICA'
                END AS estado_vencimiento,
                DATEDIFF(dp.fecha_vencimiento, CURDATE()) AS dias_para_vencer,
                dp.observaciones,
                dp.id_usuario_subio,
                CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS nombre_usuario_subio,
                dp.activo
            FROM documentos_personas dp
            INNER JOIN personas p ON dp.id_persona = p.id
            INNER JOIN tipos_documentos td ON dp.id_tipo_documento = td.id
            LEFT JOIN usuarios u ON dp.id_usuario_subio = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            WHERE dp.id_persona = :id_persona
              AND dp.id_tipo_documento = :id_tipo_documento
              AND dp.activo = 1
              AND dp.id_tenant = :id_tenant
            ORDER BY dp.fecha_subida DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_persona', $idPersona);
        $sentence->bindParam(':id_tipo_documento', $idTipoDocumento);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Obtener documentos vencidos o próximos a vencer
    public static function getVencimientoProximo($dias = 30)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                dp.id,
                dp.id_persona,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_persona,
                dp.id_tipo_documento,
                dp.id_contrato,
                td.codigo AS codigo_documento,
                td.nombre AS nombre_documento,
                td.requiere_vencimiento,
                td.dias_alerta_vencimiento,
                dp.nombre_archivo,
                dp.ruta_archivo,
                dp.tamanio_bytes,
                dp.fecha_subida,
                dp.fecha_vencimiento,
                CASE 
                    WHEN td.requiere_vencimiento = 1 AND dp.fecha_vencimiento IS NOT NULL THEN
                        CASE 
                            WHEN dp.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                            WHEN dp.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL td.dias_alerta_vencimiento DAY) THEN 'PROXIMO_VENCER'
                            ELSE 'VIGENTE'
                        END
                    ELSE 'NO_APLICA'
                END AS estado_vencimiento,
                DATEDIFF(dp.fecha_vencimiento, CURDATE()) AS dias_para_vencer,
                dp.observaciones,
                dp.id_usuario_subio,
                CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS nombre_usuario_subio,
                dp.activo
            FROM documentos_personas dp
            INNER JOIN personas p ON dp.id_persona = p.id
            INNER JOIN tipos_documentos td ON dp.id_tipo_documento = td.id
            LEFT JOIN usuarios u ON dp.id_usuario_subio = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            WHERE td.requiere_vencimiento = 1
              AND dp.fecha_vencimiento IS NOT NULL
              AND (
                  dp.fecha_vencimiento < CURDATE() 
                  OR dp.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL :dias DAY)
              )
              AND dp.activo = 1
              AND dp.id_tenant = :id_tenant
            ORDER BY dp.fecha_vencimiento ASC
        ");
        $sentence->bindParam(':dias', $dias);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }



    // Actualizar documento (sin archivo)
    public static function update()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $fecha_vencimiento = isset(Flight::request()->data['fecha_vencimiento'])
                ? Flight::request()->data['fecha_vencimiento']
                : null;
            $observaciones = isset(Flight::request()->data['observaciones'])
                ? Flight::request()->data['observaciones']
                : null;

            $sentence = $db->prepare("
                UPDATE documentos_personas 
                SET fecha_vencimiento = :fecha_vencimiento,
                    observaciones = :observaciones
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha_vencimiento', $fecha_vencimiento);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'mensaje' => 'Documento actualizado'));
        } catch (Exception $e) {
            error_log("Error en DocumentosPersonas::update: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Eliminar documento (lógico)
    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE documentos_personas SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'mensaje' => 'Documento eliminado'));
        } catch (Exception $e) {
            error_log("Error en DocumentosPersonas::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function upload()
    {
        try {
            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_documento = isset(Flight::request()->data['id_tipo_documento'])
                ? Flight::request()->data['id_tipo_documento']
                : null;
            // Opcional: código del tipo de documento. Si viene, tiene prioridad sobre
            // el id (el código es estable entre tenants y migraciones). Se resuelve su UUID.
            $codigo_tipo_documento = isset(Flight::request()->data['codigo_tipo_documento'])
                ? trim(Flight::request()->data['codigo_tipo_documento'])
                : null;
            $id_contrato = isset(Flight::request()->data['id_contrato'])
                ? Flight::request()->data['id_contrato']
                : null;
            $fecha_vencimiento = isset(Flight::request()->data['fecha_vencimiento'])
                ? Flight::request()->data['fecha_vencimiento']
                : null;
            $observaciones = isset(Flight::request()->data['observaciones'])
                ? Flight::request()->data['observaciones']
                : null;
            $id_usuario_subio = isset(Flight::request()->data['id_usuario_subio'])
                ? Flight::request()->data['id_usuario_subio']
                : null;

            // Si se envió el código del tipo de documento, resolver su id (UUID) por
            // código dentro del tenant. Prevalece sobre el id_tipo_documento recibido.
            if ($codigo_tipo_documento !== null && $codigo_tipo_documento !== '') {
                $stmtTipo = $db->prepare("
                    SELECT id FROM tipos_documentos 
                    WHERE codigo = :codigo AND id_tenant = :id_tenant 
                    LIMIT 1
                ");
                $stmtTipo->bindParam(':codigo', $codigo_tipo_documento);
                $stmtTipo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtTipo->execute();
                $tipoDocumento = $stmtTipo->fetch();

                if (!$tipoDocumento) {
                    Flight::json(array('error' => 'Tipo de documento no encontrado para el código: ' . $codigo_tipo_documento), 400);
                    return;
                }

                $id_tipo_documento = $tipoDocumento['id'];
            } else {
                // Sin código: se valida que el id recibido exista en el tenant antes de
                // insertar, para responder 400 en vez de fallar por la llave foránea.
                if ($id_tipo_documento === null || $id_tipo_documento === '') {
                    Flight::json(array('error' => 'Debe enviar id_tipo_documento o codigo_tipo_documento'), 400);
                    return;
                }

                $stmtTipoId = $db->prepare("
                    SELECT id FROM tipos_documentos 
                    WHERE id = :id AND id_tenant = :id_tenant 
                    LIMIT 1
                ");
                $stmtTipoId->bindParam(':id', $id_tipo_documento);
                $stmtTipoId->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtTipoId->execute();

                if (!$stmtTipoId->fetch()) {
                    Flight::json(array('error' => 'Tipo de documento no encontrado para el id: ' . $id_tipo_documento), 400);
                    return;
                }
            }

            // Validar que se subió archivo
            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error'), 400);
                return;
            }

            $archivo = $_FILES['archivo'];
            $nombre_original = $archivo['name'];
            $tamanio_bytes = $archivo['size'];
            $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

            // Validar extensiones permitidas
            $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'];
            if (!in_array($extension, $extensiones_permitidas)) {
                Flight::json(array('error' => 'Extensión de archivo no permitida'), 400);
                return;
            }

            // Validar tamaño (máximo 10MB)
            if ($tamanio_bytes > 10 * 1024 * 1024) {
                Flight::json(array('error' => 'El archivo excede el tamaño máximo de 10MB'), 400);
                return;
            }

            // Crear directorio si no existe - usando tenant
            $directorio_base = UploadHelper::getUploadPath('documentos_personas') . $id_persona . '/';
            UploadHelper::ensureDirectoryExists($directorio_base);

            // Generar nombre único
            $nombre_archivo = time() . '_' . uniqid() . '.' . $extension;
            $ruta_completa = $directorio_base . $nombre_archivo;
            $ruta_relativa = UploadHelper::getRelativePath('documentos_personas', $id_persona . '/' . $nombre_archivo);

            // Mover archivo
            if (!move_uploaded_file($archivo['tmp_name'], $ruta_completa)) {
                Flight::json(array('error' => 'Error al guardar el archivo'), 500);
                return;
            }

            // Insertar en BD
            $sentence = $db->prepare("
                INSERT INTO documentos_personas 
                (id, id_tenant, id_persona, id_tipo_documento, id_contrato, nombre_archivo, ruta_archivo, tamanio_bytes, 
                 fecha_vencimiento, observaciones, id_usuario_subio)
                VALUES 
                (:id, :id_tenant, :id_persona, :id_tipo_documento, :id_contrato, :nombre_archivo, :ruta_archivo, :tamanio_bytes,
                 :fecha_vencimiento, :observaciones, :id_usuario_subio)
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_documento', $id_tipo_documento);
            $sentence->bindParam(':id_contrato', $id_contrato);
            $sentence->bindParam(':nombre_archivo', $nombre_original);
            $sentence->bindParam(':ruta_archivo', $ruta_relativa);
            $sentence->bindParam(':tamanio_bytes', $tamanio_bytes);
            $sentence->bindParam(':fecha_vencimiento', $fecha_vencimiento);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_subio', $id_usuario_subio);

            $sentence->execute();

            Flight::json(array(
                'id' => $id,
                'mensaje' => 'Documento subido exitosamente',
                'ruta_archivo' => $ruta_relativa
            ));
        } catch (Exception $e) {
            error_log("Error en DocumentosPersonas::upload: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Emite un token efímero (5 min) para descargar un documento. La sesion ya
    // fue validada por el hook central; aqui solo se verifica que el documento
    // exista y pertenezca al tenant antes de firmar el token.
    public static function generarTokenDescarga($id)
    {
        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT id
                FROM documentos_personas
                WHERE id = :id AND activo = 1
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $documento = $sentence->fetch();

            if (!$documento) {
                Flight::json(array('error' => 'Documento no encontrado'), 404);
                return;
            }

            $token = JWTService::generarTokenDescarga($id, TenantContext::codigo());
            Flight::json(array('token' => $token));
        } catch (Exception $e) {
            error_log("Error en DocumentosPersonas::generarTokenDescarga: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function download($id)
    {
        try {
            // Esta ruta se salta la autenticacion de sesion del hook central y
            // se valida a si misma, aceptando dos formas:
            //  - ?token= : token efímero de descarga (para <img src="">).
            //  - sin ?token= : token de sesion por header (descarga blob).
            if (isset($_GET['token'])) {
                JWTService::requerirTokenDescarga($id, TenantContext::codigo());
            } else {
                JWTService::requerirTenant(TenantContext::codigo());
            }

            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT nombre_archivo, ruta_archivo 
                FROM documentos_personas 
                WHERE id = :id AND activo = 1
                AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $documento = $sentence->fetch();

            if (!$documento) {
                Flight::json(array('error' => 'Documento no encontrado'), 404);
                return;
            }

            $ruta_completa = UploadHelper::getFullPath($documento['ruta_archivo']);

            if (!file_exists($ruta_completa)) {
                Flight::json(array('error' => 'Archivo no encontrado en el servidor'), 404);
                return;
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $documento['nombre_archivo'] . '"');
            header('Content-Length: ' . filesize($ruta_completa));
            readfile($ruta_completa);
            exit;
        } catch (Exception $e) {
            error_log("Error en DocumentosPersonas::download: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}