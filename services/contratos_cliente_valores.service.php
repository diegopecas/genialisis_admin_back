<?php
/**
 * Servicio para gestionar los valores detallados de contratos de implementación
 * Maneja tanto cuotas de implementación como suscripciones mensuales
 */
class ContratosClienteValores
{
    /**
     * Obtener todos los valores de un contrato
     */
    public static function getByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cmv.id, cmv.id_contrato, cmv.id_producto_servicio,
                   cmv.fecha, cmv.valor,
                   ps.nombre AS nombre_producto,
                   ps.id_periodicidad_cobro,
                   pc.nombre AS periodicidad,
                   ps.id_clasificacion_productos_servicios,
                   MONTH(cmv.fecha) AS mes,
                   YEAR(cmv.fecha) AS anio
            FROM contratos_cliente_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
            WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
            ORDER BY cmv.fecha, ps.id_periodicidad_cobro
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Obtener resumen agrupado por producto
     */
    public static function getResumenByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                ps.id AS id_producto,
                ps.nombre AS nombre_producto,
                ps.id_periodicidad_cobro,
                pc.nombre AS periodicidad,
                COUNT(*) AS cantidad_cuotas,
                SUM(cmv.valor) AS total_producto,
                MIN(cmv.fecha) AS primera_fecha,
                MAX(cmv.fecha) AS ultima_fecha
            FROM contratos_cliente_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
            WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
            GROUP BY ps.id, ps.nombre, ps.id_periodicidad_cobro, pc.nombre
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Guardar todos los valores de un contrato (reemplaza los existentes)
     */
    public static function guardarValores()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id_contrato = Flight::request()->data['id_contrato'];
            $valores = Flight::request()->data['valores']; // Array de valores

            // Eliminar valores existentes
            $sentenceDelete = $db->prepare("DELETE FROM contratos_cliente_valores WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant");
            $sentenceDelete->bindParam(':id_contrato', $id_contrato);
            $sentenceDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDelete->execute();

