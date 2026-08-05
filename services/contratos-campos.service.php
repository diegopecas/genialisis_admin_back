<?php

/**
 * Valores que el usuario diligencio para los campos parametrizables de un
 * contrato. Se guardan por llave, de modo que agregar un campo nuevo a una
 * plantilla no obliga a cambiar la estructura de la tabla de contratos.
 */
class ContratosCampos
{
    /**
     * GET /contratos-campos/@idContrato
     */
    public static function getPorContrato($idContrato)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT llave, valor
                FROM contratos_campos
                WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_contrato', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * POST /contratos-campos
     * Guarda todos los campos de un contrato de una sola vez.
     * Espera { id_contrato, campos: [ { llave, valor } ] }.
     */
    public static function guardar()
    {
        $db = Flight::db();

        try {
            $data = Flight::request()->data;
            $idContrato = $data->id_contrato;
            $campos = isset($data->campos) ? $data->campos : [];

            $db->beginTransaction();

            $borrar = $db->prepare("DELETE FROM contratos_campos WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_contrato', $idContrato);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            $insertar = $db->prepare("
                INSERT INTO contratos_campos (id, id_tenant, id_contrato, llave, valor)
                VALUES (:id, :id_tenant, :id_contrato, :llave, :valor)
            ");

            foreach ($campos as $campo) {
                $llave = is_array($campo) ? $campo['llave'] : $campo->llave;
                $valor = is_array($campo) ? $campo['valor'] : $campo->valor;

                if ($llave === null || $llave === '') {
                    continue;
                }

                $id = Uuid::generar();
                $insertar->bindValue(':id', $id);
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindValue(':id_contrato', $idContrato);
                $insertar->bindValue(':llave', $llave);
                $insertar->bindValue(':valor', $valor);
                $insertar->execute();
            }

            $db->commit();
            Flight::json(array('id_contrato' => $idContrato, 'total' => count($campos)));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
