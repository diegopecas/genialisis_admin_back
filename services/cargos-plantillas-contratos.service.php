<?php

class CargosPlantillasContratos
{
    /**
     * Listar todos los mapeos con nombres de cargo / tipo / plantilla.
     * GET /cargos-plantillas-contratos
     */
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cpc.*,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       tc.codigo AS tipo_contrato_codigo,
                       pl.titulo AS plantilla_titulo,
                       pl.clave AS plantilla_clave
                FROM cargos_plantillas_contratos cpc
                INNER JOIN cargos car ON cpc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cpc.id_tipo_contrato = tc.id
                INNER JOIN plantillas pl ON cpc.id_plantilla = pl.id
                WHERE cpc.id_tenant = :id_tenant
                ORDER BY car.nombre, tc.nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::getAll: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener un mapeo por ID.
     * GET /cargos-plantillas-contratos/:id
     */
    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cpc.*,
                       car.nombre AS cargo_nombre,
                       tc.nombre AS tipo_contrato_nombre,
                       pl.titulo AS plantilla_titulo
                FROM cargos_plantillas_contratos cpc
                INNER JOIN cargos car ON cpc.id_cargo = car.id
                INNER JOIN tipos_contrato tc ON cpc.id_tipo_contrato = tc.id
                INNER JOIN plantillas pl ON cpc.id_plantilla = pl.id
                WHERE cpc.id = :id
                  AND cpc.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                Flight::json($resultado);
            } else {
                Flight::json(['error' => 'Mapeo no encontrado'], 404);
            }
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::getById: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolver la plantilla a partir de (cargo + tipo de contrato).
     * GET /cargos-plantillas-contratos/resolver/:idCargo/:idTipoContrato
     */
    public static function resolver($idCargo, $idTipoContrato)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cpc.id, cpc.id_plantilla,
                       pl.clave AS plantilla_clave,
                       pl.titulo AS plantilla_titulo
                FROM cargos_plantillas_contratos cpc
                INNER JOIN plantillas pl ON cpc.id_plantilla = pl.id
                WHERE cpc.id_cargo = :id_cargo
                  AND cpc.id_tipo_contrato = :id_tipo_contrato
                  AND cpc.activo = 1
                  AND cpc.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                Flight::json($resultado);
            } else {
                Flight::json(['error' => 'No existe plantilla para ese cargo y tipo de contrato'], 404);
            }
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::resolver: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear un mapeo.
     * POST /cargos-plantillas-contratos
     * Devuelve {id}.
     */
    public static function new()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $data = Flight::request()->data;
            $idCargo        = $data['id_cargo'] ?? null;
            $idTipoContrato = $data['id_tipo_contrato'] ?? null;
            $idPlantilla    = $data['id_plantilla'] ?? null;
            $activo         = isset($data['activo']) ? (int) $data['activo'] : 1;

            if (!$idCargo || !$idTipoContrato || !$idPlantilla) {
                Flight::json(['error' => 'Faltan datos obligatorios (id_cargo, id_tipo_contrato, id_plantilla)'], 400);
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO cargos_plantillas_contratos (id, id_tenant, id_cargo, id_tipo_contrato, id_plantilla, activo)
                VALUES (:id, :id_tenant, :id_cargo, :id_tipo_contrato, :id_plantilla, :activo)
            ");
            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentence->bindParam(':id_plantilla', $idPlantilla);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar un mapeo.
     * PUT /cargos-plantillas-contratos
     */
    public static function replace()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $data = Flight::request()->data;
            $id             = $data['id'] ?? null;
            $idCargo        = $data['id_cargo'] ?? null;
            $idTipoContrato = $data['id_tipo_contrato'] ?? null;
            $idPlantilla    = $data['id_plantilla'] ?? null;
            $activo         = isset($data['activo']) ? (int) $data['activo'] : 1;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del mapeo'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE cargos_plantillas_contratos SET
                    id_cargo = :id_cargo,
                    id_tipo_contrato = :id_tipo_contrato,
                    id_plantilla = :id_plantilla,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':id_tipo_contrato', $idTipoContrato);
            $sentence->bindParam(':id_plantilla', $idPlantilla);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Mapeo actualizado correctamente']);
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar un mapeo.
     * DELETE /cargos-plantillas-contratos
     */
    public static function delete()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'colaboradores.contratos.administrar');

            $data = Flight::request()->data;
            $id = $data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id del mapeo'], 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM cargos_plantillas_contratos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(['id' => $id, 'message' => 'Mapeo eliminado correctamente']);
        } catch (Exception $e) {
            error_log("Error en CargosPlantillasContratos::delete: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}
