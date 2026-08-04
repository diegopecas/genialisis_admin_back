<?php
class CuentaPagada
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha from cuenta_pagada where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha from cuenta_pagada where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPagoRecibido($id_pago_recibido)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                cp.id,
                cp.id_cuenta_por_cobrar,
                cp.id_pago_recibido,
                cp.valor_aplicado,
                cp.fecha,
                cpc.fecha AS fecha_cobro,
                ps.id AS id_producto_servicio,
                ps.nombre AS nombre_producto_servicio
            FROM cuenta_pagada cp
            INNER JOIN cuentas_por_cobrar cpc ON cp.id_cuenta_por_cobrar = cpc.id
            inner join productos_servicios ps on ps.id = cpc.id_producto_servicio
            WHERE cp.id_pago_recibido = :id_pago_recibido
            AND cp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCuentaPorCobrar($id_cuenta_por_cobrar)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                cp.id,
                cp.id_cuenta_por_cobrar,
                cp.id_pago_recibido,
                cp.valor_aplicado,
                cp.fecha AS fecha_aplicacion,
                pr.fecha AS fecha_pago,
                pr.referencia_bancaria,
                pr.anulado,
                tp.nombre AS tipo_pago
            FROM 
                cuenta_pagada cp
            INNER JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            LEFT JOIN 
                tipos_pagos tp ON pr.id_tipo_pago = tp.id
            WHERE 
                cp.id_cuenta_por_cobrar = :id_cuenta_por_cobrar
                AND cp.id_tenant = :id_tenant
            ORDER BY
                pr.fecha DESC
        ");
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_cuenta_por_cobrar = Flight::request()->data['id_cuenta_por_cobrar'];
        $id_pago_recibido = Flight::request()->data['id_pago_recibido'];
        $valor_aplicado = Flight::request()->data['valor_aplicado'];
        $fecha = Flight::request()->data['fecha'];

        $id = Uuid::generar();
        $sentence = $db->prepare("insert into cuenta_pagada(id, id_tenant, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha) values (:id, :id_tenant, :id_cuenta_por_cobrar, :id_pago_recibido, :valor_aplicado, :fecha)");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindParam(':valor_aplicado', $valor_aplicado);
        $sentence->bindParam(':fecha', $fecha);

        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_cuenta_por_cobrar = Flight::request()->data['id_cuenta_por_cobrar'];
        $id_pago_recibido = Flight::request()->data['id_pago_recibido'];
        $valor_aplicado = Flight::request()->data['valor_aplicado'];
        $fecha = Flight::request()->data['fecha'];

        $sentence = $db->prepare("update cuenta_pagada set id_cuenta_por_cobrar = :id_cuenta_por_cobrar, id_pago_recibido = :id_pago_recibido, valor_aplicado = :valor_aplicado, fecha = :fecha where id = :id and id_tenant = :id_tenant");

        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':id_cuenta_por_cobrar', $id_cuenta_por_cobrar);
        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
        $sentence->bindParam(':valor_aplicado', $valor_aplicado);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from cuenta_pagada where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }
    // Agregar este método en cuenta-pagada.service.php

    public static function createBatch()
    {
        try {
            $db = Flight::db();

            // Obtener el array de cuentas del body
            $cuentas = Flight::request()->data['cuentas'];
            $id_pago_recibido = Flight::request()->data['id_pago_recibido'];

            if (empty($cuentas) || !is_array($cuentas)) {
                Flight::json(['error' => 'No se proporcionaron cuentas válidas'], 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            $resultados = [];
            $errores = 0;

            try {
                // Preparar la consulta una sola vez
                $query = "INSERT INTO cuenta_pagada (id, id_tenant, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha) 
                     VALUES (:id, :id_tenant, :id_cuenta_por_cobrar, :id_pago_recibido, :valor_aplicado, :fecha)";
                $sentence = $db->prepare($query);

                // Insertar cada cuenta
                foreach ($cuentas as $cuenta) {
                    try {
                        $idCp = Uuid::generar();
                        $sentence->bindValue(':id', $idCp);
                        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $sentence->bindParam(':id_cuenta_por_cobrar', $cuenta['id_cuenta_por_cobrar']);
                        $sentence->bindParam(':id_pago_recibido', $id_pago_recibido);
                        $sentence->bindParam(':valor_aplicado', $cuenta['valor_aplicado']);
                        $sentence->bindParam(':fecha', $cuenta['fecha']);

                        $sentence->execute();

                        $resultados[] = [
                            'id' => $idCp,
                            'id_cuenta_por_cobrar' => $cuenta['id_cuenta_por_cobrar'],
                            'valor_aplicado' => $cuenta['valor_aplicado'],
                            'success' => true
                        ];
                    } catch (Exception $e) {
                        $errores++;
                        $resultados[] = [
                            'id_cuenta_por_cobrar' => $cuenta['id_cuenta_por_cobrar'],
                            'error' => $e->getMessage(),
                            'success' => false
                        ];
                    }
                }

                // Si hubo errores, hacer rollback
                if ($errores > 0) {
                    $db->rollBack();
                    Flight::json([
                        'error' => true,
                        'message' => "Se encontraron $errores errores al procesar las cuentas",
                        'detalles' => $resultados
                    ], 400);
                } else {
                    // Si todo salió bien, confirmar la transacción
                    $db->commit();
                    Flight::json([
                        'success' => true,
                        'message' => count($resultados) . ' cuentas aplicadas correctamente',
                        'resultados' => $resultados
                    ]);
                }
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Error en createBatch cuenta_pagada: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al procesar las cuentas pagadas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}