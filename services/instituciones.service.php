<?php
class Instituciones
{
    // Devuelve la institución del tenant (con datos de su persona) o un objeto
    // vacío si aún no existe. Al ser única por tenant, no hay listado.
    public static function getByTenant()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    i.id,
                    i.id_persona,
                    i.activo,
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
                FROM instituciones i
                INNER JOIN personas p ON p.id = i.id_persona
                WHERE i.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetch();

            // Se devuelve objeto vacío (no error) cuando aún no está creada: el
            // front muestra el formulario en blanco para crearla la primera vez.
            Flight::json($response ? $response : array());
        } catch (Exception $e) {
            error_log("Error en Instituciones::getByTenant: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener la institución'), 500);
        }
    }

    // Vincula la persona (ya creada por el front) como la institución del tenant.
    // Idempotente: si ya existe, no crea una segunda.
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'administracion.datos_maestros');

            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'] ?? null;
            if (!$id_persona) {
                Flight::json(array('error' => 'Falta id_persona'), 400);
                return;
            }

            $verif = $db->prepare("
                SELECT id FROM instituciones WHERE id_tenant = :id_tenant LIMIT 1
            ");
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();
            $existente = $verif->fetch();

            if ($existente) {
                // Ya existe la institución del tenant: se devuelve la actual.
                Flight::json(array('id' => $existente['id'], 'mensaje' => 'La institución ya estaba creada'));
                return;
            }

            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO instituciones (id, id_tenant, id_persona, activo)
                VALUES (:id, :id_tenant, :id_persona, 1)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en Instituciones::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}