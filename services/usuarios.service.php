<?php
class Usuarios
{
    private static function getDbMaster()
    {
        static $db = null;
        if ($db === null) {
            require_once __DIR__ . '/../config/master.env.php';
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            $db = new PDO(DB_MASTER_DSN, DB_MASTER_USERNAME, DB_MASTER_PASSWORD, $options);
        }
        return $db;
    }

    private static function getTenantActual()
    {
        if (isset($_SERVER['HTTP_X_TENANT'])) {
            return $_SERVER['HTTP_X_TENANT'];
        }
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'x-tenant') {
                    return $value;
                }
            }
        }
        return null;
    }

    private static function insertarEnMaster($usuario)
    {
        try {
            $tenant = self::getTenantActual();
            if (!$tenant) {
                error_log("⚠️ No se pudo obtener tenant para insertar en master");
                return false;
            }

            $dbMaster = self::getDbMaster();
            
            $stmtTenant = $dbMaster->prepare("SELECT id FROM tenants WHERE codigo = :codigo AND activo = 1");
            $stmtTenant->bindParam(':codigo', $tenant);
            $stmtTenant->execute();
            $tenantData = $stmtTenant->fetch();

            if (!$tenantData) {
                error_log("⚠️ Tenant no encontrado en master: {$tenant}");
                return false;
            }

            $stmtCheck = $dbMaster->prepare("SELECT id FROM usuarios_tenants WHERE usuario = :usuario AND id_tenant = :id_tenant");
            $stmtCheck->bindParam(':usuario', $usuario);
            $stmtCheck->bindParam(':id_tenant', $tenantData['id']);
            $stmtCheck->execute();
            
            if ($stmtCheck->fetch()) {
                return true;
            }

            $stmtInsert = $dbMaster->prepare("INSERT INTO usuarios_tenants (usuario, id_tenant) VALUES (:usuario, :id_tenant)");
            $stmtInsert->bindParam(':usuario', $usuario);
            $stmtInsert->bindParam(':id_tenant', $tenantData['id']);
            $stmtInsert->execute();

            error_log("✅ Usuario insertado en master: {$usuario} -> tenant: {$tenant}");
            return true;

        } catch (Exception $e) {
            error_log("❌ Error insertando en master: " . $e->getMessage());
            return false;
        }
    }

    private static function eliminarDeMaster($usuario)
    {
        try {
            $tenant = self::getTenantActual();
            if (!$tenant) {
                return false;
            }

            $dbMaster = self::getDbMaster();
            
            $stmtTenant = $dbMaster->prepare("SELECT id FROM tenants WHERE codigo = :codigo");
            $stmtTenant->bindParam(':codigo', $tenant);
            $stmtTenant->execute();
            $tenantData = $stmtTenant->fetch();

            if (!$tenantData) {
                return false;
            }

            $stmtDelete = $dbMaster->prepare("DELETE FROM usuarios_tenants WHERE usuario = :usuario AND id_tenant = :id_tenant");
            $stmtDelete->bindParam(':usuario', $usuario);
            $stmtDelete->bindParam(':id_tenant', $tenantData['id']);
            $stmtDelete->execute();

            error_log("✅ Usuario eliminado de master: {$usuario} -> tenant: {$tenant}");
            return true;

        } catch (Exception $e) {
            error_log("❌ Error eliminando de master: " . $e->getMessage());
            return false;
        }
    }

    private static function obtenerPermisosUsuario($idUsuario)
    {
        try {
            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT DISTINCT pxr.codigo_permiso
                FROM permisos_x_rol pxr
                INNER JOIN roles_x_usuario rxu ON pxr.id_rol = rxu.id_rol
                WHERE rxu.id_usuario = :id_usuario AND rxu.id_tenant = :id_tenant
                ORDER BY pxr.codigo_permiso
            ");
            $stmt->bindParam(':id_usuario', $idUsuario);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $permisos;
        } catch (Exception $e) {
            error_log("❌ Error obteniendo permisos del usuario {$idUsuario}: " . $e->getMessage());
            return [];
        }
    }

    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT u.id, u.id_persona, u.usuario, u.correo_electronico, p.primer_nombre, p.segundo_nombre,
                    p.primer_apellido, p.segundo_apellido, p.id_tipo_identificacion, ti.nombre tipo_identificacion,
                    p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion, 
                    u.activo, u.acceso_institucional, u.acceso_chat_wa, u.acceso_portal_padres, u.super_admin,
                    (SELECT GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') FROM roles_x_usuario rxu INNER JOIN roles r ON r.id = rxu.id_rol WHERE rxu.id_usuario = u.id) roles
                    FROM usuarios u 
                    INNER JOIN personas p ON u.id_persona = p.id
                    INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                    INNER JOIN generos g ON p.id_genero = g.id
                    WHERE u.id_tenant = :id_tenant");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response, 200);
        } catch (Exception $e) {
            Flight::json($e->getMessage(), 500);
        }
    }

    public static function getUsuarioByUsuarioAndClave()
    {
        try {
            $db = Flight::db();
            $usuario = Flight::request()->data['usuario'];
            $clave = Flight::request()->data['clave'];
            // Desde que app se autentica. Valor desconocido o ausente => institucional.
            $portal = JWTService::normalizarPortal(Flight::request()->data['portal'] ?? null);

            $checkUser = $db->prepare("SELECT id, usuario, clave, activo FROM usuarios WHERE usuario = :usuario AND id_tenant = :id_tenant");
            $checkUser->bindParam(':usuario', $usuario);
            $checkUser->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkUser->execute();
            $userExists = $checkUser->fetch();

            if (!$userExists) {
                Flight::json([]);
                return;
            }

            if ($userExists['clave'] !== $clave) {
                Flight::json([]);
                return;
            }

            if ($userExists['activo'] != 1) {
                Flight::json([['error' => true, 'code' => 'USER_INACTIVE', 'message' => 'Usuario inactivo']]);
                return;
            }

            $sentence = $db->prepare("
                SELECT 
                    u.id, 
                    u.id_persona, 
                    u.usuario, 
                    u.correo_electronico, 
                    p.primer_nombre, 
                    p.segundo_nombre,
                    p.primer_apellido, 
                    p.segundo_apellido, 
                    p.id_tipo_identificacion, 
                    ti.nombre tipo_identificacion,
                    p.numero_identificacion, 
                    p.fecha_nacimiento, 
                    p.id_genero, 
                    g.nombre nombre_genero, 
                    p.direccion, 
                    u.activo, 
                    u.acceso_institucional,
                    u.acceso_chat_wa,
                    u.acceso_portal_padres,
                    u.super_admin,
                    c.sobrenombre,
                    c.id id_colaborador,
                    c.valida_ingreso_jornada,
                    c.valida_ingreso_descanso
                FROM usuarios u 
                INNER JOIN personas p ON u.id_persona = p.id
                LEFT JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                LEFT JOIN generos g ON p.id_genero = g.id
                LEFT JOIN colaboradores c ON p.id = c.id_persona
                WHERE u.usuario = :usuario
                AND u.clave = :clave
                AND u.activo = 1
                AND u.id_tenant = :id_tenant
            ");

            $sentence->bindParam(':usuario', $usuario);
            $sentence->bindParam(':clave', $clave);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (!empty($response) && isset($response[0])) {
                if ($response[0]['super_admin'] == 1) {
                    $permisos = ['*'];
                } else {
                    $permisos = self::obtenerPermisosUsuario($response[0]['id']);
                }

                // hd_ok: pasaporte firmado. True si el portal no exige politica
                // o si el usuario ya acepto la version vigente. El middleware lo
                // lee sin tocar la BD. hd_v queda solo como dato informativo.
                $hdOk = AutorizacionesHabeasData::estaAutorizado($response[0]['id'], $portal);
                $hdVersion = AutorizacionesHabeasData::versionAceptada($response[0]['id'], $portal);

                $token = JWTService::generarToken(
                    $response[0],
                    $permisos,
                    TenantContext::codigo(),
                    ['portal' => $portal, 'hd_ok' => $hdOk, 'hd_v' => $hdVersion]
                );
                $response[0]['token'] = $token;
                $response[0]['permisos'] = $permisos;
            }

            Flight::json($response);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function cambiarClave()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $id = $data->id ?? null;
            $claveActual = $data->claveActual ?? null;
            $claveNueva = $data->claveNueva ?? null;

            if (!$id || !$claveActual || !$claveNueva) {
                Flight::json([
                    'success' => false,
                    'message' => 'Faltan datos requeridos'
                ], 400);
                return;
            }

            $verificarClave = $db->prepare("SELECT id, usuario, clave FROM usuarios WHERE id = :id AND id_tenant = :id_tenant");
            $verificarClave->bindParam(':id', $id);
            $verificarClave->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificarClave->execute();
            $usuarioActual = $verificarClave->fetch(PDO::FETCH_ASSOC);

            if (!$usuarioActual) {
                Flight::json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
                return;
            }

            if ((string)$usuarioActual['clave'] !== (string)$claveActual) {
                Flight::json([
                    'success' => false,
                    'message' => 'La contraseña actual es incorrecta'
                ], 401);
                return;
            }

            $actualizarClave = $db->prepare("UPDATE usuarios SET clave = :clave WHERE id = :id AND id_tenant = :id_tenant");
            $actualizarClave->bindParam(':clave', $claveNueva);
            $actualizarClave->bindParam(':id', $id);
            $actualizarClave->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $resultado = $actualizarClave->execute();

            if ($resultado) {
                Flight::json([
                    'success' => true,
                    'message' => 'Contraseña actualizada correctamente'
                ]);
            } else {
                Flight::json([
                    'success' => false,
                    'message' => 'Error al actualizar la contraseña'
                ], 500);
            }
        } catch (Exception $e) {
            Flight::json([
                'success' => false,
                'message' => 'Error interno del servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public static function getByPersona($idPersona)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("SELECT u.id, u.id_persona, u.usuario, u.correo_electronico, u.activo, 
                                u.acceso_institucional, u.acceso_chat_wa, u.acceso_portal_padres
                                FROM usuarios u 
                                WHERE u.id_persona = :id_persona AND u.id_tenant = :id_tenant");
            $sentence->bindParam(':id_persona', $idPersona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function new()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $id_persona = Flight::request()->data['id_persona'];
            $correo_electronico = Flight::request()->data['correo_electronico'];
            $clave = Flight::request()->data['clave'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $acceso_institucional = isset(Flight::request()->data['acceso_institucional']) ? Flight::request()->data['acceso_institucional'] : 0;
            $acceso_chat_wa = isset(Flight::request()->data['acceso_chat_wa']) ? Flight::request()->data['acceso_chat_wa'] : 1;
            $acceso_portal_padres = isset(Flight::request()->data['acceso_portal_padres']) ? Flight::request()->data['acceso_portal_padres'] : 0;

            // super_admin solo lo puede otorgar otro super admin; para el resto se fuerza a 0
            $usuarioAutenticado = JWTService::requerirAutenticacion();
            $super_admin = 0;
            if (($usuarioAutenticado->super_admin ?? 0) == 1) {
                $super_admin = isset(Flight::request()->data['super_admin']) && Flight::request()->data['super_admin'] ? 1 : 0;
            }

            $checkUsuario = $db->prepare("SELECT id FROM usuarios WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $checkUsuario->bindParam(':id_persona', $id_persona);
            $checkUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkUsuario->execute();
            if ($checkUsuario->fetch()) {
                $db->rollBack();
                Flight::json(['error' => 'Ya existe un usuario para esta persona'], 400);
                return;
            }

            $getPersona = $db->prepare("SELECT numero_identificacion FROM personas WHERE id = :id_persona AND id_tenant = :id_tenant");
            $getPersona->bindParam(':id_persona', $id_persona);
            $getPersona->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getPersona->execute();
            $persona = $getPersona->fetch();

            if (!$persona) {
                $db->rollBack();
                Flight::json(['error' => 'No se encontró la persona'], 404);
                return;
            }

            $usuario = $persona['numero_identificacion'];

            $sentence = $db->prepare("INSERT INTO usuarios(id, id_tenant, id_persona, usuario, clave, correo_electronico, activo, acceso_institucional, acceso_chat_wa, acceso_portal_padres, super_admin) 
                                     VALUES (:id, :id_tenant, :id_persona, :usuario, :clave, :correo_electronico, :activo, :acceso_institucional, :acceso_chat_wa, :acceso_portal_padres, :super_admin)");
            $idUsr = Uuid::generar();
            $sentence->bindValue(':id', $idUsr);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':usuario', $usuario);
            $sentence->bindParam(':clave', $clave);
            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':acceso_institucional', $acceso_institucional);
            $sentence->bindParam(':acceso_chat_wa', $acceso_chat_wa);
            $sentence->bindParam(':acceso_portal_padres', $acceso_portal_padres);
            $sentence->bindValue(':super_admin', $super_admin, PDO::PARAM_INT);
            $sentence->execute();

            $id_usuario = $idUsr;

            $db->commit();

            self::insertarEnMaster($usuario);

            Flight::json(['id' => $id_usuario, 'usuario' => $usuario]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function replace()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $correo_electronico = Flight::request()->data['correo_electronico'];
            $activo = isset(Flight::request()->data['activo']) ? Flight::request()->data['activo'] : 1;
            $acceso_institucional = isset(Flight::request()->data['acceso_institucional']) ? Flight::request()->data['acceso_institucional'] : 0;
            $acceso_chat_wa = isset(Flight::request()->data['acceso_chat_wa']) ? Flight::request()->data['acceso_chat_wa'] : 1;
            $acceso_portal_padres = isset(Flight::request()->data['acceso_portal_padres']) ? Flight::request()->data['acceso_portal_padres'] : 0;
            $clave = isset(Flight::request()->data['clave']) ? Flight::request()->data['clave'] : null;

            // super_admin solo lo modifica otro super admin; si no, no se toca
            $usuarioAutenticado = JWTService::requerirAutenticacion();
            $puedeSuperAdmin = ($usuarioAutenticado->super_admin ?? 0) == 1 && isset(Flight::request()->data['super_admin']);
            $super_admin = $puedeSuperAdmin ? (Flight::request()->data['super_admin'] ? 1 : 0) : null;
            $sqlSuper = $puedeSuperAdmin ? ", super_admin = :super_admin" : "";

            $checkUsuario = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND id_tenant = :id_tenant");
            $checkUsuario->bindParam(':id', $id);
            $checkUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkUsuario->execute();
            if (!$checkUsuario->fetch()) {
                $db->rollBack();
                Flight::json(['error' => 'Usuario no encontrado'], 404);
                return;
            }

            if ($clave !== null && $clave !== '') {
                $sentence = $db->prepare("UPDATE usuarios SET correo_electronico = :correo_electronico, clave = :clave, activo = :activo, acceso_institucional = :acceso_institucional, acceso_chat_wa = :acceso_chat_wa, acceso_portal_padres = :acceso_portal_padres" . $sqlSuper . " WHERE id = :id AND id_tenant = :id_tenant");
                $sentence->bindParam(':clave', $clave);
            } else {
                $sentence = $db->prepare("UPDATE usuarios SET correo_electronico = :correo_electronico, activo = :activo, acceso_institucional = :acceso_institucional, acceso_chat_wa = :acceso_chat_wa, acceso_portal_padres = :acceso_portal_padres" . $sqlSuper . " WHERE id = :id AND id_tenant = :id_tenant");
            }

            $sentence->bindParam(':correo_electronico', $correo_electronico);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindParam(':acceso_institucional', $acceso_institucional);
            $sentence->bindParam(':acceso_chat_wa', $acceso_chat_wa);
            $sentence->bindParam(':acceso_portal_padres', $acceso_portal_padres);
            if ($puedeSuperAdmin) {
                $sentence->bindValue(':super_admin', $super_admin, PDO::PARAM_INT);
            }
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();
            Flight::json(['id' => $id, 'message' => 'Usuario actualizado correctamente']);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];

            $getUsuario = $db->prepare("SELECT usuario FROM usuarios WHERE id = :id AND id_tenant = :id_tenant");
            $getUsuario->bindParam(':id', $id);
            $getUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $getUsuario->execute();
            $usuarioData = $getUsuario->fetch();

            if (!$usuarioData) {
                $db->rollBack();
                Flight::json(['error' => 'Usuario no encontrado'], 404);
                return;
            }

            $sentence = $db->prepare("DELETE FROM usuarios WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                $db->rollBack();
                Flight::json(['error' => 'No se pudo eliminar el usuario'], 500);
                return;
            }

            $db->commit();

            self::eliminarDeMaster($usuarioData['usuario']);

            Flight::json(['id' => $id, 'message' => 'Usuario eliminado correctamente']);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Restablecimiento de clave por un administrador (no requiere la clave actual).
     * Payload: { id, claveNueva }
     */
    public static function restablecerClave()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            $id = $data->id ?? null;
            $claveNueva = $data->claveNueva ?? null;

            if (!$id || !$claveNueva) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }

            $verificar = $db->prepare("SELECT id FROM usuarios WHERE id = :id AND id_tenant = :id_tenant");
            $verificar->bindParam(':id', $id);
            $verificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verificar->execute();
            if (!$verificar->fetch()) {
                Flight::json(['error' => 'Usuario no encontrado'], 404);
                return;
            }

            $actualizar = $db->prepare("UPDATE usuarios SET clave = :clave WHERE id = :id AND id_tenant = :id_tenant");
            $actualizar->bindParam(':clave', $claveNueva);
            $actualizar->bindParam(':id', $id);
            $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actualizar->execute();

            Flight::json(['id' => $id, 'mensaje' => 'Clave restablecida']);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

}
