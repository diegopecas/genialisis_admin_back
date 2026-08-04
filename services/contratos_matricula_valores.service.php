<?php
/**
 * Servicio para gestionar los valores detallados de contratos de matrícula
 * Maneja tanto cuotas de matrícula como pensiones mensuales
 */
class ContratosMatriculaValores
{
    /**
     * Obtener todos los valores de un contrato
     */
    public static function getByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cmv.id, cmv.id_contrato_matricula, cmv.id_producto_servicio,
                   cmv.fecha, cmv.valor,
                   ps.nombre AS nombre_producto,
                   ps.id_periodicidad_cobro,
                   pc.nombre AS periodicidad,
                   ps.id_clasificacion_productos_servicios,
                   MONTH(cmv.fecha) AS mes,
                   YEAR(cmv.fecha) AS anio
            FROM contratos_matricula_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
            WHERE cmv.id_contrato_matricula = :id_contrato AND cmv.id_tenant = :id_tenant
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
        PermisosService::validar($userData, 'estudiantes.contratos');

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
            FROM contratos_matricula_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            INNER JOIN periodicidad_cobro pc ON ps.id_periodicidad_cobro = pc.id
            WHERE cmv.id_contrato_matricula = :id_contrato AND cmv.id_tenant = :id_tenant
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
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id_contrato = Flight::request()->data['id_contrato_matricula'];
            $valores = Flight::request()->data['valores']; // Array de valores

            // Eliminar valores existentes
            $sentenceDelete = $db->prepare("DELETE FROM contratos_matricula_valores WHERE id_contrato_matricula = :id_contrato AND id_tenant = :id_tenant");
            $sentenceDelete->bindParam(':id_contrato', $id_contrato);
            $sentenceDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDelete->execute();

