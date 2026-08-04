<?php
class PrestamosPagos
{

    /**
     * Obtener todos los pagos
     */
    public static function getAll()
    {
        try {
            $db = Flight::db();

            $stmt = $db->prepare("
            SELECT 
                pp.*,
                tp.nombre as nombre_tipo_pago,
                pc.numero_cuota,
                pc.monto_cuota as monto_cuota_original,
                pr.id_colaborador,
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_usuario_registro,
                n.periodo as nombre_nomina
            FROM prestamos_pagos pp
            INNER JOIN tipos_pago_prestamo tp ON pp.id_tipo_pago = tp.id
            LEFT JOIN prestamos_cuotas pc ON pp.id_cuota = pc.id
            LEFT JOIN prestamos pr ON pp.id_prestamo = pr.id
            LEFT JOIN usuarios u ON pp.id_usuario_registro = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            LEFT JOIN nominas n ON pp.id_nomina = n.id
            WHERE pp.id_tenant = :id_tenant
            ORDER BY pp.fecha_pago DESC
        ");

            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($result, 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener pagos',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un pago por ID
     */
    public static function getById($id)
    {
        try {
            $db = Flight::db();

            $stmt = $db->prepare("
            SELECT 
                pp.*,
                tp.nombre as nombre_tipo_pago,
                pc.numero_cuota,
                pc.monto_cuota as monto_cuota_original,
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_usuario_registro,
                n.periodo as nombre_nomina,
                n.periodo as periodo_nomina
            FROM prestamos_pagos pp
            INNER JOIN tipos_pago_prestamo tp ON pp.id_tipo_pago = tp.id
            LEFT JOIN prestamos_cuotas pc ON pp.id_cuota = pc.id
            LEFT JOIN usuarios u ON pp.id_usuario_registro = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            LEFT JOIN nominas n ON pp.id_nomina = n.id
            WHERE pp.id = :id AND pp.id_tenant = :id_tenant
        ");

            $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($result, 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener pago',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Obtener pagos de un préstamo
     */
    public static function getByPrestamo($id_prestamo)
    {
        try {
            $db = Flight::db();

            $stmt = $db->prepare("
            SELECT 
                pp.*,
                tp.nombre as nombre_tipo_pago,
                pc.numero_cuota,
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_usuario_registro,
                n.nombre as nombre_nomina,
                n.periodo as periodo_nomina
            FROM prestamos_pagos pp
            INNER JOIN tipos_pago_prestamo tp ON pp.id_tipo_pago = tp.id
            LEFT JOIN prestamos_cuotas pc ON pp.id_cuota = pc.id
            LEFT JOIN usuarios u ON pp.id_usuario_registro = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            LEFT JOIN nominas n ON pp.id_nomina = n.id
            WHERE pp.id_prestamo = :id_prestamo
            AND pp.id_tenant = :id_tenant
            ORDER BY pp.fecha_pago DESC
        ");

            $stmt->execute(['id_prestamo' => $id_prestamo, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($result, 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener pagos del préstamo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener pagos de una cuota específica
     */
    public static function getByCuota($id_cuota)
    {
        try {
            $db = Flight::db();

            $stmt = $db->prepare("
            SELECT 
                pp.*,
                tp.nombre as nombre_tipo_pago,
                CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_usuario_registro,
                n.periodo as nombre_nomina,
                n.periodo as periodo_nomina
            FROM prestamos_pagos pp
            INNER JOIN tipos_pago_prestamo tp ON pp.id_tipo_pago = tp.id
            LEFT JOIN usuarios u ON pp.id_usuario_registro = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            LEFT JOIN nominas n ON pp.id_nomina = n.id
            WHERE pp.id_cuota = :id_cuota
            AND pp.id_tenant = :id_tenant
            ORDER BY pp.fecha_pago DESC
        ");

            $stmt->execute(['id_cuota' => $id_cuota, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($result, 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener pagos de la cuota',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener cuotas con saldo pendiente de un préstamo
     */
    public static function getCuotasConSaldo($id_prestamo)
    {
        try {
            $db = Flight::db();

            $stmt = $db->prepare("
                SELECT 
                    pc.*,
                    ec.nombre as nombre_estado,
                    COALESCE(SUM(pp.monto_pagado), 0) as total_pagado,
                    (pc.monto_cuota - COALESCE(SUM(pp.monto_pagado), 0)) as saldo
                FROM prestamos_cuotas pc
                LEFT JOIN estados_cuota_prestamo ec ON pc.id_estado = ec.id
                LEFT JOIN prestamos_pagos pp ON pc.id = pp.id_cuota
                WHERE pc.id_prestamo = :id_prestamo
                AND pc.id_tenant = :id_tenant
                GROUP BY pc.id
                HAVING saldo > 0
                ORDER BY pc.numero_cuota ASC
            ");

            $stmt->execute(['id_prestamo' => $id_prestamo, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json($result, 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener cuotas con saldo',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo pago
     */
    public static function new()
    {
        try {
            $data = Flight::request()->data->getData();
            $db = Flight::db();

            // Validaciones
            if (
                !isset($data['id_prestamo']) || !isset($data['monto_pagado']) ||
                !isset($data['id_tipo_pago']) || !isset($data['fecha_pago'])
            ) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            try {
                $idPagoNuevo = null;
                // Si hay cuotas específicas, procesar cada una
                if (isset($data['cuotas']) && is_array($data['cuotas'])) {
                    $montoPorCuota = $data['monto_pagado'] / count($data['cuotas']);

                    foreach ($data['cuotas'] as $idCuota) {
                        // Obtener información de la cuota
                        $stmtCuota = $db->prepare("
                            SELECT 
                                pc.*,
                                COALESCE(SUM(pp.monto_pagado), 0) as total_pagado,
                                (pc.monto_cuota - COALESCE(SUM(pp.monto_pagado), 0)) as saldo
                            FROM prestamos_cuotas pc
                            LEFT JOIN prestamos_pagos pp ON pc.id = pp.id_cuota
                            WHERE pc.id = :id_cuota
                            AND pc.id_tenant = :id_tenant
                            GROUP BY pc.id
                        ");
                        $stmtCuota->execute(['id_cuota' => $idCuota, 'id_tenant' => TenantContext::id()]);
                        $cuota = $stmtCuota->fetch(PDO::FETCH_ASSOC);

                        if (!$cuota) {
                            throw new Exception("Cuota no encontrada");
                        }

                        // Validar que no exceda el saldo
                        $montoAPagar = min($montoPorCuota, $cuota['saldo']);

                        // Insertar el pago
                        $stmtPago = $db->prepare("
                            INSERT INTO prestamos_pagos (
                                id,
                                id_tenant,
                                id_prestamo,
                                id_cuota,
                                id_nomina,
                                fecha_pago,
                                monto_pagado,
                                id_tipo_pago,
                                referencia,
                                observaciones,
                                id_usuario_registro,
                                fecha_registro
                            ) VALUES (
                                :id,
                                :id_tenant,
                                :id_prestamo,
                                :id_cuota,
                                :id_nomina,
                                :fecha_pago,
                                :monto_pagado,
                                :id_tipo_pago,
                                :referencia,
                                :observaciones,
                                :id_usuario_registro,
                                NOW()
                            )
                        ");

                        $idPagoNuevo = Uuid::generar();
                        $stmtPago->execute([
                            'id' => $idPagoNuevo,
                            'id_tenant' => TenantContext::id(),
                            'id_prestamo' => $data['id_prestamo'],
                            'id_cuota' => $idCuota,
                            'id_nomina' => $data['id_nomina'] ?? null,
                            'fecha_pago' => $data['fecha_pago'],
                            'monto_pagado' => $montoAPagar,
                            'id_tipo_pago' => $data['id_tipo_pago'],
                            'referencia' => $data['referencia'] ?? null,
                            'observaciones' => $data['observaciones'] ?? null,
                            'id_usuario_registro' => $data['id_usuario_registro']
                        ]);

                        // Calcular nuevo total pagado de la cuota
                        $nuevoTotalPagado = $cuota['total_pagado'] + $montoAPagar;

                        // Si la cuota está completamente pagada, actualizar estado
                        if ($nuevoTotalPagado >= $cuota['monto_cuota']) {
                            $stmtUpdateCuota = $db->prepare("
                                UPDATE prestamos_cuotas 
                                SET id_estado = 2,
                                    fecha_pago = :fecha_pago
                                WHERE id = :id_cuota
                                AND id_tenant = :id_tenant
                            ");
                            $stmtUpdateCuota->execute([
                                'id_cuota' => $idCuota,
                                'fecha_pago' => $data['fecha_pago'],
                                'id_tenant' => TenantContext::id()
                            ]);
                        }
                    }
                }

                // Actualizar el saldo del préstamo y verificar si está finalizado
                self::actualizarSaldoPrestamo($db, $data['id_prestamo']);

                // Confirmar transacción
                $db->commit();

                Flight::json([
                    'success' => true,
                    'message' => 'Pago registrado correctamente',
                    'id' => $idPagoNuevo
                ], 200);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear pago',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un pago
     */
    public static function replace()
    {
        try {
            $data = Flight::request()->data->getData();

            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }

            $db = Flight::db();

            $stmt = $db->prepare("
                UPDATE prestamos_pagos 
                SET 
                    fecha_pago = :fecha_pago,
                    monto_pagado = :monto_pagado,
                    id_tipo_pago = :id_tipo_pago,
                    referencia = :referencia,
                    observaciones = :observaciones
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $stmt->execute([
                'id' => $data['id'],
                'id_tenant' => TenantContext::id(),
                'fecha_pago' => $data['fecha_pago'],
                'monto_pagado' => $data['monto_pagado'],
                'id_tipo_pago' => $data['id_tipo_pago'],
                'referencia' => $data['referencia'] ?? null,
                'observaciones' => $data['observaciones'] ?? null
            ]);

            Flight::json([
                'success' => true,
                'message' => 'Pago actualizado correctamente'
            ], 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar pago',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Anular un pago
     */
    public static function anular()
    {
        try {
            $data = Flight::request()->data->getData();
            $db = Flight::db();

            if (!isset($data['id']) || !isset($data['motivo_anulacion'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }

            // Verificar que el préstamo no esté finalizado
            $stmtCheck = $db->prepare("
                SELECT p.id_estado 
                FROM prestamos p
                INNER JOIN prestamos_pagos pp ON p.id = pp.id_prestamo
                WHERE pp.id = :id_pago
                AND pp.id_tenant = :id_tenant
            ");
            $stmtCheck->execute(['id_pago' => $data['id'], 'id_tenant' => TenantContext::id()]);
            $prestamo = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($prestamo['id_estado'] == 2) {
                Flight::json([
                    'error' => 'No se puede anular un pago de un préstamo finalizado'
                ], 400);
                return;
            }

            // Iniciar transacción
            $db->beginTransaction();

            try {
                // Obtener datos del pago antes de anularlo
                $stmtPago = $db->prepare("
                    SELECT id_prestamo, id_cuota, monto_pagado 
                    FROM prestamos_pagos 
                    WHERE id = :id
                    AND id_tenant = :id_tenant
                ");
                $stmtPago->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
                $pago = $stmtPago->fetch(PDO::FETCH_ASSOC);

                if (!$pago) {
                    throw new Exception("Pago no encontrado");
                }

                // Eliminar el pago
                $stmtDelete = $db->prepare("DELETE FROM prestamos_pagos WHERE id = :id AND id_tenant = :id_tenant");
                $stmtDelete->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);

                // Si tiene cuota asociada, actualizar su estado
                if ($pago['id_cuota']) {
                    // Verificar cuánto se ha pagado de esa cuota ahora
                    $stmtSaldoCuota = $db->prepare("
                        SELECT 
                            pc.monto_cuota,
                            COALESCE(SUM(pp.monto_pagado), 0) as total_pagado
                        FROM prestamos_cuotas pc
                        LEFT JOIN prestamos_pagos pp ON pc.id = pp.id_cuota
                        WHERE pc.id = :id_cuota
                        AND pc.id_tenant = :id_tenant
                        GROUP BY pc.id
                    ");
                    $stmtSaldoCuota->execute(['id_cuota' => $pago['id_cuota'], 'id_tenant' => TenantContext::id()]);
                    $cuota = $stmtSaldoCuota->fetch(PDO::FETCH_ASSOC);

                    // Si ya no está completamente pagada, volver a pendiente
                    if ($cuota['total_pagado'] < $cuota['monto_cuota']) {
                        $stmtUpdateCuota = $db->prepare("
                            UPDATE prestamos_cuotas 
                            SET id_estado = 1,
                                fecha_pago = NULL
                            WHERE id = :id_cuota
                            AND id_tenant = :id_tenant
                        ");
                        $stmtUpdateCuota->execute(['id_cuota' => $pago['id_cuota'], 'id_tenant' => TenantContext::id()]);
                    }
                }

                // Actualizar el saldo del préstamo
                self::actualizarSaldoPrestamo($db, $pago['id_prestamo']);

                // Confirmar transacción
                $db->commit();

                Flight::json([
                    'success' => true,
                    'message' => 'Pago anulado correctamente'
                ], 200);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al anular pago',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un pago
     */
    public static function delete()
    {
        try {
            $data = Flight::request()->data->getData();

            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }

            $db = Flight::db();

            $stmt = $db->prepare("DELETE FROM prestamos_pagos WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);

            Flight::json([
                'success' => true,
                'message' => 'Pago eliminado correctamente'
            ], 200);
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar pago',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar saldo del préstamo y verificar si está finalizado
     */
    private static function actualizarSaldoPrestamo($db, $idPrestamo)
    {
        // Calcular total pagado
        $stmtTotal = $db->prepare("
            SELECT 
                p.monto_total,
                COALESCE(SUM(pp.monto_pagado), 0) as total_pagado
            FROM prestamos p
            LEFT JOIN prestamos_pagos pp ON p.id = pp.id_prestamo
            WHERE p.id = :id_prestamo
            AND p.id_tenant = :id_tenant
            GROUP BY p.id
        ");
        $stmtTotal->execute(['id_prestamo' => $idPrestamo, 'id_tenant' => TenantContext::id()]);
        $result = $stmtTotal->fetch(PDO::FETCH_ASSOC);

        $totalPagado = $result['total_pagado'];
        $montoTotal = $result['monto_total'];
        $saldo = $montoTotal - $totalPagado;

        // Determinar estado: si saldo = 0, está finalizado
        $nuevoEstado = ($saldo <= 0) ? 2 : 1;

        // Actualizar préstamo
        $stmtUpdate = $db->prepare("
            UPDATE prestamos 
            SET id_estado = :id_estado
            WHERE id = :id_prestamo
            AND id_tenant = :id_tenant
        ");
        $stmtUpdate->execute([
            'id_prestamo' => $idPrestamo,
            'id_estado' => $nuevoEstado,
            'id_tenant' => TenantContext::id()
        ]);
    }
}
