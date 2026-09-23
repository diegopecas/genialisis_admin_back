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
                   COALESCE(cmp.codigo_tipo_cobro, " . TarifasPlanes::sqlCodigoTipoCobro('cl') . ") AS codigo_tipo_cobro,
                   cmp.orden AS orden_producto,
                   MONTH(cmv.fecha) AS mes,
                   YEAR(cmv.fecha) AS anio
            FROM contratos_cliente_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
            LEFT JOIN contratos_cliente_productos cmp
                   ON cmp.id_contrato = cmv.id_contrato
                  AND cmp.id_producto_servicio = cmv.id_producto_servicio
                  AND cmp.id_tenant = cmv.id_tenant
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
            ORDER BY cmv.fecha, cmp.orden, ps.id_periodicidad_cobro
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

            foreach ($valores as $valor) {
                $sentenceInsert->bindParam(':id_contrato', $id_contrato);
                $sentenceInsert->bindParam(':id_producto', $valor['id_producto_servicio']);
                $sentenceInsert->bindParam(':fecha', $valor['fecha']);
                $sentenceInsert->bindParam(':valor', $valor['valor']);
                $sentenceInsert->execute();
            }

            // Los totales de la cabecera se derivan de las lineas del contrato
            // (contratos_cliente_productos). No se tocan las fechas.
            $totales = self::recalcularTotalesContrato($db, $id_contrato);

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contrato' => $id_contrato,
                'total_implementacion' => $totales['total_implementacion'],
                'total_suscripcion' => $totales['total_suscripcion'],
                'total_otros' => $totales['total_otros'],
                'numero_cuotas' => $totales['numero_cuotas'],
                'valor_total' => $totales['valor_total']
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
        // La clasificacion por tipo de tarifa vive en el servicio de las lineas,
        // que es la tabla principal de donde salen los totales derivados.
        return ContratosClienteProductos::recalcularTotalesContrato($db, $idContrato);
    }

    /**
     * Generar valores por defecto para un contrato nuevo.
     * Recorre las lineas del contrato (o, si no vienen, las filas obligatorias
     * de la tarifa del plan) y arma el calendario:
     *   - IMPLEMENTACION: se reparte en las primeras cuotas_implementacion cuotas
     *   - SUSCRIPCION: una cuota por mes
     *   - OTRO: segun la periodicidad del producto (2 Mensual = una por mes,
     *     el resto = una sola cuota en el primer mes)
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
            if ($cuotas_implementacion < 1) {
                $cuotas_implementacion = 1;
            }
            // Dia del mes en que vence cada cuota. Es la fecha que despues se
            // copia a la cuenta por cobrar y contra la que corre la mora.
            // Sin dato explicito se conserva el comportamiento historico: dia 1.
            $dia_vencimiento = isset(Flight::request()->data['dia_vencimiento']) ? (int)Flight::request()->data['dia_vencimiento'] : 1;
            if ($dia_vencimiento < 1 || $dia_vencimiento > 31) {
                $dia_vencimiento = 1;
            }

            // Lineas escogidas en el contrato, con su descuento y recargo ya
            // aplicados en valor_final.
            $lineas = Flight::request()->data['lineas'];
            $lineas = is_array($lineas) ? $lineas : [];

            $db = Flight::db();

            // Filas de la tarifa del plan para el anio
            $sentenceTarifa = $db->prepare("
                SELECT tg.id, tg.id_producto_servicio, tg.valor,
                       tg.obligatorio, tg.orden,
                       ps.nombre AS nombre_producto,
                       ps.id_periodicidad_cobro,
                       " . TarifasPlanes::sqlCodigoTipoCobro('cl') . " AS codigo_tipo_cobro,
                       " . TarifasPlanes::sqlNombreTipoCobro('cl') . " AS nombre_tipo_cobro
                FROM tarifas_planes tg
                INNER JOIN productos_servicios ps ON tg.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
                WHERE tg.id_plan = :id_plan AND tg.anio = :anio AND tg.id_tenant = :id_tenant
                ORDER BY tg.orden
            ");
            $sentenceTarifa->bindParam(':id_plan', $id_plan);
            $sentenceTarifa->bindParam(':anio', $anio);
            $sentenceTarifa->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceTarifa->execute();
            $tarifa = $sentenceTarifa->fetchAll(PDO::FETCH_ASSOC);

            if (empty($tarifa)) {
                Flight::json(array('error' => 'No se encontraron tarifas para el plan y año especificados'), 404);
                return;
            }

            // Indice de la tarifa por producto, para completar los datos que la
            // linea del contrato no traiga.
            $tarifaPorProducto = [];
            foreach ($tarifa as $filaTarifa) {
                $tarifaPorProducto[$filaTarifa['id_producto_servicio']] = $filaTarifa;
            }

            // Sin lineas explicitas se usan las filas obligatorias de la tarifa
            if (empty($lineas)) {
                foreach ($tarifa as $filaTarifa) {
                    if ((int)$filaTarifa['obligatorio'] !== 1) {
                        continue;
                    }
                    $lineas[] = [
                        'id_producto_servicio' => $filaTarifa['id_producto_servicio'],
                        'valor_final' => $filaTarifa['valor'],
                        'orden' => $filaTarifa['orden']
                    ];
                }
            }

            if (empty($lineas)) {
                Flight::json(array('error' => 'La tarifa del plan no tiene productos obligatorios configurados'), 400);
                return;
            }

            // Una linea puede traer un producto que no esta en la tarifa del plan
            // (por ejemplo, uno que se quito de la tarifa despues de firmar). Sus
            // datos de cobro se toman directamente del producto.
            $sentenceProducto = $db->prepare("
                SELECT ps.id AS id_producto_servicio, ps.nombre AS nombre_producto,
                       ps.id_periodicidad_cobro, 1 AS orden,
                       " . TarifasPlanes::sqlCodigoTipoCobro('cl') . " AS codigo_tipo_cobro,
                       " . TarifasPlanes::sqlNombreTipoCobro('cl') . " AS nombre_tipo_cobro
                FROM productos_servicios ps
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
                WHERE ps.id = :id_producto AND ps.id_tenant = :id_tenant
            ");
            foreach ($lineas as $linea) {
                $idProductoLinea = isset($linea['id_producto_servicio']) ? $linea['id_producto_servicio'] : null;
                if (!$idProductoLinea || isset($tarifaPorProducto[$idProductoLinea])) {
                    continue;
                }
                $sentenceProducto->bindValue(':id_producto', $idProductoLinea);
                $sentenceProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceProducto->execute();
                $filaProducto = $sentenceProducto->fetch(PDO::FETCH_ASSOC);
                if ($filaProducto) {
                    $tarifaPorProducto[$idProductoLinea] = $filaProducto;
                }
            }

            // Fechas de vencimiento, una por mes del periodo
            $fechasCuotas = [];
            $fechaActual = new DateTime($fecha_inicio);
            $fechaLimite = new DateTime($fecha_fin);
            while ($fechaActual <= $fechaLimite) {
                // El dia pedido se recorta al ultimo dia del mes cuando no
                // existe (un 31 en febrero cae al 28/29).
                $ultimoDiaDelMes = (int) $fechaActual->format('t');
                $diaEfectivo = min($dia_vencimiento, $ultimoDiaDelMes);
                $fechasCuotas[] = $fechaActual->format('Y-m-') . str_pad($diaEfectivo, 2, '0', STR_PAD_LEFT);
                $fechaActual->modify('+1 month');
            }

            if (empty($fechasCuotas)) {
                Flight::json(array('error' => 'El periodo entre la fecha de inicio y la de fin no tiene meses'), 400);
                return;
            }

            $valores = [];
            $totalImplementacion = 0;
            $totalSuscripcion = 0;
            $totalOtros = 0;
            $numeroCuotas = 0;

            foreach ($lineas as $linea) {
                $idProducto = $linea['id_producto_servicio'];
                $filaTarifa = isset($tarifaPorProducto[$idProducto]) ? $tarifaPorProducto[$idProducto] : null;

                // El tipo de cobro lo manda la clasificacion del producto, no la linea
                $codigoTipo = $filaTarifa ? $filaTarifa['codigo_tipo_cobro'] : 'OTRO';
                $nombreProducto = $filaTarifa ? $filaTarifa['nombre_producto'] : null;
                $periodicidad = $filaTarifa ? (int)$filaTarifa['id_periodicidad_cobro'] : null;
                $orden = isset($linea['orden']) ? (int)$linea['orden'] : ($filaTarifa ? (int)$filaTarifa['orden'] : 1);
                $valorLinea = isset($linea['valor_final']) ? (float)$linea['valor_final'] : 0;

                if ($valorLinea <= 0) {
                    continue;
                }

                if ($codigoTipo === 'IMPLEMENTACION') {
                    // Se reparte en las primeras cuotas, sin decimales.
                    // La primera cuota absorbe el residuo para que sume exacto.
                    $cuotas = min($cuotas_implementacion, count($fechasCuotas));
                    $cuotaBase = floor($valorLinea / $cuotas);
                    $residuo = $valorLinea - ($cuotaBase * $cuotas);

                    for ($i = 0; $i < $cuotas; $i++) {
                        $valorCuota = ($i == 0) ? $cuotaBase + $residuo : $cuotaBase;
                        $valores[] = [
                            'id_producto_servicio' => $idProducto,
                            'nombre_producto' => $nombreProducto,
                            'fecha' => $fechasCuotas[$i],
                            'valor' => (int)$valorCuota,
                            'id_periodicidad_cobro' => $periodicidad,
                            'codigo_tipo_cobro' => $codigoTipo,
                            'orden' => $orden,
                            'es_implementacion' => true
                        ];
                        $totalImplementacion += (int)$valorCuota;
                    }
                } else if ($codigoTipo === 'SUSCRIPCION') {
                    foreach ($fechasCuotas as $fechaCuota) {
                        $valores[] = [
                            'id_producto_servicio' => $idProducto,
                            'nombre_producto' => $nombreProducto,
                            'fecha' => $fechaCuota,
                            'valor' => (int)$valorLinea,
                            'id_periodicidad_cobro' => $periodicidad,
                            'codigo_tipo_cobro' => $codigoTipo,
                            'orden' => $orden,
                            'es_implementacion' => false
                        ];
                        $totalSuscripcion += (int)$valorLinea;
                        $numeroCuotas++;
                    }
                } else {
                    // OTRO: la periodicidad del producto manda.
                    // 2 (Mensual) va mes a mes; el resto queda en una sola cuota.
                    $fechasDelProducto = ($periodicidad === 2) ? $fechasCuotas : [$fechasCuotas[0]];

                    foreach ($fechasDelProducto as $fechaCuota) {
                        $valores[] = [
                            'id_producto_servicio' => $idProducto,
                            'nombre_producto' => $nombreProducto,
                            'fecha' => $fechaCuota,
                            'valor' => (int)$valorLinea,
                            'id_periodicidad_cobro' => $periodicidad,
                            'codigo_tipo_cobro' => $codigoTipo,
                            'orden' => $orden,
                            'es_implementacion' => false
                        ];
                        $totalOtros += (int)$valorLinea;
                    }
                }
            }

            // Se ordena por fecha y luego por el orden de la linea, para que la
            // grilla del contrato salga mes a mes en el mismo orden de la tarifa.
            usort($valores, function ($a, $b) {
                if ($a['fecha'] === $b['fecha']) {
                    return $a['orden'] <=> $b['orden'];
                }
                return strcmp($a['fecha'], $b['fecha']);
            });

            Flight::json(array(
                'valores' => $valores,
                'tarifa' => $tarifa,
                'resumen' => [
                    'total_implementacion' => $totalImplementacion,
                    'total_suscripcion' => $totalSuscripcion,
                    'total_otros' => $totalOtros,
                    'numero_cuotas' => $numeroCuotas,
                    'valor_total' => $totalImplementacion + $totalSuscripcion + $totalOtros
                ]
            ));
        } catch (Exception $e) {
            error_log("Error en ContratosClienteValores::generarValoresPorDefecto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