            // Insertar nuevos valores
            $sentenceInsert = $db->prepare("
                INSERT INTO contratos_matricula_valores 
                (id_tenant, id_contrato_matricula, id_producto_servicio, fecha, valor) 
                VALUES (:id_tenant, :id_contrato, :id_producto, :fecha, :valor)
            ");
            $sentenceInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $totalMatricula = 0;
            $totalPension = 0;
            $numeroCuotas = 0;

            foreach ($valores as $valor) {
                $sentenceInsert->bindParam(':id_contrato', $id_contrato);
                $sentenceInsert->bindParam(':id_producto', $valor['id_producto_servicio']);
                $sentenceInsert->bindParam(':fecha', $valor['fecha']);
                $sentenceInsert->bindParam(':valor', $valor['valor']);
                $sentenceInsert->execute();

                // Calcular totales según periodicidad (1=Anual/Matrícula, 2=Mensual/Pensión)
                if ($valor['id_periodicidad_cobro'] == 1) {
                    $totalMatricula += $valor['valor'];
                } else if ($valor['id_periodicidad_cobro'] == 2) {
                    $totalPension += $valor['valor'];
                    $numeroCuotas++;
                }
            }

            // Actualizar solo los totales en contratos_matricula (NO las fechas, esas las maneja el usuario)
            $valorTotal = $totalMatricula + $totalPension;
            
            $sentenceUpdate = $db->prepare("
                UPDATE contratos_matricula SET 
                    valor_matricula = :valor_matricula,
                    valor_pension = :valor_pension,
                    numero_cuotas = :numero_cuotas,
                    valor_total = :valor_total
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceUpdate->bindParam(':valor_matricula', $totalMatricula);
            $sentenceUpdate->bindParam(':valor_pension', $totalPension);
            $sentenceUpdate->bindParam(':numero_cuotas', $numeroCuotas);
            $sentenceUpdate->bindParam(':valor_total', $valorTotal);
            $sentenceUpdate->bindParam(':id', $id_contrato);
            $sentenceUpdate->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contrato' => $id_contrato,
                'total_matricula' => $totalMatricula,
                'total_pension' => $totalPension,
                'numero_cuotas' => $numeroCuotas,
                'valor_total' => $valorTotal
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosMatriculaValores::guardarValores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar un valor individual
     */
    public static function actualizarValor()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            
            $id = Flight::request()->data['id'];
            $valor = Flight::request()->data['valor'];

            $sentence = $db->prepare("UPDATE contratos_matricula_valores SET valor = :valor WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':valor', $valor);
            $sentence->execute();

            // Recalcular totales del contrato
            $sentenceContrato = $db->prepare("SELECT id_contrato_matricula FROM contratos_matricula_valores WHERE id = :id AND id_tenant = :id_tenant");
            $sentenceContrato->bindParam(':id', $id);
            $sentenceContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceContrato->execute();
            $row = $sentenceContrato->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                self::recalcularTotalesContrato($db, $row['id_contrato_matricula']);
            }

            Flight::json(array('success' => true, 'id' => $id));
        } catch (Exception $e) {
            error_log("Error en ContratosMatriculaValores::actualizarValor: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Eliminar valores de un contrato
     */
    public static function eliminarByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            
            $sentence = $db->prepare("DELETE FROM contratos_matricula_valores WHERE id_contrato_matricula = :id_contrato AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_contrato', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'id_contrato' => $idContrato));
        } catch (Exception $e) {
            error_log("Error en ContratosMatriculaValores::eliminarByContrato: " . $e->getMessage());
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
                SUM(CASE WHEN ps.id_periodicidad_cobro = 1 THEN cmv.valor ELSE 0 END) AS total_matricula,
                SUM(CASE WHEN ps.id_periodicidad_cobro = 2 THEN cmv.valor ELSE 0 END) AS total_pension,
                COUNT(CASE WHEN ps.id_periodicidad_cobro = 2 THEN 1 END) AS numero_cuotas
            FROM contratos_matricula_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            WHERE cmv.id_contrato_matricula = :id_contrato AND cmv.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $totales = $sentence->fetch(PDO::FETCH_ASSOC);

        if ($totales) {
            $valorTotal = ($totales['total_matricula'] ?? 0) + ($totales['total_pension'] ?? 0);
            
            // Solo actualizar totales, NO las fechas (esas las maneja el usuario)
            $sentenceUpdate = $db->prepare("
                UPDATE contratos_matricula SET 
                    valor_matricula = :valor_matricula,
                    valor_pension = :valor_pension,
                    numero_cuotas = :numero_cuotas,
                    valor_total = :valor_total
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceUpdate->bindParam(':valor_matricula', $totales['total_matricula']);
            $sentenceUpdate->bindParam(':valor_pension', $totales['total_pension']);
            $sentenceUpdate->bindParam(':numero_cuotas', $totales['numero_cuotas']);
            $sentenceUpdate->bindParam(':valor_total', $valorTotal);
            $sentenceUpdate->bindParam(':id', $idContrato);
            $sentenceUpdate->execute();
        }
    }

    /**
     * Generar valores por defecto para un contrato nuevo
     * Basado en las tarifas del grupo y las fechas seleccionadas
     * Acepta valores personalizados de matrícula y pensión (con descuentos/recargos aplicados)
     */
    public static function generarValoresPorDefecto()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $id_grupo = Flight::request()->data['id_grupo'];
            $anio = Flight::request()->data['anio'];
            $fecha_inicio = Flight::request()->data['fecha_inicio'];
            $fecha_fin = Flight::request()->data['fecha_fin'];
            $cuotas_matricula = isset(Flight::request()->data['cuotas_matricula']) ? (int)Flight::request()->data['cuotas_matricula'] : 1;
            
            // Valores personalizados (con descuentos/recargos ya aplicados)
            $valor_matricula_custom = isset(Flight::request()->data['valor_matricula']) ? (float)Flight::request()->data['valor_matricula'] : null;
            $valor_pension_custom = isset(Flight::request()->data['valor_pension']) ? (float)Flight::request()->data['valor_pension'] : null;

            $db = Flight::db();

            // Obtener tarifas del grupo
            $sentenceTarifa = $db->prepare("
                SELECT tg.id_producto_matricula, tg.id_producto_pension,
                       tg.valor_matricula, pm.nombre AS nombre_matricula,
                       tg.valor_pension, pp.nombre AS nombre_pension
                FROM tarifas_grupos tg
                INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
                INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
                WHERE tg.id_grupo = :id_grupo AND tg.anio = :anio AND tg.id_tenant = :id_tenant
            ");
            $sentenceTarifa->bindParam(':id_grupo', $id_grupo);
            $sentenceTarifa->bindParam(':anio', $anio);
            $sentenceTarifa->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceTarifa->execute();
            $tarifa = $sentenceTarifa->fetch(PDO::FETCH_ASSOC);

            if (!$tarifa) {
                Flight::json(array('error' => 'No se encontraron tarifas para el grupo y año especificados'), 404);
                return;
            }

            // Usar valores personalizados si vienen, sino usar los de la tarifa
            $valorMatriculaFinal = ($valor_matricula_custom !== null) ? $valor_matricula_custom : (float)$tarifa['valor_matricula'];
            $valorPensionFinal = ($valor_pension_custom !== null) ? $valor_pension_custom : (float)$tarifa['valor_pension'];

            $valores = [];
            
            // Generar fechas de pensión (un registro por mes)
            $fechaActual = new DateTime($fecha_inicio);
            $fechaLimite = new DateTime($fecha_fin);
            $mesIndex = 0;
            
            // Calcular cuotas de matrícula sin decimales
            $cuotaBaseMatricula = floor($valorMatriculaFinal / $cuotas_matricula);
            $residuoMatricula = $valorMatriculaFinal - ($cuotaBaseMatricula * $cuotas_matricula);

            while ($fechaActual <= $fechaLimite) {
                $fechaPrimeroDeMes = $fechaActual->format('Y-m-01');
                
                // Valor de matrícula (dividido en las primeras N cuotas)
                if ($mesIndex < $cuotas_matricula) {
                    // La primera cuota absorbe el residuo para que sume exacto
                    $valorCuotaMatricula = ($mesIndex == 0) 
                        ? $cuotaBaseMatricula + $residuoMatricula 
                        : $cuotaBaseMatricula;
                    
                    $valores[] = [
                        'id_producto_servicio' => $tarifa['id_producto_matricula'],
                        'nombre_producto' => $tarifa['nombre_matricula'],
                        'fecha' => $fechaPrimeroDeMes,
                        'valor' => (int)$valorCuotaMatricula,
                        'id_periodicidad_cobro' => 1, // Anual (matrícula)
                        'es_matricula' => true
                    ];
                }

                // Valor de pensión (también entero)
                $valores[] = [
                    'id_producto_servicio' => $tarifa['id_producto_pension'],
                    'nombre_producto' => $tarifa['nombre_pension'],
                    'fecha' => $fechaPrimeroDeMes,
                    'valor' => (int)$valorPensionFinal,
                    'id_periodicidad_cobro' => 2, // Mensual (pensión)
                    'es_matricula' => false
                ];

                $fechaActual->modify('+1 month');
                $mesIndex++;
            }

            // Calcular totales usando los valores finales
            $totalMatricula = $valorMatriculaFinal;
            $totalPension = 0;
            $numeroCuotas = 0;
            
            foreach ($valores as $v) {
                if ($v['id_periodicidad_cobro'] == 2) {
                    $totalPension += $v['valor'];
                    $numeroCuotas++;
                }
            }

            Flight::json(array(
                'valores' => $valores,
                'tarifa' => $tarifa,
                'resumen' => [
                    'total_matricula' => $totalMatricula,
                    'total_pension' => $totalPension,
                    'numero_cuotas' => $numeroCuotas,
                    'valor_total' => $totalMatricula + $totalPension
                ]
            ));
        } catch (Exception $e) {
            error_log("Error en ContratosMatriculaValores::generarValoresPorDefecto: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}