            // Insertar nuevos valores
            $sentenceInsert = $db->prepare("
                INSERT INTO contratos_cliente_valores 
                (id_tenant, id_contrato, id_producto_servicio, fecha, valor) 
                VALUES (:id_tenant, :id_contrato, :id_producto, :fecha, :valor)
            ");
            $sentenceInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $totalImplementacion = 0;
            $totalSuscripcion = 0;
            $numeroCuotas = 0;

            foreach ($valores as $valor) {
                $sentenceInsert->bindParam(':id_contrato', $id_contrato);
                $sentenceInsert->bindParam(':id_producto', $valor['id_producto_servicio']);
                $sentenceInsert->bindParam(':fecha', $valor['fecha']);
                $sentenceInsert->bindParam(':valor', $valor['valor']);
                $sentenceInsert->execute();

                // Calcular totales según periodicidad (1=Anual/Implementación, 2=Mensual/Suscripción)
                if ($valor['id_periodicidad_cobro'] == 1) {
                    $totalImplementacion += $valor['valor'];
                } else if ($valor['id_periodicidad_cobro'] == 2) {
                    $totalSuscripcion += $valor['valor'];
                    $numeroCuotas++;
                }
            }

            // Actualizar solo los totales en contratos_cliente (NO las fechas, esas las maneja el usuario)
            $valorTotal = $totalImplementacion + $totalSuscripcion;
            
            $sentenceUpdate = $db->prepare("
                UPDATE contratos_cliente SET 
                    valor_implementacion = :valor_implementacion,
                    valor_suscripcion = :valor_suscripcion,
                    numero_cuotas = :numero_cuotas,
                    valor_total = :valor_total
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceUpdate->bindParam(':valor_implementacion', $totalImplementacion);
            $sentenceUpdate->bindParam(':valor_suscripcion', $totalSuscripcion);
            $sentenceUpdate->bindParam(':numero_cuotas', $numeroCuotas);
            $sentenceUpdate->bindParam(':valor_total', $valorTotal);
            $sentenceUpdate->bindParam(':id', $id_contrato);
            $sentenceUpdate->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contrato' => $id_contrato,
                'total_implementacion' => $totalImplementacion,
                'total_suscripcion' => $totalSuscripcion,
                'numero_cuotas' => $numeroCuotas,
                'valor_total' => $valorTotal
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosClienteValores::guardarValores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un valor individual
     */
    public static function actualizarValor()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            
            $id = Flight::request()->data['id'];
            $valor = Flight::request()->data['valor'];

            $sentence = $db->prepare("UPDATE contratos_cliente_valores SET valor = :valor WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':valor', $valor);
            $sentence->execute();

            // Recalcular totales del contrato
            $sentenceContrato = $db->prepare("SELECT id_contrato FROM contratos_cliente_valores WHERE id = :id AND id_tenant = :id_tenant");
            $sentenceContrato->bindParam(':id', $id);
            $sentenceContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceContrato->execute();
            $row = $sentenceContrato->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                self::recalcularTotalesContrato($db, $row['id_contrato']);
            }

            Flight::json(array('success' => true, 'id' => $id));
        } catch (Exception $e) {
            error_log("Error en ContratosClienteValores::actualizarValor: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar valores de un contrato
     */
    public static function eliminarByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            
            $sentence = $db->prepare("DELETE FROM contratos_cliente_valores WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_contrato', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'id_contrato' => $idContrato));
        } catch (Exception $e) {
            error_log("Error en ContratosClienteValores::eliminarByContrato: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Recalcular totales del contrato basado en los valores detallados
     */
    private static function recalcularTotalesContrato($db, $idContrato)
    {
        $sentence = $db->prepare("
            SELECT 
                SUM(CASE WHEN ps.id_periodicidad_cobro = 1 THEN cmv.valor ELSE 0 END) AS total_implementacion,
                SUM(CASE WHEN ps.id_periodicidad_cobro = 2 THEN cmv.valor ELSE 0 END) AS total_suscripcion,
                COUNT(CASE WHEN ps.id_periodicidad_cobro = 2 THEN 1 END) AS numero_cuotas
            FROM contratos_cliente_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $totales = $sentence->fetch(PDO::FETCH_ASSOC);

        if ($totales) {
            $valorTotal = ($totales['total_implementacion'] ?? 0) + ($totales['total_suscripcion'] ?? 0);
            
            // Solo actualizar totales, NO las fechas (esas las maneja el usuario)
            $sentenceUpdate = $db->prepare("
                UPDATE contratos_cliente SET 
                    valor_implementacion = :valor_implementacion,
                    valor_suscripcion = :valor_suscripcion,
                    numero_cuotas = :numero_cuotas,
                    valor_total = :valor_total
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceUpdate->bindParam(':valor_implementacion', $totales['total_implementacion']);
            $sentenceUpdate->bindParam(':valor_suscripcion', $totales['total_suscripcion']);
            $sentenceUpdate->bindParam(':numero_cuotas', $totales['numero_cuotas']);
            $sentenceUpdate->bindParam(':valor_total', $valorTotal);
            $sentenceUpdate->bindParam(':id', $idContrato);
            $sentenceUpdate->execute();
        }
    }

    /**
     * Generar valores por defecto para un contrato nuevo
     * Basado en las tarifas del plan y las fechas seleccionadas
     * Acepta valores personalizados de implementación y suscripción (con descuentos/recargos aplicados)
     */
    public static function generarValoresPorDefecto()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $id_plan = Flight::request()->data['id_plan'];
            $anio = Flight::request()->data['anio'];
            $fecha_inicio = Flight::request()->data['fecha_inicio'];
            $fecha_fin = Flight::request()->data['fecha_fin'];
            $cuotas_implementacion = isset(Flight::request()->data['cuotas_implementacion']) ? (int)Flight::request()->data['cuotas_implementacion'] : 1;
            
            // Valores personalizados (con descuentos/recargos ya aplicados)
            $valor_implementacion_custom = isset(Flight::request()->data['valor_implementacion']) ? (float)Flight::request()->data['valor_implementacion'] : null;
            $valor_suscripcion_custom = isset(Flight::request()->data['valor_suscripcion']) ? (float)Flight::request()->data['valor_suscripcion'] : null;

            $db = Flight::db();

            // Obtener tarifas del plan
            $sentenceTarifa = $db->prepare("
                SELECT tg.id_producto_implementacion, tg.id_producto_suscripcion,
                       tg.valor_implementacion, pm.nombre AS nombre_implementacion,
                       tg.valor_suscripcion, pp.nombre AS nombre_suscripcion
                FROM tarifas_planes tg
                INNER JOIN productos_servicios pm ON tg.id_producto_implementacion = pm.id
                INNER JOIN productos_servicios pp ON tg.id_producto_suscripcion = pp.id
                WHERE tg.id_plan = :id_plan AND tg.anio = :anio AND tg.id_tenant = :id_tenant
            ");
            $sentenceTarifa->bindParam(':id_plan', $id_plan);
            $sentenceTarifa->bindParam(':anio', $anio);
            $sentenceTarifa->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceTarifa->execute();
            $tarifa = $sentenceTarifa->fetch(PDO::FETCH_ASSOC);

            if (!$tarifa) {
                Flight::json(array('error' => 'No se encontraron tarifas para el plan y año especificados'), 404);
                return;
            }

            // Usar valores personalizados si vienen, sino usar los de la tarifa
            $valorImplementacionFinal = ($valor_implementacion_custom !== null) ? $valor_implementacion_custom : (float)$tarifa['valor_implementacion'];
            $valorSuscripcionFinal = ($valor_suscripcion_custom !== null) ? $valor_suscripcion_custom : (float)$tarifa['valor_suscripcion'];

            $valores = [];
            
            // Generar fechas de suscripción (un registro por mes)
            $fechaActual = new DateTime($fecha_inicio);
            $fechaLimite = new DateTime($fecha_fin);
            $mesIndex = 0;
            
            // Calcular cuotas de implementación sin decimales
            $cuotaBaseImplementacion = floor($valorImplementacionFinal / $cuotas_implementacion);
            $residuoImplementacion = $valorImplementacionFinal - ($cuotaBaseImplementacion * $cuotas_implementacion);

            while ($fechaActual <= $fechaLimite) {
                $fechaPrimeroDeMes = $fechaActual->format('Y-m-01');
                
                // Valor de implementación (dividido en las primeras N cuotas)
                if ($mesIndex < $cuotas_implementacion) {
                    // La primera cuota absorbe el residuo para que sume exacto
                    $valorCuotaImplementacion = ($mesIndex == 0) 
                        ? $cuotaBaseImplementacion + $residuoImplementacion 
                        : $cuotaBaseImplementacion;
                    
                    $valores[] = [
                        'id_producto_servicio' => $tarifa['id_producto_implementacion'],
                        'nombre_producto' => $tarifa['nombre_implementacion'],
                        'fecha' => $fechaPrimeroDeMes,
                        'valor' => (int)$valorCuotaImplementacion,
                        'id_periodicidad_cobro' => 1, // Anual (implementación)
                        'es_implementacion' => true
                    ];
                }

                // Valor de suscripción (también entero)
                $valores[] = [
                    'id_producto_servicio' => $tarifa['id_producto_suscripcion'],
                    'nombre_producto' => $tarifa['nombre_suscripcion'],
                    'fecha' => $fechaPrimeroDeMes,
                    'valor' => (int)$valorSuscripcionFinal,
                    'id_periodicidad_cobro' => 2, // Mensual (suscripción)
                    'es_implementacion' => false
                ];

                $fechaActual->modify('+1 month');
                $mesIndex++;
            }

            // Calcular totales usando los valores finales
            $totalImplementacion = $valorImplementacionFinal;
            $totalSuscripcion = 0;
            $numeroCuotas = 0;
            
            foreach ($valores as $v) {
                if ($v['id_periodicidad_cobro'] == 2) {
                    $totalSuscripcion += $v['valor'];
                    $numeroCuotas++;
                }
            }

            Flight::json(array(
                'valores' => $valores,
                'tarifa' => $tarifa,
                'resumen' => [
                    'total_implementacion' => $totalImplementacion,
                    'total_suscripcion' => $totalSuscripcion,
                    'numero_cuotas' => $numeroCuotas,
                    'valor_total' => $totalImplementacion + $totalSuscripcion
                ]
            ));
        } catch (Exception $e) {
            error_log("Error en ContratosClienteValores::generarValoresPorDefecto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}