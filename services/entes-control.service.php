<?php
class EntesControl
{
    // Listado de entes de control del tenant (con datos de la persona asociada).
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    ec.id,
                    ec.id_persona,
                    ec.funciones,
                    ec.activo,
                    COALESCE(
                        NULLIF(TRIM(p.razon_social), ''),
                        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                    ) AS nombre_ente,
                    p.numero_identificacion,
                    p.id_tipo_identificacion,
                    p.correo_electronico,
                    p.telefono
                FROM entes_control ec
                INNER JOIN personas p ON p.id = ec.id_persona
                WHERE ec.id_tenant = :id_tenant
                  AND ec.activo = 1
                ORDER BY nombre_ente ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en EntesControl::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los entes de control'), 500);
        }
    }

    // Detalle de un ente de control por su id.
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    ec.id,
                    ec.id_persona,
                    ec.funciones,
                    ec.activo,
                    p.razon_social,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    p.id_tipo_identificacion,
                    p.numero_identificacion,
                    p.direccion,
                    p.id_ciudad,
                    p.correo_electronico,
                    p.telefono
                FROM entes_control ec
                INNER JOIN personas p ON p.id = ec.id_persona
                WHERE ec.id = :id
                  AND ec.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (empty($response)) {
                Flight::json(array('error' => 'No se encontró el ente de control'), 404);
                return;
            }
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en EntesControl::getById: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener el ente de control'), 500);
        }
    }

    // Verifica si una persona ya está registrada como ente de control (evita duplicados).
    public static function verificarDuplicados($idPersona)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT id FROM entes_control
                WHERE id_persona = :id_persona AND id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindParam(':id_persona', $idPersona);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $existe = $sentence->fetch();
            Flight::json(array('existe' => $existe ? true : false));
        } catch (Exception $e) {
            error_log("Error en EntesControl::verificarDuplicados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Crea el ente de control sobre una persona ya existente (creada por el front).
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'] ?? null;
            $funciones = Flight::request()->data['funciones'] ?? null;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id_persona) {
                Flight::json(array('error' => 'Falta id_persona'), 400);
                return;
            }

            // Evitar registrar dos veces la misma persona como ente en el tenant.
            $verif = $db->prepare("
                SELECT id FROM entes_control
                WHERE id_persona = :id_persona AND id_tenant = :id_tenant LIMIT 1
            ");
            $verif->bindParam(':id_persona', $id_persona);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();
            if ($verif->fetch()) {
                Flight::json(array('error' => 'Esta persona ya está registrada como ente de control'), 409);
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO entes_control (id, id_tenant, id_persona, funciones, activo)
                VALUES (:id, :id_tenant, :id_persona, :funciones, :activo)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':funciones', $funciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en EntesControl::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Actualiza los datos propios del ente (funciones, activo).
    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $id = Flight::request()->data['id'] ?? null;
            $funciones = Flight::request()->data['funciones'] ?? null;
            $activo = Flight::request()->data['activo'] ?? 1;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del ente de control'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE entes_control
                SET funciones = :funciones,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':funciones', $funciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en EntesControl::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Baja lógica del ente (no borra la persona ni sus documentos).
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id del ente de control'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE entes_control SET activo = 0
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el ente de control'), 404);
                return;
            }

            Flight::json(array('id' => $id, 'mensaje' => 'Ente de control eliminado'));
        } catch (Exception $e) {
            error_log("Error en EntesControl::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}