<?php
class CuentasPorCobrar
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    c.fecha_anulacion,
                    c.id_usuario_anulacion,
                    COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(cp.valor_aplicado), 0) AS saldo
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                WHERE c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, c.detalle, c.id_usuario,
                    c.anulado, c.fecha_anulacion, c.id_usuario_anulacion
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAll cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();

        error_log('getById: ' . $id);
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT 
                c.id, 
                c.id_producto_servicio, 
                c.id_persona, 
                c.fecha, 
                c.valor, 
                c.detalle, 
                c.id_usuario,
                c.anulado,
                c.fecha_anulacion,
                c.id_usuario_anulacion,
                COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS valor_pagado,
                c.valor - COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS saldo
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN 
                cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
            LEFT JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            WHERE 
                c.id = :id AND c.id_tenant = :id_tenant
            GROUP BY 
                c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, c.detalle, c.id_usuario,
                c.anulado, c.fecha_anulacion, c.id_usuario_anulacion
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getById cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener la cuenta por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByPersona($id)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
        SELECT 
            cpc.id,
            cpc.fecha,
            cpc.valor,
            cpc.id_usuario_anulacion,
            COALESCE(SUM(
                CASE 
                    WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                    ELSE 0 
                END
            ), 0) AS valor_pagado,
            cpc.valor - COALESCE(SUM(
                CASE 
                    WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                    ELSE 0 
                END
            ), 0) AS saldo,
            cpc.detalle,
            ps.nombre AS nombre_producto_servicio,
            ps.id_periodicidad_cobro,
            pc.nombre AS periodicidad_cobro_nombre,
            ps.id_clasificacion_productos_servicios,
            cps.nombre AS nombre_clasificacion,
            cps.codigo AS clasificacion_codigo,
            CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_usuario
        FROM 
            cuentas_por_cobrar cpc
        INNER JOIN 
            productos_servicios ps ON ps.id = cpc.id_producto_servicio
        INNER JOIN 
            clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
        INNER JOIN 
            usuarios u ON u.id = cpc.id_usuario
        INNER JOIN 
            personas p ON p.id = u.id_persona
        LEFT JOIN 
            periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        LEFT JOIN 
            cuenta_pagada cp ON cpc.id = cp.id_cuenta_por_cobrar
        LEFT JOIN 
            pagos_recibidos pr ON cp.id_pago_recibido = pr.id
        WHERE 
            cpc.id_persona = :id
            AND (cpc.anulado = 0 OR cpc.anulado IS NULL)
            AND cpc.id_tenant = :id_tenant
        GROUP BY 
            cpc.id, cpc.fecha, cpc.valor, cpc.detalle, ps.nombre, cps.nombre, cps.codigo, 
            p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
            cpc.anulado, cpc.fecha_anulacion, cpc.id_usuario_anulacion,
            ps.id_periodicidad_cobro, pc.id, pc.nombre
        ORDER BY 
            cpc.fecha, cps.nombre, ps.nombre 
    ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByPersona cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener cuentas por cobrar de la persona',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();

            $data = $request->data->getData();

            $sql = "INSERT INTO cuentas_por_cobrar (
                id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, 
                anulado, fecha_anulacion, id_usuario_anulacion
            ) VALUES (
                :id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                0, NULL, NULL
            )";

            $stmt = $db->prepare($sql);
            $idCxcNew = Uuid::generar();
            $stmt->bindValue(':id', $idCxcNew);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':detalle', $data['detalle']);
            $stmt->bindParam(':id_usuario', $data['id_usuario']);
            $stmt->execute();

            $id = $idCxcNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->new(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al crear cuenta por cobrar'), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "UPDATE cuentas_por_cobrar SET
                id_producto_servicio = :id_producto_servicio,
                id_persona = :id_persona,
                fecha = :fecha,
                valor = :valor,
                detalle = :detalle,
                id_usuario = :id_usuario";

            if (isset($data['anulado'])) {
                $sql .= ", anulado = :anulado";
            }
            if (isset($data['fecha_anulacion'])) {
                $sql .= ", fecha_anulacion = :fecha_anulacion";
            }
            if (isset($data['id_usuario_anulacion'])) {
                $sql .= ", id_usuario_anulacion = :id_usuario_anulacion";
            }

            $sql .= " WHERE id = :id AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindParam(':detalle', $data['detalle']);
            $stmt->bindParam(':id_usuario', $data['id_usuario']);

            if (isset($data['anulado'])) {
                $stmt->bindParam(':anulado', $data['anulado']);
            }
            if (isset($data['fecha_anulacion'])) {
                $stmt->bindParam(':fecha_anulacion', $data['fecha_anulacion']);
            }
            if (isset($data['id_usuario_anulacion'])) {
                $stmt->bindParam(':id_usuario_anulacion', $data['id_usuario_anulacion']);
            }

            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para actualizar'), 404);
                return;
            }

            error_log("ID actualizado: " . $data['id']);
            Flight::json(array('id' => $data['id']));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->replace(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al actualizar cuenta por cobrar'), 500);
        }
    }

    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            if (!isset($data['id']) || !isset($data['id_usuario_anulacion'])) {
                Flight::json(array('error' => 'Faltan datos requeridos (id, id_usuario_anulacion)'), 400);
                return;
            }

            $fechaActual = date('Y-m-d H:i:s');

            $sql = "UPDATE cuentas_por_cobrar SET
                anulado = 1,
                fecha_anulacion = :fecha_anulacion,
                id_usuario_anulacion = :id_usuario_anulacion
                WHERE id = :id AND id_tenant = :id_tenant";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id', $data['id']);
            $stmt->bindParam(':fecha_anulacion', $fechaActual);
            $stmt->bindParam(':id_usuario_anulacion', $data['id_usuario_anulacion']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el registro con el ID especificado para anular'), 404);
                return;
            }

            Flight::json(array(
                'id' => $data['id'],
                'anulado' => 1,
                'fecha_anulacion' => $fechaActual,
                'id_usuario_anulacion' => $data['id_usuario_anulacion']
            ));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->anular(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al anular cuenta por cobrar'), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $stmt = $db->prepare("DELETE FROM cuentas_por_cobrar WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->delete(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al borrar cuentas_por_cobrar'), 500);
        }
    }

    public static function verificarDuplicados()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $request = Flight::request();
            $data = $request->data->getData();

            $sql = "SELECT 
                    c.id, 
                    c.fecha, 
                    c.valor,
                    COALESCE(SUM(cp.valor_aplicado), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(cp.valor_aplicado), 0) AS saldo
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                WHERE 
                    c.id_producto_servicio = :id_producto_servicio 
                    AND c.id_persona = :id_persona 
                    AND c.fecha = :fecha 
                    AND c.valor = :valor
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.fecha, c.valor";

            $stmt = $db->prepare($sql);
            $stmt->bindParam(':id_producto_servicio', $data['id_producto_servicio']);
            $stmt->bindParam(':id_persona', $data['id_persona']);
            $stmt->bindParam(':fecha', $data['fecha']);
            $stmt->bindParam(':valor', $data['valor']);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $registrosDuplicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'duplicados' => $registrosDuplicados,
                'cantidad' => count($registrosDuplicados)
            ]);
        } catch (Exception $e) {
            error_log('Error en cuentas_por_cobrar->verificarDuplicados(): ' . $e->getMessage());
            Flight::json(array('error' => 'Error al verificar duplicados'), 500);
        }
    }

    public static function getAllConDetalle()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    c.fecha_anulacion,
                    c.id_usuario_anulacion,
                    COALESCE(SUM(
                        CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(
                        CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS saldo,
                    ps.nombre AS nombre_producto_servicio,
                    ps.id_clasificacion_productos_servicios,
                    cps.nombre AS nombre_clasificacion,
                    CONCAT(
                        COALESCE(p.primer_nombre, ''), ' ',
                        COALESCE(p.segundo_nombre, ''), ' ',
                        COALESCE(p.primer_apellido, ''), ' ',
                        COALESCE(p.segundo_apellido, '')
                    ) AS nombre_persona,
                    p.numero_identificacion,
                    e.id AS id_cliente,
                    eg.id_plan,
                    g.nombre AS nombre_plan,
                    CONCAT(
                        COALESCE(pu.primer_nombre, ''), ' ',
                        COALESCE(pu.segundo_nombre, ''), ' ',
                        COALESCE(pu.primer_apellido, ''), ' ',
                        COALESCE(pu.segundo_apellido, '')
                    ) AS nombre_usuario
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                LEFT JOIN 
                    pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                LEFT JOIN
                    productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN
                    clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN
                    personas p ON p.id = c.id_persona
                LEFT JOIN
                    clientes e ON e.id_persona = p.id
                LEFT JOIN
                    clientes_x_planes eg ON eg.id_cliente = e.id AND eg.activo = 1
                LEFT JOIN
                    planes g ON g.id = eg.id_plan
                LEFT JOIN
                    usuarios u ON u.id = c.id_usuario
                LEFT JOIN
                    personas pu ON pu.id = u.id_persona
                WHERE
                    (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                    c.detalle, c.id_usuario, c.anulado, c.fecha_anulacion, 
                    c.id_usuario_anulacion,
                    ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.numero_identificacion, e.id, eg.id_plan, g.nombre,
                    pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido
                ORDER BY 
                    c.fecha DESC, p.primer_apellido, p.primer_nombre
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAllConDetalle cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar con detalle',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getResumenCartera()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
                SELECT 
                    COUNT(DISTINCT c.id) as total_cuentas,
                    SUM(c.valor) as total_cobrado,
                    SUM(CASE WHEN c.saldo <= 0 THEN 1 ELSE 0 END) as cuentas_pagadas,
                    SUM(CASE WHEN c.saldo > 0 THEN 1 ELSE 0 END) as cuentas_pendientes,
                    SUM(CASE 
                        WHEN c.saldo > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                        THEN 1 ELSE 0 
                    END) as cuentas_vencidas,
                    SUM(COALESCE(cp_sum.total_pagado, 0)) as total_recaudado,
                    SUM(CASE 
                        WHEN c.saldo > 0 
                        THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                        ELSE 0 
                    END) as saldo_pendiente,
                    SUM(CASE 
                        WHEN c.saldo > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                        THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                        ELSE 0 
                    END) as saldo_vencido
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN (
                    SELECT 
                        cp.id_cuenta_por_cobrar,
                        SUM(CASE 
                            WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                            THEN cp.valor_aplicado 
                            ELSE 0 
                        END) as total_pagado
                    FROM cuenta_pagada cp
                    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    GROUP BY cp.id_cuenta_por_cobrar
                ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
                WHERE 
                    (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resumen = $sentence->fetch();

            $resumen['porcentaje_recaudo'] = $resumen['total_cobrado'] > 0
                ? round(($resumen['total_recaudado'] / $resumen['total_cobrado']) * 100, 2)
                : 0;

            Flight::json($resumen);
        } catch (Exception $e) {
            error_log('Error en getResumenCartera: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el resumen de cartera',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getReporteAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $stmt = $db->prepare("CALL sp_reporte_anual_cuentas_por_cobrar(:anio, :id_tenant)");
            $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $reporteClientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteValores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteClasificaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reportePagosDiarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $clienteClasificacion = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteProductos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $clienteProducto = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();
            $reporteAnulados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'anio' => $anio,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'reporte_clientes' => $reporteClientes,
                'reporte_valores' => $reporteValores,
                'reporte_clasificaciones' => $reporteClasificaciones,
                'reporte_pagos_diarios' => $reportePagosDiarios,
                'cliente_clasificacion' => $clienteClasificacion,
                'reporte_productos' => $reporteProductos,
                'cliente_producto' => $clienteProducto,
                'reporte_anulados' => $reporteAnulados
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al generar el reporte anual',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getAllConDetalleAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
            SELECT 
                c.id, 
                c.id_producto_servicio, 
                c.id_persona, 
                c.fecha, 
                c.valor, 
                c.detalle, 
                c.id_usuario,
                c.anulado,
                c.fecha_anulacion,
                c.id_usuario_anulacion,
                COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS valor_pagado,
                c.valor - COALESCE(SUM(
                    CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado 
                        ELSE 0 
                    END
                ), 0) AS saldo,
                ps.nombre AS nombre_producto_servicio,
                ps.id_clasificacion_productos_servicios,
                cps.nombre AS nombre_clasificacion,
                CONCAT(
                    COALESCE(p.primer_nombre, ''), ' ',
                    COALESCE(p.segundo_nombre, ''), ' ',
                    COALESCE(p.primer_apellido, ''), ' ',
                    COALESCE(p.segundo_apellido, '')
                ) AS nombre_persona,
                p.numero_identificacion,
                e.id AS id_cliente,
                eg.id_plan,
                g.nombre AS nombre_plan,
                CONCAT(
                    COALESCE(pu.primer_nombre, ''), ' ',
                    COALESCE(pu.segundo_nombre, ''), ' ',
                    COALESCE(pu.primer_apellido, ''), ' ',
                    COALESCE(pu.segundo_apellido, '')
                ) AS nombre_usuario
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN 
                cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
            LEFT JOIN 
                pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            LEFT JOIN
                productos_servicios ps ON ps.id = c.id_producto_servicio
            LEFT JOIN
                clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN
                personas p ON p.id = c.id_persona
            LEFT JOIN
                clientes e ON e.id_persona = p.id
            LEFT JOIN
                clientes_x_planes eg ON eg.id_cliente = e.id AND eg.activo = 1
            LEFT JOIN
                planes g ON g.id = eg.id_plan
            LEFT JOIN
                usuarios u ON u.id = c.id_usuario
            LEFT JOIN
                personas pu ON pu.id = u.id_persona
            WHERE
                YEAR(c.fecha) = :anio
                AND (c.anulado = 0 OR c.anulado IS NULL)
                AND c.id_tenant = :id_tenant
            GROUP BY 
                c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                c.detalle, c.id_usuario, c.anulado, c.fecha_anulacion, 
                c.id_usuario_anulacion,
                ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                p.numero_identificacion, e.id, eg.id_plan, g.nombre,
                pu.primer_nombre, pu.segundo_nombre, pu.primer_apellido, pu.segundo_apellido
            ORDER BY 
                c.fecha DESC, p.primer_apellido, p.primer_nombre
        ");

            $sentence->bindParam(':anio', $anio);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getAllConDetalleAnual cuentas_por_cobrar: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar con detalle',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getResumenCarteraAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $sentence = $db->prepare("
            SELECT 
                COUNT(DISTINCT c.id) as total_cuentas,
                SUM(c.valor) as total_cobrado,
                SUM(CASE WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) <= 0 THEN 1 ELSE 0 END) as cuentas_pagadas,
                SUM(CASE WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 THEN 1 ELSE 0 END) as cuentas_pendientes,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                    THEN 1 ELSE 0 
                END) as cuentas_vencidas,
                SUM(COALESCE(cp_sum.total_pagado, 0)) as total_recaudado,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) as saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND DATEDIFF(CURDATE(), c.fecha) > 30 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) as saldo_vencido
            FROM 
                cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) as total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE 
                YEAR(c.fecha) = :anio
                AND (c.anulado = 0 OR c.anulado IS NULL)
                AND c.id_tenant = :id_tenant
        ");

            $sentence->bindParam(':anio', $anio);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resumen = $sentence->fetch();

            $resumen['porcentaje_recaudo'] = $resumen['total_cobrado'] > 0
                ? round(($resumen['total_recaudado'] / $resumen['total_cobrado']) * 100, 2)
                : 0;

            $resumen['anio'] = $anio;

            Flight::json($resumen);
        } catch (Exception $e) {
            error_log('Error en getResumenCarteraAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el resumen de cartera anual',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    public static function getByMultipleIds()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $ids = Flight::request()->data['ids'];

            if (empty($ids) || !is_array($ids)) {
                Flight::json(['error' => 'No se proporcionaron IDs válidos'], 400);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $query = "
            SELECT 
                cpc.id,
                cpc.id_producto_servicio,
                cpc.id_persona,
                cpc.fecha,
                cpc.valor,
                cpc.detalle,
                cpc.fecha_generado,
                cpc.id_usuario,
                cpc.anulado,
                cpc.fecha_anulacion,
                cpc.id_usuario_anulacion,
                ps.nombre AS nombre_producto_servicio,
                ps.id_categoria_productos_servicios,
                cps.nombre AS nombre_categoria,
                CONCAT(p.primer_nombre, ' ', COALESCE(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', COALESCE(p.segundo_apellido, '')) AS nombre_persona,
                COALESCE((SELECT SUM(cp.valor_aplicado) 
                         FROM cuenta_pagada cp 
                         WHERE cp.id_cuenta_por_cobrar = cpc.id), 0) AS valor_pagado,
                (cpc.valor - COALESCE((SELECT SUM(cp.valor_aplicado) 
                                      FROM cuenta_pagada cp 
                                      WHERE cp.id_cuenta_por_cobrar = cpc.id), 0)) AS saldo
            FROM 
                cuentas_por_cobrar cpc
            LEFT JOIN 
                productos_servicios ps ON cpc.id_producto_servicio = ps.id
            LEFT JOIN 
                categoria_productos_servicios cps ON ps.id_categoria_productos_servicios = cps.id
            LEFT JOIN 
                personas p ON cpc.id_persona = p.id
            WHERE 
                cpc.id IN ($placeholders) AND cpc.id_tenant = ?
            ORDER BY 
                cpc.fecha ASC
        ";

            $sentence = $db->prepare($query);
            $sentence->execute(array_merge($ids, [TenantContext::id()]));
            $response = $sentence->fetchAll();

            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getByMultipleIds: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener las cuentas por cobrar',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera cuentas por cobrar a partir de los valores de un contrato de implementación.
     */
    public static function generarDesdeContrato()
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            $id_contrato = Flight::request()->data['id_contrato'];
            $id_usuario = Flight::request()->data['id_usuario'];

            $stmtContrato = $db->prepare("
                SELECT cm.id, cm.id_cliente, cm.anio, e.id_persona
                FROM contratos_cliente cm
                INNER JOIN clientes e ON cm.id_cliente = e.id
                WHERE cm.id = :id_contrato AND cm.id_tenant = :id_tenant
            ");
            $stmtContrato->bindParam(':id_contrato', $id_contrato);
            $stmtContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtContrato->execute();
            $contrato = $stmtContrato->fetch(PDO::FETCH_ASSOC);

            if (!$contrato) {
                Flight::json(array('error' => 'Contrato no encontrado'), 404);
                return;
            }

            $id_persona = $contrato['id_persona'];

            $stmtValores = $db->prepare("
                SELECT cmv.id, cmv.id_producto_servicio, cmv.fecha, cmv.valor,
                       ps.nombre AS nombre_producto,
                       ps.id_periodicidad_cobro
                FROM contratos_cliente_valores cmv
                INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
                ORDER BY cmv.fecha, ps.id_periodicidad_cobro
            ");
            $stmtValores->bindParam(':id_contrato', $id_contrato);
            $stmtValores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtValores->execute();
            $valores = $stmtValores->fetchAll(PDO::FETCH_ASSOC);

            if (empty($valores)) {
                Flight::json(array('error' => 'El contrato no tiene valores generados'), 400);
                return;
            }

            $stmtVerificar = $db->prepare("
                SELECT COUNT(*) AS cantidad
                FROM cuentas_por_cobrar
                WHERE id_persona = :id_persona
                  AND id_producto_servicio = :id_producto_servicio
                  AND fecha = :fecha
                  AND (anulado = 0 OR anulado IS NULL)
                  AND id_tenant = :id_tenant
            ");

            $duplicados = [];
            foreach ($valores as $valor) {
                $stmtVerificar->bindParam(':id_persona', $id_persona);
                $stmtVerificar->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                $stmtVerificar->bindParam(':fecha', $valor['fecha']);
                $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtVerificar->execute();
                $resultado = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

                if ($resultado['cantidad'] > 0) {
                    $duplicados[] = [
                        'nombre_producto' => $valor['nombre_producto'],
                        'fecha' => $valor['fecha']
                    ];
                }
            }

            if (!empty($duplicados)) {
                Flight::json(array(
                    'duplicados' => $duplicados,
                    'mensaje' => 'Ya existen cuentas por cobrar para algunos conceptos. Debe generarlas manualmente.'
                ));
                return;
            }

            $db->beginTransaction();

            $stmtInsert = $db->prepare("
                INSERT INTO cuentas_por_cobrar 
                (id, id_tenant, id_producto_servicio, id_persona, fecha, valor, detalle, id_usuario, 
                 anulado, fecha_anulacion, id_usuario_anulacion)
                VALUES 
                (:id, :id_tenant, :id_producto_servicio, :id_persona, :fecha, :valor, :detalle, :id_usuario,
                 0, NULL, NULL)
            ");

            $cuentasCreadas = 0;
            $totalImplementacion = 0;
            $totalSuscripcion = 0;

            $nombresMeses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
            ];

            foreach ($valores as $valor) {
                $mesNum = (int) date('n', strtotime($valor['fecha']));
                $anioFecha = date('Y', strtotime($valor['fecha']));
                $nombreMes = $nombresMeses[$mesNum];

                $tipoConcepto = ($valor['id_periodicidad_cobro'] == 1) ? 'Implementación' : 'Suscripción';
                $detalle = "Generado automáticamente - Contrato #{$id_contrato} - {$tipoConcepto} {$nombreMes} {$anioFecha}";

                $idCxc = Uuid::generar();
                $stmtInsert->bindValue(':id', $idCxc);
                $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtInsert->bindParam(':id_producto_servicio', $valor['id_producto_servicio']);
                $stmtInsert->bindParam(':id_persona', $id_persona);
                $stmtInsert->bindParam(':fecha', $valor['fecha']);
                $stmtInsert->bindParam(':valor', $valor['valor']);
                $stmtInsert->bindParam(':detalle', $detalle);
                $stmtInsert->bindParam(':id_usuario', $id_usuario);
                $stmtInsert->execute();

                $cuentasCreadas++;

                if ($valor['id_periodicidad_cobro'] == 1) {
                    $totalImplementacion += $valor['valor'];
                } else {
                    $totalSuscripcion += $valor['valor'];
                }
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'cuentas_creadas' => $cuentasCreadas,
                'total_implementacion' => $totalImplementacion,
                'total_suscripcion' => $totalSuscripcion,
                'total_general' => $totalImplementacion + $totalSuscripcion
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en CuentasPorCobrar::generarDesdeContrato: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Reporte de cartera de clientes con representantes responsables de pago.
     */
    public static function getReporteCarteraClientes($anio, $idCliente = null)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $idEst = ($idCliente !== null && $idCliente !== 'null') ? $idCliente : null;

            $stmt = $db->prepare("CALL sp_reporte_cartera_clientes(:anio, :id_cliente, :id_tenant)");
            $stmt->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmt->bindParam(':id_cliente', $idEst, PDO::PARAM_STR);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            $reporteClientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();

            $reporteValores = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->nextRowset();

            $representantesPago = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Flight::json([
                'anio' => $anio,
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'reporte_clientes' => $reporteClientes,
                'reporte_valores' => $reporteValores,
                'representantes_pago' => $representantesPago
            ]);
        } catch (Exception $e) {
            error_log('Error en getReporteCarteraClientes: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al generar el reporte de cartera de clientes',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte desagregado de cobros por año. Incluye clientes y colaboradores.
     * Retorna cada registro individual con tipo_persona y plan_o_cargo derivados.
     */
    public static function getReporteCobrosAnual($anio)
    {
        $userData = JWTService::requerirAutenticacion();

        try {
            $db = Flight::db();

            if (!is_numeric($anio) || $anio < 2000 || $anio > 2100) {
                Flight::json([
                    'error' => true,
                    'message' => 'Año inválido'
                ], 400);
                return;
            }

            $sentence = $db->prepare("
                SELECT 
                    c.id, 
                    c.id_producto_servicio, 
                    c.id_persona, 
                    c.fecha, 
                    c.valor, 
                    c.detalle, 
                    c.id_usuario,
                    c.anulado,
                    COALESCE(SUM(
                        CASE 
                            WHEN (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS valor_pagado,
                    c.valor - COALESCE(SUM(
                        CASE 
                            WHEN (pr.anulado = 0 OR pr.anulado IS NULL) THEN cp.valor_aplicado 
                            ELSE 0 
                        END
                    ), 0) AS saldo,
                    ps.nombre AS nombre_producto_servicio,
                    ps.id_clasificacion_productos_servicios,
                    cps.nombre AS nombre_clasificacion,
                    TRIM(CONCAT(
                        COALESCE(p.primer_nombre, ''), ' ',
                        COALESCE(p.segundo_nombre, ''), ' ',
                        COALESCE(p.primer_apellido, ''), ' ',
                        COALESCE(p.segundo_apellido, '')
                    )) AS nombre_persona,
                    p.numero_identificacion,
                    CASE 
                        WHEN e.id IS NOT NULL THEN 'Cliente'
                        WHEN col.id IS NOT NULL THEN 'Colaborador'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    CASE 
                        WHEN e.id IS NOT NULL THEN COALESCE(g.nombre, 'Sin plan')
                        WHEN col.id IS NOT NULL THEN COALESCE(ca.nombre, 'Sin cargo')
                        ELSE 'Sin asignar'
                    END AS plan_o_cargo,
                    e.id AS id_cliente,
                    col.id AS id_colaborador
                FROM 
                    cuentas_por_cobrar c
                LEFT JOIN 
                    cuenta_pagada cp ON c.id = cp.id_cuenta_por_cobrar
                LEFT JOIN 
                    pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                LEFT JOIN
                    productos_servicios ps ON ps.id = c.id_producto_servicio
                LEFT JOIN
                    clasificacion_productos_servicios cps ON cps.id = ps.id_clasificacion_productos_servicios
                LEFT JOIN
                    personas p ON p.id = c.id_persona
                LEFT JOIN
                    clientes e ON e.id_persona = p.id
                LEFT JOIN
                    clientes_x_planes eg ON eg.id_cliente = e.id AND eg.activo = 1
                LEFT JOIN
                    planes g ON g.id = eg.id_plan
                LEFT JOIN
                    colaboradores col ON col.id_persona = p.id
                LEFT JOIN
                    cargos ca ON ca.id = col.id_cargo
                WHERE
                    YEAR(c.fecha) = :anio
                    AND (c.anulado = 0 OR c.anulado IS NULL)
                    AND c.id_tenant = :id_tenant
                GROUP BY 
                    c.id, c.id_producto_servicio, c.id_persona, c.fecha, c.valor, 
                    c.detalle, c.id_usuario, c.anulado,
                    ps.nombre, ps.id_clasificacion_productos_servicios, cps.nombre,
                    p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                    p.numero_identificacion, e.id, eg.id_plan, g.nombre,
                    col.id, ca.nombre
                ORDER BY 
                    c.fecha DESC, p.primer_apellido, p.primer_nombre
            ");

            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log('Error en getReporteCobrosAnual: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al obtener el reporte de cobros',
                'detalles' => $e->getMessage()
            ], 500);
        }
    }


}
