<?php

class NominasCalculo
{
    /**
     * Calcular nómina (Preview - no guarda en BD)
     */
    public static function calcular()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            
            $id_nomina = $data['id_nomina'];
            $fecha_inicio = $data['fecha_inicio'];
            $fecha_fin = $data['fecha_fin'];
            $colaboradores_input = $data['colaboradores'];
            
            // Obtener configuración del año actual
            $anio = date('Y');
            $configuracion = self::obtenerConfiguracion($db, $anio);
            
            $colaboradores_calculados = [];
            
            foreach ($colaboradores_input as $col_input) {
                $id_colaborador = $col_input['id_colaborador'];
                $dias_trabajados = $col_input['dias_trabajados'];
                
                // Obtener datos del colaborador
                $colaborador = self::obtenerDatosColaborador($db, $id_colaborador);
                
                if (!$colaborador) {
                    continue;
                }
                
                // Calcular conceptos de nómina según tipo de contrato
                $conceptos = [];
                
                if ($colaborador['aplica_nomina'] == 1) {
                    // Contratos con nómina completa
                    $conceptos = self::calcularConceptosNomina(
                        $db,
                        $colaborador,
                        $dias_trabajados,
                        $configuracion,
                        $fecha_inicio,
                        $fecha_fin
                    );
                } else {
                    // Prestación de servicios - solo valor fijo
                    $conceptos = self::calcularPrestacionServicios(
                        $db,
                        $colaborador,
                        $configuracion
                    );
                }
                
                // Obtener productos/servicios vencidos
                $productos_vencidos = self::obtenerProductosVencidos(
                    $db,
                    $colaborador['id_persona'],
                    $fecha_fin
                );
                
                // Obtener préstamos activos
                $prestamos_activos = self::obtenerPrestamosActivos(
                    $db,
                    $id_colaborador
                );
                
                // Calcular totales
                $total_devengado = array_reduce($conceptos, function($sum, $c) {
                    return $sum + ($c['es_suma'] ? $c['valor_total'] : 0);
                }, 0);
                
                $total_deducciones_legales = array_reduce($conceptos, function($sum, $c) {
                    return $sum + (!$c['es_suma'] ? abs($c['valor_total']) : 0);
                }, 0);
                
                $colaboradores_calculados[] = [
                    'id_colaborador' => $id_colaborador,
                    'nombre_completo' => $colaborador['nombre_completo'],
                    'tipo_contrato' => $colaborador['nombre_tipo_contrato'],
                    'salario_mensual' => floatval($colaborador['salario_mensual']),
                    'dias_trabajados' => intval($dias_trabajados),
                    'conceptos' => $conceptos,
                    'productos_vencidos' => $productos_vencidos,
                    'prestamos_activos' => $prestamos_activos,
                    'totales' => [
                        'devengado' => $total_devengado,
                        'deducciones_legales' => $total_deducciones_legales
                    ]
                ];
            }
            
            // Calcular totales generales
            $total_dev = array_reduce($colaboradores_calculados, function($sum, $c) {
                return $sum + $c['totales']['devengado'];
            }, 0);
            
            $total_ded = array_reduce($colaboradores_calculados, function($sum, $c) {
                return $sum + $c['totales']['deducciones_legales'];
            }, 0);
            
