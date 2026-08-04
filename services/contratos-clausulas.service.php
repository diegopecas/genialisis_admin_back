<?php

/**
 * Servicio para gestión de cláusulas de contratos laborales.
 * CRUD sobre la tabla contratos_clausulas.
 *
 * Patrón seguido: métodos estáticos, Flight::db(), Flight::json(),
 * new() devuelve {id}.
 */

class ContratosClausulas
{
    /**
     * Listar todas las cláusulas.
     * GET /contratos-clausulas
     */
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cl.*, car.nombre AS cargo_nombre
                FROM contratos_clausulas cl
                LEFT JOIN cargos car ON cl.id_cargo = car.id
                WHERE cl.id_tenant = :id_tenant
                ORDER BY cl.orden, cl.numero, cl.subnumero
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::getAll: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtener una cláusula por ID.
     * GET /contratos-clausulas/:id
     */
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cl.*, car.nombre AS cargo_nombre
                FROM contratos_clausulas cl
                LEFT JOIN cargos car ON cl.id_cargo = car.id
                WHERE cl.id = :id
                  AND cl.id_tenant = :id_tenant
                LIMIT 1
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                Flight::json($resultado);
            } else {
                Flight::json(['error' => 'Cláusula no encontrada'], 404);
            }
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::getById: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resolver las cláusulas aplicables a un contrato según el cargo.
     * Devuelve las cláusulas globales (id_cargo NULL) más las del cargo dado,
     * activas y ordenadas. Es lo que el servicio PDF usa para armar el cuerpo.
     * GET /contratos-clausulas/resolver/:idCargo
     */
    public static function resolver($idCargo)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT cl.*
                FROM contratos_clausulas cl
                WHERE cl.activo = 1
                  AND (cl.id_cargo IS NULL OR cl.id_cargo = :id_cargo)
                  AND cl.id_tenant = :id_tenant
                ORDER BY cl.orden, cl.numero, cl.subnumero
            ");
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::resolver: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Crear una cláusula.
     * POST /contratos-clausulas
     * Devuelve {id}.
     */
    public static function new()
    {
        $db = Flight::db();
        try {

            $tipo      = Flight::request()->data['tipo'] ?? null;
            $contenido = Flight::request()->data['contenido'] ?? null;
            $numero    = Flight::request()->data['numero'] ?? null;

            if (!$tipo || !$contenido || $numero === null) {
                Flight::json(['error' => 'Faltan datos obligatorios (tipo, numero, contenido)'], 400);
                return;
            }

            $sentence = $db->prepare("
                INSERT INTO contratos_clausulas
                    (id, id_tenant, tipo, id_cargo, numero, subnumero, titulo, contenido, orden, activo)
                VALUES
                    (:id, :id_tenant, :tipo, :id_cargo, :numero, :subnumero, :titulo, :contenido, :orden, :activo)
            ");

            $idCargo   = Flight::request()->data['id_cargo'] ?? null;
            $subnumero = Flight::request()->data['subnumero'] ?? null;
            $titulo    = Flight::request()->data['titulo'] ?? null;
            $orden     = Flight::request()->data['orden'] ?? 0;
            $activo    = isset(Flight::request()->data['activo']) ? (int) Flight::request()->data['activo'] : 1;

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':tipo', $tipo);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':numero', $numero);
            $sentence->bindParam(':subnumero', $subnumero);
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':contenido', $contenido);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);

            $sentence->execute();
            Flight::json(['id' => $id]);
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::new: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Actualizar una cláusula.
     * PUT /contratos-clausulas
     */
    public static function replace()
    {
        $db = Flight::db();
        try {
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id de la cláusula'], 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE contratos_clausulas SET
                    tipo = :tipo,
                    id_cargo = :id_cargo,
                    numero = :numero,
                    subnumero = :subnumero,
                    titulo = :titulo,
                    contenido = :contenido,
                    orden = :orden,
                    activo = :activo
                WHERE id = :id
                  AND id_tenant = :id_tenant
            ");

            $tipo      = Flight::request()->data['tipo'] ?? null;
            $idCargo   = Flight::request()->data['id_cargo'] ?? null;
            $numero    = Flight::request()->data['numero'] ?? null;
            $subnumero = Flight::request()->data['subnumero'] ?? null;
            $titulo    = Flight::request()->data['titulo'] ?? null;
            $contenido = Flight::request()->data['contenido'] ?? null;
            $orden     = Flight::request()->data['orden'] ?? 0;
            $activo    = isset(Flight::request()->data['activo']) ? (int) Flight::request()->data['activo'] : 1;

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':tipo', $tipo);
            $sentence->bindParam(':id_cargo', $idCargo);
            $sentence->bindParam(':numero', $numero);
            $sentence->bindParam(':subnumero', $subnumero);
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':contenido', $contenido);
            $sentence->bindParam(':orden', $orden);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            Flight::json(['id' => $id, 'message' => 'Cláusula actualizada correctamente']);
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::replace: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Eliminar una cláusula.
     * DELETE /contratos-clausulas
     */
    public static function delete()
    {
        $db = Flight::db();
        try {
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(['error' => 'Falta el id de la cláusula'], 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM contratos_clausulas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json(['id' => $id, 'message' => 'Cláusula eliminada correctamente']);
        } catch (Exception $e) {
            error_log("Error en ContratosClausulas::delete: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}