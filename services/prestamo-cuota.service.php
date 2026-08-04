<?php
class PrestamoCuota
{
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    pc.id,
                    pc.id_prestamo,
                    pc.numero_cuota,
                    pc.fecha_programada,
                    pc.monto_cuota,
                    pc.monto_capital,
                    pc.monto_interes,
                    pc.id_estado,
                    ec.nombre as nombre_estado,
                    pc.fecha_pago,
                    pc.observaciones
                from 
                    prestamos_cuotas pc
                left join 
                    estados_cuota_prestamo ec on pc.id_estado = ec.id
                where 
                    pc.id_tenant = :id_tenant
                order by 
                    pc.id_prestamo, pc.numero_cuota
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll prestamo cuotas: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuotas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    pc.id,
                    pc.id_prestamo,
                    pc.numero_cuota,
                    pc.fecha_programada,
                    pc.monto_cuota,
                    pc.monto_capital,
                    pc.monto_interes,
                    pc.id_estado,
                    ec.nombre as nombre_estado,
                    pc.fecha_pago,
                    pc.observaciones
                from 
                    prestamos_cuotas pc
                left join 
                    estados_cuota_prestamo ec on pc.id_estado = ec.id
                where 
                    pc.id = :id
                    and pc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById prestamo cuota: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener la cuota',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByPrestamo($id_prestamo)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                select 
                    pc.id,
                    pc.id_prestamo,
                    pc.numero_cuota,
                    pc.fecha_programada,
                    pc.monto_cuota,
                    pc.monto_capital,
                    pc.monto_interes,
                    pc.id_estado,
                    ec.nombre as nombre_estado,
                    pc.fecha_pago,
                    pc.observaciones,
                    coalesce((
                        select sum(pp.monto_pagado) 
                        from prestamos_pagos pp 
                        where pp.id_cuota = pc.id
                    ), 0) as monto_pagado_cuota,
                    (pc.monto_cuota - coalesce((
                        select sum(pp.monto_pagado) 
                        from prestamos_pagos pp 
                        where pp.id_cuota = pc.id
                    ), 0)) as saldo_cuota
                from 
                    prestamos_cuotas pc
                left join 
                    estados_cuota_prestamo ec on pc.id_estado = ec.id
                where 
                    pc.id_prestamo = :id_prestamo
                    and pc.id_tenant = :id_tenant
                order by 
                    pc.numero_cuota
            ");
            $sentence->bindParam(':id_prestamo', $id_prestamo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByPrestamo cuotas: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuotas del préstamo',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_prestamo = Flight::request()->data['id_prestamo'];
            $numero_cuota = Flight::request()->data['numero_cuota'];
            $fecha_programada = Flight::request()->data['fecha_programada'];
            $monto_cuota = Flight::request()->data['monto_cuota'];
            $monto_capital = Flight::request()->data['monto_capital'];
            $monto_interes = Flight::request()->data['monto_interes'];
            $id_estado = Flight::request()->data['id_estado'];
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            $sentence = $db->prepare("
                insert into prestamos_cuotas(
                    id, id_tenant, id_prestamo, numero_cuota, fecha_programada, monto_cuota,
                    monto_capital, monto_interes, id_estado, observaciones
                ) values (
                    :id, :id_tenant, :id_prestamo, :numero_cuota, :fecha_programada, :monto_cuota,
                    :monto_capital, :monto_interes, :id_estado, :observaciones
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_prestamo', $id_prestamo);
            $sentence->bindParam(':numero_cuota', $numero_cuota);
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':monto_cuota', $monto_cuota);
            $sentence->bindParam(':monto_capital', $monto_capital);
            $sentence->bindParam(':monto_interes', $monto_interes);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':observaciones', $observaciones);

            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (PDOException $e) {
            error_log("Error PDO en new prestamo cuota: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        } catch (Exception $e) {
            error_log("Error general en new prestamo cuota: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $id_prestamo = Flight::request()->data['id_prestamo'];
            $numero_cuota = Flight::request()->data['numero_cuota'];
            $fecha_programada = Flight::request()->data['fecha_programada'];
            $monto_cuota = Flight::request()->data['monto_cuota'];
            $monto_capital = Flight::request()->data['monto_capital'];
            $monto_interes = Flight::request()->data['monto_interes'];
            $id_estado = Flight::request()->data['id_estado'];
            $fecha_pago = isset(Flight::request()->data['fecha_pago']) ? Flight::request()->data['fecha_pago'] : null;
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            $sentence = $db->prepare("
                update prestamos_cuotas set 
                    id_prestamo = :id_prestamo,
                    numero_cuota = :numero_cuota,
                    fecha_programada = :fecha_programada,
                    monto_cuota = :monto_cuota,
                    monto_capital = :monto_capital,
                    monto_interes = :monto_interes,
                    id_estado = :id_estado,
                    fecha_pago = :fecha_pago,
                    observaciones = :observaciones
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_prestamo', $id_prestamo);
            $sentence->bindParam(':numero_cuota', $numero_cuota);
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':monto_cuota', $monto_cuota);
            $sentence->bindParam(':monto_capital', $monto_capital);
            $sentence->bindParam(':monto_interes', $monto_interes);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':fecha_pago', $fecha_pago);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en replace prestamo cuota: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from prestamos_cuotas where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function createBatch()
    {
        try {
            $db = Flight::db();

            $cuotas = Flight::request()->data['cuotas'];
            $id_prestamo = Flight::request()->data['id_prestamo'];

            if (empty($cuotas) || !is_array($cuotas)) {
                Flight::json(['error' => 'No se proporcionaron cuotas válidas'], 400);
                return;
            }

            $db->beginTransaction();

            $resultados = [];
            $errores = 0;

            try {
                $query = "insert into prestamos_cuotas (
                    id, id_tenant, id_prestamo, numero_cuota, fecha_programada, monto_cuota,
                    monto_capital, monto_interes, id_estado
                ) values (
                    :id, :id_tenant, :id_prestamo, :numero_cuota, :fecha_programada, :monto_cuota,
                    :monto_capital, :monto_interes, :id_estado
                )";
                $sentence = $db->prepare($query);

                foreach ($cuotas as $cuota) {
                    try {
                        $idCuota = Uuid::generar();
                        $sentence->bindValue(':id', $idCuota);
                        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $sentence->bindParam(':id_prestamo', $id_prestamo);
                        $sentence->bindParam(':numero_cuota', $cuota['numero_cuota']);
                        $sentence->bindParam(':fecha_programada', $cuota['fecha_programada']);
                        $sentence->bindParam(':monto_cuota', $cuota['monto_cuota']);
                        $sentence->bindParam(':monto_capital', $cuota['monto_capital']);
                        $sentence->bindParam(':monto_interes', $cuota['monto_interes']);
                        $sentence->bindParam(':id_estado', $cuota['id_estado']);

                        $sentence->execute();

                        $resultados[] = [
                            'id' => $idCuota,
                            'numero_cuota' => $cuota['numero_cuota'],
                            'success' => true
                        ];
                    } catch (Exception $e) {
                        $errores++;
                        $resultados[] = [
                            'numero_cuota' => $cuota['numero_cuota'],
                            'error' => $e->getMessage(),
                            'success' => false
                        ];
                    }
                }

                if ($errores > 0) {
                    $db->rollBack();
                    Flight::json([
                        'error' => true,
                        'message' => "Se encontraron $errores errores al procesar las cuotas",
                        'detalles' => $resultados
                    ], 400);
                } else {
                    $db->commit();
                    Flight::json([
                        'success' => true,
                        'message' => count($resultados) . ' cuotas creadas correctamente',
                        'resultados' => $resultados
                    ]);
                }
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            error_log('Error en createBatch prestamos_cuotas: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al procesar las cuotas',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function marcarPagada()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $fecha_pago = isset(Flight::request()->data['fecha_pago']) ? 
                Flight::request()->data['fecha_pago'] : 
                date('Y-m-d H:i:s');

            $sentence = $db->prepare("
                update prestamos_cuotas 
                set id_estado = 2, 
                    fecha_pago = :fecha_pago
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':fecha_pago', $fecha_pago);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en marcarPagada cuota: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al marcar la cuota como pagada',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function anular()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("
                update prestamos_cuotas 
                set id_estado = 3
                where id = :id
                and id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log('Error en anular cuota: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al anular la cuota',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }
}