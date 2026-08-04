<?php
class AutorizacionesHabeasData
{
    /**
     * Clave de configuracion_global que define la version vigente por portal.
     */
    private static function claveVersion($portal)
    {
        return $portal === JWTService::PORTAL_PADRES
            ? 'habeas_data_version_actual_acudientes'
            : 'habeas_data_version_actual_colaboradores';
    }

    /**
     * Clave de la plantilla en la tabla plantillas, por portal.
     */
    private static function clavePlantilla($portal)
    {
        return $portal === JWTService::PORTAL_PADRES
            ? 'politica_habeas_data_acudientes'
            : 'politica_habeas_data_colaboradores';
    }

    /**
     * Clave del interruptor de activacion por portal.
     */
    private static function claveActivo($portal)
    {
        return $portal === JWTService::PORTAL_PADRES
            ? 'habeas_data_activo_acudientes'
            : 'habeas_data_activo_colaboradores';
    }

    /**
     * Interruptor explicito de activacion por portal.
     *
     * Solo apaga: si la clave existe y vale 'false' (o '0'), el habeas data
     * queda desactivado para ese portal aunque haya plantilla publicada. Si la
     * clave no existe, o vale cualquier otra cosa, se considera activo y manda
     * la logica normal (version + plantilla). Asi los tenants ya configurados
     * no cambian de comportamiento hasta que se cree el flag en 'false'.
     */
    private static function estaActivo($portal)
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT valor_texto FROM configuracion_global
                               WHERE clave = :clave AND id_tenant = :id_tenant LIMIT 1");
        $stmt->bindValue(':clave', self::claveActivo($portal));
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row) {
            return true; // ausencia de flag = activo
        }

        $valor = strtolower(trim((string) $row['valor_texto']));
        return !($valor === 'false' || $valor === '0');
    }

    /**
     * Version vigente de la politica para el portal dado.
     *
     * Retorna null si la clave no esta configurada. Sin fallback: la ausencia
     * de configuracion significa "esta institucion no exige habeas data en
     * este portal", y esa debe ser una decision explicita, no un accidente.
     *
     * @return string|null
     */
    public static function versionVigente($portal)
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT valor_texto
                                FROM configuracion_global
                               WHERE clave = :clave
                                 AND id_tenant = :id_tenant
                               LIMIT 1");
        $stmt->bindValue(':clave', self::claveVersion($portal));
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        if (!$row || $row['valor_texto'] === null || $row['valor_texto'] === '') {
            return null;
        }

        return (string) $row['valor_texto'];
    }

    /**
     * Contenido crudo (JSON) de la plantilla que corresponde a $version,
     * o null si no existe.
     *
     * @return string|null
     */
    private static function plantillaDeVersion($portal, $version)
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT contenido
                                FROM plantillas
                               WHERE clave = :clave
                                 AND id_tenant = :id_tenant");
        $stmt->bindValue(':clave', self::clavePlantilla($portal));
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll() as $p) {
            $contenido = json_decode($p['contenido'], true);
            if ($contenido && isset($contenido['version'])
                && strval($contenido['version']) === strval($version)) {
                return $p['contenido'];
            }
        }

        return null;
    }

    /**
     * Version de la politica aceptada por el usuario en el portal dado.
     * Fuente de verdad para emitir el claim hd_v del JWT.
     *
     * @return string|null
     */
    public static function versionAceptada($id_usuario, $portal)
    {
        $db = Flight::db();
        $stmt = $db->prepare("SELECT version_politica
                                FROM autorizaciones_habeas_data
                               WHERE id_usuario = :id_usuario
                                 AND portal = :portal
                                 AND id_tenant = :id_tenant
                               ORDER BY fecha_aceptacion DESC
                               LIMIT 1");
        $stmt->bindValue(':id_usuario', $id_usuario);
        $stmt->bindValue(':portal', $portal);
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row ? (string) $row['version_politica'] : null;
    }

    /**
     * True si el portal exige habeas data: hay version configurada Y existe
     * plantilla publicada para esa version. Si falta cualquiera de las dos,
     * no se exige (y por tanto no se bloquea a nadie).
     */
    public static function seExige($portal)
    {
        // Interruptor explicito: si esta apagado, no se exige aunque haya plantilla.
        if (!self::estaActivo($portal)) {
            return false;
        }

        $version = self::versionVigente($portal);
        if ($version === null) {
            return false;
        }

        return self::plantillaDeVersion($portal, $version) !== null;
    }

    /**
     * True si el usuario tiene autorizacion vigente para el portal.
     * Si el portal no exige habeas data, siempre true.
     */
    public static function estaAutorizado($id_usuario, $portal)
    {
        if (!self::seExige($portal)) {
            return true;
        }

        return self::versionAceptada($id_usuario, $portal) === self::versionVigente($portal);
    }

    /**
     * GET /autorizaciones-habeas-data/verificar
     *
     * El backend decide: el cliente solo lee requiere_autorizacion.
     * El id_usuario sale del token, nunca de la URL.
     */
    public static function verificar()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            $portal = JWTService::normalizarPortal($userData->portal ?? null);
            $version = self::versionVigente($portal);

            $exige = self::seExige($portal);
            $autorizado = !$exige || self::versionAceptada($userData->id, $portal) === $version;

            Flight::json(array(
                'requiere_autorizacion' => $exige && !$autorizado,
                'autorizado' => $autorizado,
                'version_actual' => $version
            ));
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::verificar: " . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar autorización'), 500);
        }
    }

    /**
     * GET /autorizaciones-habeas-data/plantilla
     *
     * Solo se llama cuando verificar() dijo requiere_autorizacion = true,
     * asi que un 404 aqui es una inconsistencia real, no un caso normal.
     */
    public static function getPlantilla()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $portal = JWTService::normalizarPortal($userData->portal ?? null);

            $version = self::versionVigente($portal);
            $plantilla = $version === null ? null : self::plantillaDeVersion($portal, $version);

            if ($plantilla === null) {
                Flight::json(array(
                    'error' => 'No hay política publicada para este portal',
                    'code' => 'POLICY_NOT_PUBLISHED'
                ), 404);
                return;
            }

            $stmtConfig = $db->prepare("SELECT clave, valor_texto
                                          FROM configuracion_global
                                         WHERE clave IN (
                                             'institucion_nombre', 'institucion_nit', 'institucion_direccion',
                                             'institucion_telefono', 'institucion_email', 'institucion_web'
                                         )
                                           AND id_tenant = :id_tenant");
            $stmtConfig->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtConfig->execute();

            foreach ($stmtConfig->fetchAll() as $config) {
                $plantilla = str_replace(
                    '{{' . $config['clave'] . '}}',
                    $config['valor_texto'] ?? '',
                    $plantilla
                );
            }

            Flight::json(array(
                'contenido' => json_decode($plantilla, true),
                'version' => $version
            ));
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::getPlantilla: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener plantilla'), 500);
        }
    }

    /**
     * POST /autorizaciones-habeas-data
     *
     * id_usuario, id_persona, portal y version salen del token y del servidor.
     * El cuerpo del request no aporta nada: nadie puede registrar la
     * autorizacion de otro usuario ni declarar una version distinta.
     *
     * Responde con un token nuevo que ya trae el claim hd_v, para que el
     * cliente no tenga que volver a autenticarse.
     */
    public static function new()
    {
        try {
            $db = Flight::db();
            $userData = JWTService::requerirAutenticacion();
            $portal = JWTService::normalizarPortal($userData->portal ?? null);

            $version = self::versionVigente($portal);
            if ($version === null || self::plantillaDeVersion($portal, $version) === null) {
                Flight::json(array(
                    'error' => 'No hay política publicada para este portal',
                    'code' => 'POLICY_NOT_PUBLISHED'
                ), 409);
                return;
            }

            // Idempotente: si ya acepto esta version, no duplicar el registro.
            if (self::versionAceptada($userData->id, $portal) !== $version) {
                $sentence = $db->prepare("INSERT INTO autorizaciones_habeas_data
                    (id, id_tenant, id_usuario, id_persona, portal, version_politica, ip_address, user_agent)
                    VALUES (:id, :id_tenant, :id_usuario, :id_persona, :portal, :version_politica, :ip_address, :user_agent)");

                $sentence->bindValue(':id', Uuid::generar());
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindValue(':id_usuario', $userData->id);
                $sentence->bindValue(':id_persona', $userData->id_persona);
                $sentence->bindValue(':portal', $portal);
                $sentence->bindValue(':version_politica', $version);
                $sentence->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'] ?? null);
                $sentence->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
                $sentence->execute();
            }

            // Token nuevo con el pasaporte hd_ok, mismos datos y permisos.
            $token = JWTService::generarToken(
                array(
                    'id' => $userData->id,
                    'id_persona' => $userData->id_persona,
                    'usuario' => $userData->usuario,
                    'primer_nombre' => $userData->primer_nombre ?? '',
                    'primer_apellido' => $userData->primer_apellido ?? '',
                    'super_admin' => $userData->super_admin ?? 0
                ),
                isset($userData->permisos) ? (array) $userData->permisos : [],
                TenantContext::codigo(),
                array('portal' => $portal, 'hd_ok' => true, 'hd_v' => $version)
            );

            Flight::json(array(
                'token' => $token,
                'version_politica' => $version,
                'mensaje' => 'Autorización registrada correctamente'
            ));
        } catch (Exception $e) {
            error_log("Error en AutorizacionesHabeasData::new: " . $e->getMessage());
            Flight::json(array('error' => 'Error al registrar autorización'), 500);
        }
    }
}