            Flight::json([
                'colaboradores' => $colaboradores_calculados,
                'total_colaboradores' => count($colaboradores_calculados),
                'totales' => [
                    'total_devengado' => $total_dev,
                    'total_deducciones' => $total_ded,
                    'neto_total' => $total_dev - $total_ded
                ]
            ]);
            
        } catch (Exception $e) {
            error_log('Error en calcular nómina: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al calcular nómina: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Procesar nómina en firme (guarda en BD)
     */
    public static function procesar()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            
            $id_nomina = $data['id_nomina'];
            $colaboradores_data = $data['colaboradores'];
            
            // Iniciar transacción
            $db->beginTransaction();
            
            $registros_nomina = 0;
            $actividades_contabilizadas = 0;
            $pagos_productos = 0;
            $pagos_prestamos = 0;
            
            foreach ($colaboradores_data as $col_data) {
                $id_colaborador = $col_data['id_colaborador'];
                
                // 1. Insertar conceptos en nominas_detalle
                foreach ($col_data['conceptos'] as $concepto) {
                    $stmt = $db->prepare("
                        INSERT INTO nominas_detalle (
                            id, id_tenant, id_nomina, id_colaborador, id_concepto, 
                            cantidad, valor_unitario, valor_total
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $idDet = Uuid::generar();
                    $stmt->execute([
                        $idDet,
                        TenantContext::id(),
                        $id_nomina,
                        $id_colaborador,
                        $concepto['id_concepto'],
                        $concepto['cantidad'],
                        $concepto['valor_unitario'],
                        $concepto['valor_total']
                    ]);
                    $registros_nomina++;
                }
                
                // 2. Procesar productos/servicios
                if (!empty($col_data['productos_descontar'])) {
                    $resultado_productos = self::procesarProductosServicios(
                        $db,
                        $id_nomina,
                        $id_colaborador,
                        $col_data['productos_descontar']
                    );
                    $pagos_productos += $resultado_productos;
                }
                
                // 3. Procesar préstamos
                if (!empty($col_data['prestamos_descontar'])) {
                    $resultado_prestamos = self::procesarPrestamos(
                        $db,
                        $id_nomina,
                        $id_colaborador,
                        $col_data['prestamos_descontar']
                    );
                    $pagos_prestamos += $resultado_prestamos;
                }
                
                // 4. Actualizar actividades a contabilizadas
                $actividades_contabilizadas += self::contabilizarActividades(
                    $db,
                    $id_colaborador,
                    $id_nomina
                );
            }
            
            // Confirmar transacción
            $db->commit();
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina procesada exitosamente',
                'registros_nomina' => $registros_nomina,
                'actividades_contabilizadas' => $actividades_contabilizadas,
                'pagos_productos' => $pagos_productos,
                'pagos_prestamos' => $pagos_prestamos
            ]);
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en procesar nómina: ' . $e->getMessage());
            Flight::json([
                'error' => true,
                'message' => 'Error al procesar nómina: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // ========================================
    // MÉTODOS AUXILIARES
    // ========================================
    
    private static function obtenerConfiguracion($db, $anio)
    {
        $stmt = $db->prepare("
            SELECT codigo, valor 
            FROM nomina_configuracion 
            WHERE anio = ? AND activo = 1 AND id_tenant = ?
        ");
        $stmt->execute([$anio, TenantContext::id()]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($rows as $row) {
            $config[$row['codigo']] = floatval($row['valor']);
        }
        return $config;
    }
    
    private static function obtenerDatosColaborador($db, $id_colaborador)
    {
        $stmt = $db->prepare("
            SELECT 
                c.*,
                CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) as nombre_completo,
                tc.nombre as nombre_tipo_contrato,
                tc.aplica_nomina
            FROM colaboradores c
            INNER JOIN personas p ON p.id = c.id_persona
            LEFT JOIN tipos_contrato tc ON tc.id = c.id_tipo_contrato
            WHERE c.id = ? AND c.activo = 1 AND c.id_tenant = ?
        ");
        $stmt->execute([$id_colaborador, TenantContext::id()]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private static function calcularConceptosNomina($db, $colaborador, $dias, $config, $fecha_inicio, $fecha_fin)
    {
        $conceptos = [];
        $salario_mensual = floatval($colaborador['salario_mensual']);
        $dias_periodo = $config['DIAS_PERIODO_NOMINA'] ?? 15;
        
        // Calcular salario proporcional
        $salario_base = ($salario_mensual / 30) * $dias;
        
        // DEV001 - Salario
        $conceptos[] = [
            'id_concepto' => 1,
            'codigo' => 'DEV001',
            'nombre' => 'Salario',
            'es_suma' => true,
            'cantidad' => $dias,
            'valor_unitario' => $salario_mensual / 30,
            'valor_total' => $salario_base,
            'editable' => false
        ];
        
        // Obtener horas adicionales del periodo
        $horas_adicionales = self::obtenerHorasAdicionales(
            $db,
            $colaborador['id'],
            $fecha_inicio,
            $fecha_fin
        );
        
        if ($horas_adicionales > 0) {
            // DEV006 - Bonificaciones (todas las horas adicionales)
            $conceptos[] = [
                'id_concepto' => 6,
                'codigo' => 'DEV006',
                'nombre' => 'Bonificaciones',
                'es_suma' => true,
                'cantidad' => 1,
                'valor_unitario' => $horas_adicionales,
                'valor_total' => $horas_adicionales,
                'editable' => true
            ];
        }
        
        // Verificar auxilio de transporte
        $salario_minimo = $config['SALARIO_MINIMO'] ?? 1423500;
        if ($salario_mensual <= ($salario_minimo * 2)) {
            // Verificar si NO tiene vacaciones en el periodo
            $tiene_vacaciones = self::tieneVacaciones($db, $colaborador['id'], $fecha_inicio, $fecha_fin);
            
            if (!$tiene_vacaciones) {
                $auxilio_transporte = ($config['AUXILIO_TRANSPORTE'] ?? 200000) * ($dias / 30);
                
                // DEV005 - Auxilio de Transporte
                $conceptos[] = [
                    'id_concepto' => 5,
                    'codigo' => 'DEV005',
                    'nombre' => 'Auxilio de Transporte',
                    'es_suma' => true,
                    'cantidad' => $dias,
                    'valor_unitario' => $config['AUXILIO_TRANSPORTE'] / 30,
                    'valor_total' => $auxilio_transporte,
                    'editable' => false
                ];
            }
        }
        
        // DED001 - Salud (4%)
        $salud = $salario_base * ($config['SALUD_EMPLEADO'] / 100);
        $conceptos[] = [
            'id_concepto' => 11,
            'codigo' => 'DED001',
            'nombre' => 'Salud',
            'es_suma' => false,
            'cantidad' => 1,
            'valor_unitario' => -$salud,
            'valor_total' => -$salud,
            'editable' => false
        ];
        
        // DED002 - Pensión (4%)
        $pension = $salario_base * ($config['PENSION_EMPLEADO'] / 100);
        $conceptos[] = [
            'id_concepto' => 12,
            'codigo' => 'DED002',
            'nombre' => 'Pensión',
            'es_suma' => false,
            'cantidad' => 1,
            'valor_unitario' => -$pension,
            'valor_total' => -$pension,
            'editable' => false
        ];
        
        return $conceptos;
    }
    
    private static function calcularPrestacionServicios($db, $colaborador, $config)
    {
        $conceptos = [];
        $salario_mensual = floatval($colaborador['salario_mensual']);
        
        // Solo el valor fijo del contrato
        $conceptos[] = [
            'id_concepto' => 1,
            'codigo' => 'DEV001',
            'nombre' => 'Honorarios',
            'es_suma' => true,
            'cantidad' => 1,
            'valor_unitario' => $salario_mensual,
            'valor_total' => $salario_mensual,
            'editable' => false
        ];
        
        return $conceptos;
    }
    
    private static function obtenerHorasAdicionales($db, $id_colaborador, $fecha_inicio, $fecha_fin)
    {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(ac.minutos_totales * tac.valor_hora / 60), 0) as total
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON tac.id = ac.id_tipo_actividad
            INNER JOIN categorias_actividades ca ON ca.id = tac.id_categoria AND ca.id_tenant = tac.id_tenant
            WHERE ac.id_colaborador = ?
              AND ca.codigo = 'HORA_ADICIONAL'
              AND ac.id_estado IN (2, 5)
              AND ac.fecha_hora_inicio >= ?
              AND ac.fecha_hora_fin <= ?
              AND ac.id_tenant = ?
        ");
        $stmt->execute([$id_colaborador, $fecha_inicio, $fecha_fin, TenantContext::id()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($row['total'] ?? 0);
    }
    
    private static function tieneVacaciones($db, $id_colaborador, $fecha_inicio, $fecha_fin)
    {
        $stmt = $db->prepare("
            SELECT COUNT(*) as total
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON tac.id = ac.id_tipo_actividad
            INNER JOIN categorias_actividades ca ON ca.id = tac.id_categoria AND ca.id_tenant = tac.id_tenant
            WHERE ac.id_colaborador = ?
              AND ca.codigo = 'VACACIONES'
              AND ac.id_estado IN (2, 5)
              AND ac.fecha_hora_inicio >= ?
              AND ac.fecha_hora_fin <= ?
              AND ac.id_tenant = ?
        ");
        $stmt->execute([$id_colaborador, $fecha_inicio, $fecha_fin, TenantContext::id()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($row['total']) > 0;
    }
    
    private static function obtenerProductosVencidos($db, $id_persona, $fecha_corte)
    {
        $stmt = $db->prepare("
            SELECT 
                cpc.id,
                ps.nombre as nombre_producto_servicio,
                cpc.fecha,
                cpc.valor,
                cpc.saldo,
                DATE_ADD(cpc.fecha, INTERVAL 30 DAY) as fecha_vencimiento
            FROM cuentas_por_cobrar cpc
            INNER JOIN productos_servicios ps ON ps.id = cpc.id_producto_servicio
            WHERE cpc.id_persona = ?
              AND cpc.saldo > 0
              AND cpc.anulado = 0
              AND DATE_ADD(cpc.fecha, INTERVAL 30 DAY) <= ?
              AND cpc.id_tenant = ?
            ORDER BY cpc.fecha ASC
        ");
        $stmt->execute([$id_persona, $fecha_corte, TenantContext::id()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private static function obtenerPrestamosActivos($db, $id_colaborador)
    {
        $stmt = $db->prepare("
            SELECT 
                pc.id as id_cuota,
                pc.id_prestamo,
                CONCAT(pc.numero_cuota, '/', p.numero_cuotas) as numero_cuota,
                pc.monto_cuota,
                pc.fecha_programada
            FROM prestamos_cuotas pc
            INNER JOIN prestamos p ON p.id = pc.id_prestamo
            WHERE p.id_colaborador = ?
              AND pc.id_estado = 1
              AND p.id_tipo_descuento IN (1, 3)
              AND pc.id_tenant = ?
            ORDER BY pc.fecha_programada ASC
        ");
        $stmt->execute([$id_colaborador, TenantContext::id()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private static function procesarProductosServicios($db, $id_nomina, $id_colaborador, $ids_productos)
    {
        // Obtener id_persona del colaborador
        $stmt = $db->prepare("SELECT id_persona FROM colaboradores WHERE id = ? AND id_tenant = ?");
        $stmt->execute([$id_colaborador, TenantContext::id()]);
        $colaborador = $stmt->fetch(PDO::FETCH_ASSOC);
        $id_persona = $colaborador['id_persona'];
        
        // Calcular total a descontar
        $placeholders = str_repeat('?,', count($ids_productos) - 1) . '?';
        $stmt = $db->prepare("
            SELECT SUM(saldo) as total
            FROM cuentas_por_cobrar
            WHERE id IN ($placeholders)
              AND id_tenant = ?
        ");
        $stmt->execute(array_merge($ids_productos, [TenantContext::id()]));
        $total = floatval($stmt->fetch(PDO::FETCH_ASSOC)['total']);
        
        // Crear pago recibido (tipo 7 = Efectivo - Nómina)
        $stmt = $db->prepare("
            INSERT INTO pagos_recibidos (
                id, id_tenant,
                id_estudiante, id_colaborador, id_acudiente,
                fecha, id_tipo_pago, valor_recibido, 
                observaciones, id_usuario_registro
            ) VALUES (?, ?, NULL, ?, NULL, NOW(), 7, ?, 'Descuento nómina', 1)
        ");
        $idPagoRecibido = Uuid::generar();
        $stmt->execute([$idPagoRecibido, TenantContext::id(), $id_colaborador, $total]);
        $id_pago_recibido = $idPagoRecibido;
        
        // Aplicar pago a cada cuenta
        foreach ($ids_productos as $id_cuenta) {
            // Obtener saldo de la cuenta
            $stmt = $db->prepare("SELECT saldo FROM cuentas_por_cobrar WHERE id = ? AND id_tenant = ?");
            $stmt->execute([$id_cuenta, TenantContext::id()]);
            $saldo = floatval($stmt->fetch(PDO::FETCH_ASSOC)['saldo']);
            
            // Insertar en cuenta_pagada
            $stmt = $db->prepare("
                INSERT INTO cuenta_pagada (
                    id, id_tenant, id_cuenta_por_cobrar, id_pago_recibido, valor_aplicado, fecha
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $idCuentaPagada = Uuid::generar();
            $stmt->execute([$idCuentaPagada, TenantContext::id(), $id_cuenta, $id_pago_recibido, $saldo]);
            
            // Actualizar saldo de la cuenta
            $stmt = $db->prepare("
                UPDATE cuentas_por_cobrar 
                SET saldo = 0, valor_pagado = valor
                WHERE id = ? AND id_tenant = ?
            ");
            $stmt->execute([$id_cuenta, TenantContext::id()]);
        }
        
        // Registrar en nominas_pagos para trazabilidad
        $stmt = $db->prepare("
            INSERT INTO nominas_pagos (
                id, id_tenant, id_nomina, id_colaborador, id_pago_recibido
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $idNominaPago = Uuid::generar();
        $stmt->execute([$idNominaPago, TenantContext::id(), $id_nomina, $id_colaborador, $id_pago_recibido]);
        
        return count($ids_productos);
    }
    
    private static function procesarPrestamos($db, $id_nomina, $id_colaborador, $ids_cuotas)
    {
        foreach ($ids_cuotas as $id_cuota) {
            // Obtener datos de la cuota
            $stmt = $db->prepare("
                SELECT id_prestamo, monto_cuota
                FROM prestamos_cuotas
                WHERE id = ? AND id_tenant = ?
            ");
            $stmt->execute([$id_cuota, TenantContext::id()]);
            $cuota = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Registrar pago
            $stmt = $db->prepare("
                INSERT INTO prestamos_pagos (
                    id, id_tenant, id_prestamo, id_cuota, id_nomina, 
                    fecha_pago, monto_pagado, id_tipo_pago
                ) VALUES (?, ?, ?, ?, ?, NOW(), ?, 1)
            ");
            $idPP = Uuid::generar();
            $stmt->execute([
                $idPP,
                TenantContext::id(),
                $cuota['id_prestamo'],
                $id_cuota,
                $id_nomina,
                $cuota['monto_cuota']
            ]);
            
            // Actualizar estado de la cuota a pagada (2)
            $stmt = $db->prepare("
                UPDATE prestamos_cuotas
                SET id_estado = 2, fecha_pago = NOW()
                WHERE id = ? AND id_tenant = ?
            ");
            $stmt->execute([$id_cuota, TenantContext::id()]);
        }
        
        return count($ids_cuotas);
    }
    
    private static function contabilizarActividades($db, $id_colaborador, $id_nomina)
    {
        // Obtener fechas de la nómina
        $stmt = $db->prepare("
            SELECT fecha_inicio, fecha_fin
            FROM nominas
            WHERE id = ? AND id_tenant = ?
        ");
        $stmt->execute([$id_nomina, TenantContext::id()]);
        $nomina = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Actualizar actividades a estado 4 (Contabilizado)
        $stmt = $db->prepare("
            UPDATE actividades_colaboradores
            SET id_estado = 4
            WHERE id_colaborador = ?
              AND id_estado IN (2, 5)
              AND fecha_hora_inicio >= ?
              AND fecha_hora_fin <= ?
              AND id_tenant = ?
        ");
        $stmt->execute([
            $id_colaborador,
            $nomina['fecha_inicio'],
            $nomina['fecha_fin'],
            TenantContext::id()
        ]);
        
        return $stmt->rowCount();
    }
}