<?php
class DashboardGerencial
{
    /**
     * Configura la zona horaria de la sesión a Colombia (UTC-5)
     */
    private static function setTimeZone()
    {
        $db = Flight::db();
        $db->exec("SET time_zone = '-05:00'");
    }

    // =========================================================
    // RESUMEN GENERAL (CONTADORES)
    // =========================================================
    public static function getResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $esHoy = ($fecha === date('Y-m-d'));

            $colaboradores = self::calcularColaboradores($db, $fecha, $esHoy);

            Flight::json([
                'fecha' => $fecha,
                'colaboradores' => $colaboradores
            ]);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // RESÚMENES REUTILIZABLES (contexto para chat IA)
    // Devuelven los mismos arreglos que usan los endpoints, sin JWT ni
    // Flight::json, para que otros servicios (IaChat) los consuman sin
    // duplicar la lógica de cálculo. Los endpoints públicos no cambian.
    // =========================================================

    /**
     * Resumen operativo (colaboradores) para una fecha.
     * Reusa los mismos cálculos privados que getResumen().
     */
    public static function resumenOperativoContexto($db, $fecha)
    {
        self::setTimeZone();
        $esHoy = ($fecha === date('Y-m-d'));

        $colaboradores = self::calcularColaboradores($db, $fecha, $esHoy);

        return [
            'fecha' => $fecha,
            'colaboradores' => $colaboradores
        ];
    }

    /**
     * Resumen financiero del jardín (cartera + recaudo) para una fecha.
     * Reusa los mismos cálculos privados que getCarteraResumen()/getRecaudoResumen().
     */
    public static function resumenFinancieroContexto($db, $fecha)
    {
        self::setTimeZone();

        return [
            'fecha' => $fecha,
            'cartera' => self::calcularCartera($db, $fecha),
            'recaudo' => self::calcularRecaudo($db, $fecha)
        ];
    }

    /**
     * Detalle operativo (colaboradores) para el contexto del chat.
     * Reusa los mismos métodos de detalle que los endpoints, sin duplicar SQL.
     */
    public static function detalleOperativoContexto($db, $fecha)
    {
        self::setTimeZone();
        return [
            'fecha' => $fecha,
            'colaboradores' => self::detalleColaboradores($db, $fecha)
        ];
    }

    /**
     * Detalle financiero (cartera + recaudo + movimientos) para el contexto del chat.
     * El rango temporal de recaudo/movimientos se deriva de la fecha (mes/año).
     */
    public static function detalleFinancieroContexto($db, $fecha)
    {
        self::setTimeZone();
        return [
            'fecha' => $fecha,
            'cartera' => self::detalleCartera($db),
            'recaudo' => self::detalleRecaudo($db, $fecha, 'mes'),
            'movimientos' => self::detalleMovimientos($db, $fecha, 'mes')
        ];
    }

    public static function getColaboradoresDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            Flight::json(self::detalleColaboradores($db, $fecha));
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function detalleColaboradores($db, $fecha)
    {
            $esHoy = ($fecha === date('Y-m-d'));

            // Día de la semana ISO (1=Lunes ... 7=Domingo) — coincide con dias_semana.id
            $diaSemanaNumero = (int)date('N', strtotime($fecha));

            $sql = "SELECT 
                    c.id AS id_colaborador,
                    c.sobrenombre,
                    c.valida_ingreso_jornada,
                    c.valida_ingreso_descanso,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_completo,
                    car.nombre AS nombre_cargo,
                    hc.hora_entrada AS hora_entrada_esperada,
                    hc.hora_salida AS hora_salida_esperada,
                    hc.hora_inicio_descanso AS hora_inicio_descanso_esperada,
                    hc.hora_fin_descanso AS hora_fin_descanso_esperada,
                    -- Registros del día
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha
                          AND tra.codigo = 'jornada_entrada'
                        ORDER BY ra.hora_registro ASC LIMIT 1) AS hora_entrada,
                    (SELECT era.codigo
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_1
                          AND tra.codigo = 'jornada_entrada'
                        ORDER BY ra.hora_registro ASC LIMIT 1) AS estado_entrada,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_2
                          AND tra.codigo = 'descanso_salida'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_inicio_descanso,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_3
                          AND tra.codigo = 'descanso_regreso'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_fin_descanso,
                    (SELECT TIME_FORMAT(ra.hora_registro, '%H:%i')
                        FROM registros_asistencia_colaboradores ra
                        INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
                        WHERE ra.id_colaborador = c.id AND ra.fecha = :fecha_4
                          AND tra.codigo = 'jornada_salida'
                        ORDER BY ra.hora_registro DESC LIMIT 1) AS hora_salida
                FROM colaboradores c
                INNER JOIN personas p ON c.id_persona = p.id
                LEFT JOIN cargos car ON c.id_cargo = car.id
                LEFT JOIN horarios_colaboradores hc 
                    ON hc.id_colaborador = c.id 
                    AND hc.dia_semana = :dia_semana
                    AND hc.activo = 1
                WHERE c.activo = 1
                  AND c.valida_ingreso_jornada = 1
                  AND c.id_tenant = " . TenantContext::id() . "
                ORDER BY p.primer_nombre, p.primer_apellido";

            $sentence = $db->prepare($sql);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':fecha_1', $fecha);
            $sentence->bindParam(':fecha_2', $fecha);
            $sentence->bindParam(':fecha_3', $fecha);
            $sentence->bindParam(':fecha_4', $fecha);
            $sentence->bindParam(':dia_semana', $diaSemanaNumero);
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Calcular estado final en PHP (más claro que anidar otro CASE enorme)
            foreach ($registros as &$r) {
                $r['estado'] = self::calcularEstadoColaborador($r, $esHoy);
                $r['entrada_tarde'] = ($r['estado_entrada'] === 'tarde') ? 1 : 0;
                $r['nombre_cargo'] = $r['nombre_cargo'] ?: 'Sin cargo';
            }
            unset($r);

            return [
                'fecha' => $fecha,
                'total' => count($registros),
                'registros' => $registros
            ];
    }

    // =========================================================
    // HELPERS
    // =========================================================

    private static function calcularColaboradores($db, $fecha, $esHoy)
    {
        // Total colaboradores activos (solo los que deben validar ingreso de jornada)
        $sqlTotal = "SELECT COUNT(*) AS total
            FROM colaboradores c
            WHERE c.activo = 1 AND c.valida_ingreso_jornada = 1 AND c.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlTotal);
        $stmt->execute();
        $totalRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalActivos = (int)$totalRow['total'];

        // Agregados del día
        $sqlAg = "SELECT 
                -- Colaboradores que registraron entrada de jornada
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_entrada' THEN ra.id_colaborador END) AS ingresaron,
                -- Colaboradores que registraron salida de jornada
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_salida' THEN ra.id_colaborador END) AS salieron,
                -- Entradas tarde
                COUNT(DISTINCT CASE WHEN tra.codigo = 'jornada_entrada' AND era.codigo = 'tarde' THEN ra.id_colaborador END) AS tarde,
                -- Descansos iniciados
                COUNT(DISTINCT CASE WHEN tra.codigo = 'descanso_salida' THEN ra.id_colaborador END) AS descansos_iniciados,
                -- Descansos terminados
                COUNT(DISTINCT CASE WHEN tra.codigo = 'descanso_regreso' THEN ra.id_colaborador END) AS descansos_terminados
            FROM registros_asistencia_colaboradores ra
            INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            INNER JOIN colaboradores c ON ra.id_colaborador = c.id
            WHERE ra.fecha = :fecha AND c.activo = 1 AND ra.id_tenant = " . TenantContext::id();
        $s = $db->prepare($sqlAg);
        $s->bindParam(':fecha', $fecha);
        $s->execute();
        $ag = $s->fetch(PDO::FETCH_ASSOC);

        $ingresaron = (int)$ag['ingresaron'];
        $salieron = (int)$ag['salieron'];
        $tarde = (int)$ag['tarde'];
        $enDescanso = max(0, (int)$ag['descansos_iniciados'] - (int)$ag['descansos_terminados']);

        // "Presentes ahora" (solo tiene sentido en el día actual):
        // = ingresaron - salieron
        $presentes = max(0, $ingresaron - $salieron);
        $noIngresaron = max(0, $totalActivos - $ingresaron);

        // Porcentaje de presentes según el día:
        // - hoy: presentes ahora / total activos
        // - pasado: ingresaron / total activos (histórico)
        $base = $esHoy ? $presentes : $ingresaron;
        $porcentaje = $totalActivos > 0 ? round(($base / $totalActivos) * 100, 2) : 0;

        return [
            'total_activos' => $totalActivos,
            'presentes' => $presentes,
            'ingresaron' => $ingresaron,
            'salieron' => $salieron,
            'en_descanso' => $enDescanso,
            'tarde' => $tarde,
            'no_ingresaron' => $noIngresaron,
            'porcentaje' => $porcentaje,
            'es_hoy' => $esHoy
        ];
    }

    /**
     * Calcula los KPIs de alimentación separando mensuales y diarios.
     * Por cada horario_alimentacion devuelve:
     *   - mensuales_servidos / mensuales_contratados (con %)
     *   - diarios_servidos
     * Mensuales: tienen contrato del mes → tienen denominador (contratados)
     * Diarios: solo existe el registro si se consumió → no tienen denominador útil
     */
    private static function calcularEstadoColaborador($r, $esHoy)
    {
        $entro = !empty($r['hora_entrada']);
        $salio = !empty($r['hora_salida']);
        $inicioDesc = !empty($r['hora_inicio_descanso']);
        $finDesc = !empty($r['hora_fin_descanso']);

        if (!$esHoy) {
            if (!$entro) return 'No marcó';
            if ($salio) return 'Salió';
            return 'Marcó entrada';
        }

        // Día actual
        if (!$entro) return 'No marcó';
        if ($salio) return 'Salió';
        if ($inicioDesc && !$finDesc) return 'En descanso';
        return 'En jornada';
    }

    // =========================================================
    // CARTERA (Financiero)
    // =========================================================
    public static function getCarteraResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularCartera($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // =========================================================
    // RECAUDO (Financiero)
    // =========================================================
    public static function getRecaudoResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularRecaudo($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista de pagos para el detalle de recaudo.
     * Params:
     *   - fecha: fecha global del dashboard
     *   - rango: 'hoy' | 'mes' | 'anio' (default: mes)
     */
    public static function getRecaudoDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes';

            Flight::json(self::detalleRecaudo($db, $fecha, $rango));
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function detalleRecaudo($db, $fecha, $rango)
    {

            $fechaObj = new DateTime($fecha);
            $anio = $fechaObj->format('Y');
            $mes = $fechaObj->format('m');

            // Construir filtro temporal según rango
            $whereFecha = '';
            $params = [];
            switch ($rango) {
                case 'hoy':
                    $whereFecha = "AND pr.fecha = :fecha";
                    $params[':fecha'] = $fecha;
                    break;
                case 'anio':
                    $whereFecha = "AND YEAR(pr.fecha) = :anio";
                    $params[':anio'] = $anio;
                    break;
                case 'mes':
                default:
                    $whereFecha = "AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes";
                    $params[':anio'] = $anio;
                    $params[':mes'] = $mes;
                    break;
            }

            $sql = "SELECT 
                    pr.id,
                    pr.fecha,
                    pr.valor_recibido,
                    pr.referencia_bancaria,
                    pr.observaciones,
                    pr.id_estudiante,
                    pr.id_colaborador,
                    pr.id_acudiente,
                    tp.id AS id_tipo_pago,
                    tp.nombre AS tipo_pago,
                    CASE
                        WHEN pr.id_estudiante IS NOT NULL THEN 'Estudiante'
                        WHEN pr.id_colaborador IS NOT NULL THEN 'Colaborador'
                        WHEN pr.id_acudiente IS NOT NULL THEN 'Acudiente'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona
                FROM pagos_recibidos pr
                INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
                LEFT JOIN estudiantes e ON pr.id_estudiante = e.id
                LEFT JOIN colaboradores c ON pr.id_colaborador = c.id
                LEFT JOIN acudientes a ON pr.id_acudiente = a.id
                LEFT JOIN personas p ON p.id = COALESCE(e.id_persona, c.id_persona, a.id_persona)
                WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
                  AND pr.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                ORDER BY pr.fecha DESC, pr.id DESC";

            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($registros as &$r) {
                $r['valor_recibido'] = (float)$r['valor_recibido'];
                $r['nombre_persona'] = $r['nombre_persona'] ?: 'Sin nombre';
            }
            unset($r);

            // Resumen por tipo de pago (mismo rango que la lista)
            $sqlResumen = "SELECT 
                    tp.id AS id_tipo_pago,
                    tp.nombre AS tipo_pago,
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(pr.valor_recibido), 0) AS total
                FROM pagos_recibidos pr
                INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
                WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
                  AND pr.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                GROUP BY tp.id, tp.nombre
                ORDER BY total DESC";
            $stmtR = $db->prepare($sqlResumen);
            foreach ($params as $k => $v) $stmtR->bindValue($k, $v);
            $stmtR->execute();
            $resumenTipos = $stmtR->fetchAll(PDO::FETCH_ASSOC);
            foreach ($resumenTipos as &$t) {
                $t['cantidad'] = (int)$t['cantidad'];
                $t['total'] = (float)$t['total'];
            }
            unset($t);

            // También devolver lista de tipos de pago (para el filtro del front)
            $sqlTipos = "SELECT id, nombre FROM tipos_pagos WHERE es_ingreso = 1 AND id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtT = $db->prepare($sqlTipos);
            $stmtT->execute();
            $tipos = $stmtT->fetchAll(PDO::FETCH_ASSOC);

            return [
                'fecha' => $fecha,
                'rango' => $rango,
                'total' => count($registros),
                'registros' => $registros,
                'resumen_tipos' => $resumenTipos,
                'tipos_pago' => $tipos
            ];
    }

    /**
     * Lista de personas con saldo pendiente. Trae a TODOS (sin limit por defecto).
     * Incluye estado activo/inactivo del estudiante o colaborador.
     */
    public static function getCarteraDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            Flight::json(self::detalleCartera($db));
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function detalleCartera($db)
    {
            $sql = "SELECT 
                    p.id AS id_persona,
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)) AS nombre_persona,
                    p.numero_identificacion,
                    CASE
                        WHEN e.id IS NOT NULL THEN 'Estudiante'
                        WHEN col.id IS NOT NULL THEN 'Colaborador'
                        ELSE 'Otro'
                    END AS tipo_persona,
                    CASE
                        WHEN e.id IS NOT NULL THEN COALESCE(g.nombre, 'Sin grupo')
                        WHEN col.id IS NOT NULL THEN COALESCE(ca.nombre, 'Sin cargo')
                        ELSE 'Sin asignar'
                    END AS grupo_o_cargo,
                    CASE
                        WHEN e.id IS NOT NULL THEN COALESCE(e.activo, 0)
                        WHEN col.id IS NOT NULL THEN COALESCE(col.activo, 0)
                        ELSE 0
                    END AS activo,
                    e.id AS id_estudiante,
                    col.id AS id_colaborador,
                    COUNT(DISTINCT sub.id) AS cuentas_pendientes,
                    SUM(CASE WHEN sub.fecha < CURDATE() THEN 1 ELSE 0 END) AS cuentas_vencidas,
                    SUM(sub.saldo) AS saldo_pendiente,
                    SUM(CASE WHEN sub.fecha < CURDATE() THEN sub.saldo ELSE 0 END) AS saldo_vencido,
                    MAX(CASE WHEN sub.fecha < CURDATE() THEN DATEDIFF(CURDATE(), sub.fecha) ELSE 0 END) AS dias_max_vencido
                FROM personas p
                LEFT JOIN estudiantes e ON e.id_persona = p.id
                LEFT JOIN estudiantes_x_grupos eg ON eg.id_estudiante = e.id AND eg.activo = 1
                LEFT JOIN grupos g ON g.id = eg.id_grupo
                LEFT JOIN colaboradores col ON col.id_persona = p.id
                LEFT JOIN cargos ca ON ca.id = col.id_cargo
                INNER JOIN (
                    SELECT 
                        cpc.id,
                        cpc.id_persona,
                        cpc.fecha,
                        cpc.valor - COALESCE(SUM(
                            CASE WHEN pr.anulado = 0 OR pr.anulado IS NULL THEN cp.valor_aplicado ELSE 0 END
                        ), 0) AS saldo
                    FROM cuentas_por_cobrar cpc
                    LEFT JOIN cuenta_pagada cp ON cp.id_cuenta_por_cobrar = cpc.id
                    LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                    WHERE (cpc.anulado = 0 OR cpc.anulado IS NULL)
                    AND cpc.id_tenant = " . TenantContext::id() . "
                    GROUP BY cpc.id, cpc.id_persona, cpc.fecha, cpc.valor
                    HAVING saldo > 0
                ) sub ON sub.id_persona = p.id
                WHERE p.id_tenant = " . TenantContext::id() . "
                GROUP BY p.id, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido,
                         p.numero_identificacion, e.id, e.activo, g.nombre, col.id, col.activo, ca.nombre
                ORDER BY saldo_vencido DESC, saldo_pendiente DESC";

            $sentence = $db->prepare($sql);
            $sentence->execute();
            $registros = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // IDs de estudiantes morosos para buscar sus recordatorios
            $idsEstudiantes = [];
            foreach ($registros as $r) {
                if (!empty($r['id_estudiante'])) {
                    $idsEstudiantes[] = $r['id_estudiante'];
                }
            }

            // Mapa: id_estudiante => ultimo recordatorio de pago
            $recordatorios = [];
            if (count($idsEstudiantes) > 0) {
                $placeholders = implode(',', array_fill(0, count($idsEstudiantes), '?'));

                // Último recordatorio de pago por estudiante
                $sqlPago = "SELECT hp.id_estudiante, hp.tipo_recordatorio, hp.compromiso,
                                   hp.fecha_compromiso, hp.fecha_envio
                            FROM historial_recordatorios_pago hp
                            INNER JOIN (
                                SELECT id_estudiante, MAX(fecha_envio) AS max_fecha
                                FROM historial_recordatorios_pago
                                WHERE id_estudiante IN ($placeholders)
                                GROUP BY id_estudiante
                            ) ult ON hp.id_estudiante = ult.id_estudiante 
                                 AND hp.fecha_envio = ult.max_fecha";
                $stmtP = $db->prepare($sqlPago);
                $stmtP->execute($idsEstudiantes);
                foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $recordatorios[$row['id_estudiante']] = [
                        'origen' => 'pago',
                        'tipo' => $row['tipo_recordatorio'],
                        'compromiso' => $row['compromiso'],
                        'fecha_compromiso' => $row['fecha_compromiso'],
                        'fecha_envio' => $row['fecha_envio']
                    ];
                }
            }

            foreach ($registros as &$r) {
                $r['saldo_pendiente'] = (float)$r['saldo_pendiente'];
                $r['saldo_vencido'] = (float)$r['saldo_vencido'];
                $r['cuentas_pendientes'] = (int)$r['cuentas_pendientes'];
                $r['cuentas_vencidas'] = (int)$r['cuentas_vencidas'];
                $r['dias_max_vencido'] = (int)$r['dias_max_vencido'];
                $r['activo'] = (int)$r['activo'];

                // Adjuntar último recordatorio si existe
                $idEst = !empty($r['id_estudiante']) ? $r['id_estudiante'] : null;
                if ($idEst !== null && isset($recordatorios[$idEst])) {
                    $rec = $recordatorios[$idEst];
                    $r['recordatorio'] = [
                        'origen' => $rec['origen'],
                        'tipo' => $rec['tipo'],
                        'fecha_envio' => $rec['fecha_envio'],
                        'compromiso' => $rec['compromiso'],
                        'fecha_compromiso' => $rec['fecha_compromiso']
                    ];
                } else {
                    $r['recordatorio'] = null;
                }
            }
            unset($r);

            return [
                'total' => count($registros),
                'registros' => $registros
            ];
    }

    /**
     * Resumen de cartera:
     *   - saldo_pendiente, saldo_vencido (fecha < fecha global), %vencido
     *   - Saldo cartera mes / meses anteriores
     *   - Saldo por tipo de persona: estudiantes / colaboradores
     *   - Buckets de antigüedad: por vencer, 1-30, 31-60, 61-90, +90 días
     */
    private static function calcularCartera($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $primerDiaMes = $fechaObj->format('Y-m-01');
        // Fecha del día anterior para calcular delta
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // ------- 1) Estado actual de toda la cartera -------
        // Vencido = saldo > 0 AND c.fecha < fecha global (cualquier día anterior)
        $sqlEstado = "SELECT 
                SUM(c.valor) AS total_facturado,
                SUM(COALESCE(cp_sum.total_pagado, 0)) AS total_recaudado,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND c.fecha < :fecha_ref 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_vencido,

                -- Buckets de antigüedad (saldo > 0 únicamente)
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND c.fecha >= :f_por_vencer 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_por_vencer,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_30a, c.fecha) BETWEEN 1 AND 30 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_1_30,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_60a, c.fecha) BETWEEN 31 AND 60 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_31_60,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_90a, c.fecha) BETWEEN 61 AND 90 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_61_90,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                     AND DATEDIFF(:f_91a, c.fecha) > 90 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0) ELSE 0 
                END) AS saldo_mas_90
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL) AND c.id_tenant = " . TenantContext::id();

        $stmt = $db->prepare($sqlEstado);
        $stmt->bindParam(':fecha_ref', $fecha);
        $stmt->bindParam(':f_por_vencer', $fecha);
        $stmt->bindParam(':f_30a', $fecha);
        $stmt->bindParam(':f_60a', $fecha);
        $stmt->bindParam(':f_90a', $fecha);
        $stmt->bindParam(':f_91a', $fecha);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $saldoPendiente = (float)$row['saldo_pendiente'];
        $saldoVencido = (float)$row['saldo_vencido'];
        $porcentajeVencido = $saldoPendiente > 0
            ? round(($saldoVencido / $saldoPendiente) * 100, 2)
            : 0;

        // ------- Saldo vencido y pendiente de AYER (para delta) -------
        $sqlAyer = "SELECT 
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_pendiente,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 AND c.fecha < :fecha_ayer 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo_vencido
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                  AND (pr.fecha <= :fecha_ayer2)
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL)
              AND c.fecha <= :fecha_ayer3 AND c.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAyer);
        $stmt->bindParam(':fecha_ayer', $fechaAyer);
        $stmt->bindParam(':fecha_ayer2', $fechaAyer);
        $stmt->bindParam(':fecha_ayer3', $fechaAyer);
        $stmt->execute();
        $rowAyer = $stmt->fetch(PDO::FETCH_ASSOC);

        $saldoVencidoAyer = (float)$rowAyer['saldo_vencido'];
        $deltaVencido = $saldoVencido - $saldoVencidoAyer;

        // Helper inline para % de cada bucket
        $pct = function ($v) use ($saldoPendiente) {
            return $saldoPendiente > 0 ? round(($v / $saldoPendiente) * 100, 2) : 0;
        };

        $buckets = [
            'por_vencer' => ['saldo' => (float)$row['saldo_por_vencer'], 'porcentaje' => $pct((float)$row['saldo_por_vencer'])],
            'd_1_30'     => ['saldo' => (float)$row['saldo_1_30'],     'porcentaje' => $pct((float)$row['saldo_1_30'])],
            'd_31_60'    => ['saldo' => (float)$row['saldo_31_60'],    'porcentaje' => $pct((float)$row['saldo_31_60'])],
            'd_61_90'    => ['saldo' => (float)$row['saldo_61_90'],    'porcentaje' => $pct((float)$row['saldo_61_90'])],
            'mas_90'     => ['saldo' => (float)$row['saldo_mas_90'],   'porcentaje' => $pct((float)$row['saldo_mas_90'])]
        ];

        // ------- 2) Saldo de cuentas del mes actual (solo del mes, NO futuros) -------
        $saldoMesActual = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND MONTH(c.fecha) = :mes",
            [':anio' => $anio, ':mes' => $mes]
        );

        // ------- 3) Saldo de cuentas de meses anteriores (solo del año global) -------
        $saldoMesesAnteriores = self::calcularSaldoCarteraBloque(
            $db,
            "c.fecha < :primer_dia AND YEAR(c.fecha) = :anio",
            [':primer_dia' => $primerDiaMes, ':anio' => $anio]
        );

        // ------- 4) Saldo por tipo de persona (solo del año global) -------
        $saldoEstudiantes = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND EXISTS (SELECT 1 FROM estudiantes e WHERE e.id_persona = c.id_persona)",
            [':anio' => $anio]
        );

        $saldoColaboradores = self::calcularSaldoCarteraBloque(
            $db,
            "YEAR(c.fecha) = :anio AND EXISTS (SELECT 1 FROM colaboradores col WHERE col.id_persona = c.id_persona)",
            [':anio' => $anio]
        );

        // ------- 5) Cuentas anuladas este mes -------
        $sqlAnuladasMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(valor), 0) AS total
            FROM cuentas_por_cobrar 
            WHERE anulado = 1 
              AND YEAR(fecha_anulacion) = :anio 
              AND MONTH(fecha_anulacion) = :mes AND id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnuladasMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $anuladasMes = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'fecha' => $fecha,
            'total_facturado' => (float)$row['total_facturado'],
            'total_recaudado' => (float)$row['total_recaudado'],
            'saldo_pendiente' => $saldoPendiente,
            'saldo_vencido' => $saldoVencido,
            'porcentaje_vencido' => $porcentajeVencido,
            'saldo_vencido_ayer' => $saldoVencidoAyer,
            'delta_vencido' => $deltaVencido,
            'saldo_mes_actual' => $saldoMesActual,
            'saldo_meses_anteriores' => $saldoMesesAnteriores,
            'saldo_estudiantes' => $saldoEstudiantes,
            'saldo_colaboradores' => $saldoColaboradores,
            'cuentas_anuladas_mes' => [
                'cantidad' => (int)$anuladasMes['cantidad'],
                'total' => (float)$anuladasMes['total']
            ],
            'buckets' => $buckets
        ];
    }

    /**
     * Helper: saldo pendiente de CPC filtradas por una condición arbitraria.
     */
    private static function calcularSaldoCarteraBloque($db, $whereExtra, $params)
    {
        $sql = "SELECT 
                COUNT(DISTINCT c.id) AS total_cuentas,
                SUM(CASE 
                    WHEN c.valor - COALESCE(cp_sum.total_pagado, 0) > 0 
                    THEN c.valor - COALESCE(cp_sum.total_pagado, 0)
                    ELSE 0 
                END) AS saldo
            FROM cuentas_por_cobrar c
            LEFT JOIN (
                SELECT 
                    cp.id_cuenta_por_cobrar,
                    SUM(CASE 
                        WHEN pr.anulado = 0 OR pr.anulado IS NULL 
                        THEN cp.valor_aplicado 
                        ELSE 0 
                    END) AS total_pagado
                FROM cuenta_pagada cp
                LEFT JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                GROUP BY cp.id_cuenta_por_cobrar
            ) cp_sum ON c.id = cp_sum.id_cuenta_por_cobrar
            WHERE (c.anulado = 0 OR c.anulado IS NULL) AND $whereExtra AND c.id_tenant = " . TenantContext::id();

        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        $r = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_cuentas' => (int)$r['total_cuentas'],
            'saldo' => (float)$r['saldo']
        ];
    }

    /**
     * Resumen de recaudo (movimientos):
     *   Datos grandes:
     *     - recaudado_mes: pagos con pr.fecha en el mes de la fecha global
     *     - recaudado_anio: pagos con pr.fecha en el año de la fecha global
     *   Sub-bloques:
     *     - recaudado_hoy: pr.fecha = fecha global
     *     - recaudado_mes_corriente: pagos del mes aplicados a CPC con fecha >= primer día del mes
     *     - recaudado_mes_anteriores: pagos del mes aplicados a CPC con fecha < primer día del mes
     *   Por tipo de persona:
     *     - mes_estudiantes / mes_colaboradores
     *
     * Filtros base: pr.anulado=0 AND tp.es_ingreso=1
     */
    private static function calcularRecaudo($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $primerDiaMes = $fechaObj->format('Y-m-01');
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // Recaudado hoy
        $sqlHoy = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) AND pr.fecha = :fecha AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlHoy);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        $recaudadoHoy = $stmt->fetch(PDO::FETCH_ASSOC);

        // Registrado hoy (pagos digitados hoy, sin importar la fecha del comprobante)
        $sqlRegHoy = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) AND DATE(pr.fecha_registro) = :fecha AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlRegHoy);
        $stmt->bindParam(':fecha', $fecha);
        $stmt->execute();
        $registradoHoy = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado en el mes
        $sqlMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $recaudadoMes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado en el mes hasta AYER (para delta)
        $sqlMesAyer = "SELECT 
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.fecha <= :fecha_ayer AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesAyer);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':fecha_ayer', $fechaAyer);
        $stmt->execute();
        $recaudadoMesAyer = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalRecaudadoMesAyer = (float)$recaudadoMesAyer['total'];
        $deltaRecaudadoMes = (float)$recaudadoMes['total'] - $totalRecaudadoMesAyer;

        // Recaudado en el año
        $sqlAnio = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnio);
        $stmt->bindParam(':anio', $anio);
        $stmt->execute();
        $recaudadoAnio = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes aplicado a CPC con fecha >= primer día del mes
        // (pagos del mes que cubren cuentas del mes corriente o futuras)
        $sqlMesCorriente = "SELECT 
                COALESCE(SUM(cp.valor_aplicado), 0) AS total
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            INNER JOIN cuentas_por_cobrar c ON cp.id_cuenta_por_cobrar = c.id
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND c.fecha >= :primer_dia
              AND (c.anulado = 0 OR c.anulado IS NULL) AND cp.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesCorriente);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':primer_dia', $primerDiaMes);
        $stmt->execute();
        $mesCorriente = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes aplicado a CPC con fecha < primer día del mes
        // (pagos del mes que cubren cartera vieja)
        $sqlMesAnteriores = "SELECT 
                COALESCE(SUM(cp.valor_aplicado), 0) AS total
            FROM cuenta_pagada cp
            INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            INNER JOIN cuentas_por_cobrar c ON cp.id_cuenta_por_cobrar = c.id
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL)
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND c.fecha < :primer_dia
              AND (c.anulado = 0 OR c.anulado IS NULL) AND cp.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesAnteriores);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->bindParam(':primer_dia', $primerDiaMes);
        $stmt->execute();
        $mesAnteriores = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado mes por tipo de persona: estudiantes
        $sqlMesEst = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_estudiante IS NOT NULL AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesEst);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $mesEstudiantes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado mes por tipo de persona: colaboradores
        $sqlMesCol = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_colaborador IS NOT NULL AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlMesCol);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $mesColaboradores = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pagos anulados este mes (por fecha_anulacion)
        $sqlAnulMes = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE pr.anulado = 1
              AND YEAR(pr.fecha_anulacion) = :anio 
              AND MONTH(pr.fecha_anulacion) = :mes AND pr.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlAnulMes);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $anuladosMes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Recaudado del mes por tipo de pago
        $sqlPorTipo = "SELECT 
                tp.id AS id_tipo_pago,
                tp.nombre AS tipo_pago,
                COUNT(*) AS cantidad,
                COALESCE(SUM(pr.valor_recibido), 0) AS total
            FROM pagos_recibidos pr
            INNER JOIN tipos_pagos tp ON pr.id_tipo_pago = tp.id AND tp.es_ingreso = 1
            WHERE (pr.anulado = 0 OR pr.anulado IS NULL) 
              AND YEAR(pr.fecha) = :anio AND MONTH(pr.fecha) = :mes
              AND pr.id_tenant = " . TenantContext::id() . "
            GROUP BY tp.id, tp.nombre
            ORDER BY total DESC";
        $stmt = $db->prepare($sqlPorTipo);
        $stmt->bindParam(':anio', $anio);
        $stmt->bindParam(':mes', $mes);
        $stmt->execute();
        $porTipoPago = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($porTipoPago as &$t) {
            $t['cantidad'] = (int)$t['cantidad'];
            $t['total'] = (float)$t['total'];
        }
        unset($t);

        return [
            'fecha' => $fecha,
            'recaudado_hoy' => [
                'cantidad' => (int)$recaudadoHoy['cantidad'],
                'total' => (float)$recaudadoHoy['total']
            ],
            'registrado_hoy' => [
                'cantidad' => (int)$registradoHoy['cantidad'],
                'total' => (float)$registradoHoy['total']
            ],
            'recaudado_mes' => [
                'cantidad' => (int)$recaudadoMes['cantidad'],
                'total' => (float)$recaudadoMes['total']
            ],
            'recaudado_mes_ayer' => $totalRecaudadoMesAyer,
            'delta_recaudado_mes' => $deltaRecaudadoMes,
            'recaudado_anio' => [
                'cantidad' => (int)$recaudadoAnio['cantidad'],
                'total' => (float)$recaudadoAnio['total']
            ],
            'recaudado_mes_corriente' => [
                'total' => (float)$mesCorriente['total']
            ],
            'recaudado_mes_anteriores' => [
                'total' => (float)$mesAnteriores['total']
            ],
            'mes_estudiantes' => [
                'cantidad' => (int)$mesEstudiantes['cantidad'],
                'total' => (float)$mesEstudiantes['total']
            ],
            'mes_colaboradores' => [
                'cantidad' => (int)$mesColaboradores['cantidad'],
                'total' => (float)$mesColaboradores['total']
            ],
            'anulados_mes' => [
                'cantidad' => (int)$anuladosMes['cantidad'],
                'total' => (float)$anuladosMes['total']
            ],
            'por_tipo_pago' => $porTipoPago
        ];
    }

    // =========================================================
    // MOVIMIENTOS FINANCIEROS
    // =========================================================
    // IDs hardcodeados de tipos_movimientos_financieros:
    //   ID 1 = Ingreso
    //   ID 2 = Gasto
    const TIPO_MOV_INGRESO = 'INGRESO';
    const TIPO_MOV_GASTO = 'GASTO';

    public static function getMovimientosResumen()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

            $resumen = self::calcularMovimientos($db, $fecha);

            Flight::json($resumen);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Lista de movimientos financieros para el detalle.
     * Params: fecha, rango (hoy|mes|anio), tipo (todos|ingresos|gastos),
     *         estado (todos|aprobados|pendientes|anulados)
     */
    public static function getMovimientosDetalle()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'dashboard.gerencial.listado');

            self::setTimeZone();
            $db = Flight::db();

            $fecha = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
            $rango = isset($_GET['rango']) ? $_GET['rango'] : 'mes';

            Flight::json(self::detalleMovimientos($db, $fecha, $rango));
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function detalleMovimientos($db, $fecha, $rango)
    {

            $fechaObj = new DateTime($fecha);
            $anio = $fechaObj->format('Y');
            $mes = $fechaObj->format('m');

            // Filtro temporal
            $whereFecha = '';
            $params = [];
            switch ($rango) {
                case 'hoy':
                    $whereFecha = "AND m.fecha = :fecha";
                    $params[':fecha'] = $fecha;
                    break;
                case 'anio':
                    $whereFecha = "AND YEAR(m.fecha) = :anio";
                    $params[':anio'] = $anio;
                    break;
                case 'mes':
                default:
                    $whereFecha = "AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes";
                    $params[':anio'] = $anio;
                    $params[':mes'] = $mes;
                    break;
            }

            $sql = "SELECT 
                    m.id,
                    m.fecha,
                    m.valor,
                    m.detalle,
                    m.referencia_externa,
                    m.observaciones,
                    m.anulado,
                    m.fecha_aprobacion,
                    cf.id AS id_concepto,
                    cf.nombre AS concepto,
                    cmf.id AS id_categoria,
                    cmf.nombre AS categoria,
                    cmf.color AS color_categoria,
                    tmf.id AS id_tipo,
                    tmf.nombre AS tipo,
                    mpf.id AS id_medio_pago,
                    mpf.nombre AS medio_pago,
                    CASE 
                        WHEN m.anulado = 1 THEN 'Anulado'
                        WHEN m.fecha_aprobacion IS NULL THEN 'Pendiente'
                        ELSE 'Aprobado'
                    END AS estado
                FROM movimientos_financieros m
                INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
                INNER JOIN tipos_movimientos_financieros tmf ON cmf.id_tipo_movimiento = tmf.id
                LEFT JOIN medios_pago_financieros mpf ON m.id_medio_pago_financiero = mpf.id
                WHERE 1=1
                  AND m.id_tenant = " . TenantContext::id() . "
                  $whereFecha
                ORDER BY m.fecha DESC, m.id DESC";

            $stmt = $db->prepare($sql);
            foreach ($params as $k => $v) $stmt->bindValue($k, $v);
            $stmt->execute();
            $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($registros as &$r) {
                $r['valor'] = (float)$r['valor'];
                $r['anulado'] = (int)$r['anulado'];
            }
            unset($r);

            // Catálogos para filtros
            $sqlCat = "SELECT id, nombre, id_tipo_movimiento FROM categorias_movimientos_financieros WHERE id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtC = $db->prepare($sqlCat);
            $stmtC->execute();
            $categorias = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            $sqlMed = "SELECT id, nombre FROM medios_pago_financieros WHERE id_tenant = " . TenantContext::id() . " ORDER BY nombre";
            $stmtM = $db->prepare($sqlMed);
            $stmtM->execute();
            $medios = $stmtM->fetchAll(PDO::FETCH_ASSOC);

            return [
                'fecha' => $fecha,
                'rango' => $rango,
                'total' => count($registros),
                'registros' => $registros,
                'categorias' => $categorias,
                'medios_pago' => $medios
            ];
    }

    /**
     * Resumen de movimientos financieros:
     *   - Ingresos / Gastos / Balance del mes y del año
     *   - Pendientes de aprobación
     *   - Top 1 categoría de gasto del mes
     *   - Top 1 concepto del mes (gasto)
     * Excluye anulados en todas las métricas.
     * Para ingresos/gastos del mes/año excluye también pendientes de aprobación.
     */
    private static function calcularMovimientos($db, $fecha)
    {
        $fechaObj = new DateTime($fecha);
        $anio = $fechaObj->format('Y');
        $mes = $fechaObj->format('m');
        $fechaAyer = (clone $fechaObj)->modify('-1 day')->format('Y-m-d');

        // Helper para totalizar (ingreso/gasto) por periodo
        $totalizar = function ($db, $idTipo, $periodo, $anio, $mes = null, $hastaFecha = null) {
            // 'mes' o 'anio' o 'mes_hasta' (acotado por hastaFecha)
            $extra = '';
            if ($periodo === 'mes_hasta') {
                $where = "YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes AND m.fecha <= :hasta_fecha";
            } elseif ($periodo === 'mes') {
                $where = "YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes";
            } else {
                $where = "YEAR(m.fecha) = :anio";
            }
            // Excluye anulados y pendientes
            $sql = "SELECT 
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(m.valor), 0) AS total
                FROM movimientos_financieros m
                INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
                WHERE (m.anulado = 0 OR m.anulado IS NULL)
                  AND m.fecha_aprobacion IS NOT NULL
                  AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
                  AND $where AND m.id_tenant = " . TenantContext::id();
            $stmt = $db->prepare($sql);
            $stmt->bindValue(':id_tipo', $idTipo, PDO::PARAM_STR);
            $stmt->bindValue(':anio', $anio);
            if ($periodo === 'mes' || $periodo === 'mes_hasta') $stmt->bindValue(':mes', $mes);
            if ($periodo === 'mes_hasta') $stmt->bindValue(':hasta_fecha', $hastaFecha);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        };

        $ingresosMes = $totalizar($db, self::TIPO_MOV_INGRESO, 'mes', $anio, $mes);
        $gastosMes   = $totalizar($db, self::TIPO_MOV_GASTO,   'mes', $anio, $mes);
        $ingresosAnio = $totalizar($db, self::TIPO_MOV_INGRESO, 'anio', $anio);
        $gastosAnio   = $totalizar($db, self::TIPO_MOV_GASTO,   'anio', $anio);

        // Hasta ayer (para deltas)
        $ingresosMesAyer = $totalizar($db, self::TIPO_MOV_INGRESO, 'mes_hasta', $anio, $mes, $fechaAyer);
        $gastosMesAyer   = $totalizar($db, self::TIPO_MOV_GASTO,   'mes_hasta', $anio, $mes, $fechaAyer);

        $balanceMes = (float)$ingresosMes['total'] - (float)$gastosMes['total'];
        $balanceAnio = (float)$ingresosAnio['total'] - (float)$gastosAnio['total'];

        $deltaIngresosMes = (float)$ingresosMes['total'] - (float)$ingresosMesAyer['total'];
        $deltaGastosMes   = (float)$gastosMes['total']   - (float)$gastosMesAyer['total'];

        // Pendientes de aprobación (no anulados, sin fecha_aprobacion)
        $sqlPend = "SELECT 
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NULL AND m.id_tenant = " . TenantContext::id();
        $stmt = $db->prepare($sqlPend);
        $stmt->execute();
        $pendientes = $stmt->fetch(PDO::FETCH_ASSOC);

        // Top 1 categoría de gasto del mes
        $sqlTopCat = "SELECT 
                cmf.id,
                cmf.nombre,
                cmf.color,
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
            INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NOT NULL
              AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
              AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes
              AND m.id_tenant = " . TenantContext::id() . "
            GROUP BY cmf.id, cmf.nombre, cmf.color
            ORDER BY total DESC
            LIMIT 1";
        $stmt = $db->prepare($sqlTopCat);
        $stmt->bindValue(':id_tipo', self::TIPO_MOV_GASTO, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio);
        $stmt->bindValue(':mes', $mes);
        $stmt->execute();
        $topCategoria = $stmt->fetch(PDO::FETCH_ASSOC);

        // Top 1 concepto del mes (gasto)
        $sqlTopCon = "SELECT 
                cf.id,
                cf.nombre,
                cmf.nombre AS categoria,
                cmf.color AS color_categoria,
                COUNT(*) AS cantidad,
                COALESCE(SUM(m.valor), 0) AS total
            FROM movimientos_financieros m
            INNER JOIN conceptos_financieros cf ON m.id_concepto_financiero = cf.id
            INNER JOIN categorias_movimientos_financieros cmf ON cf.id_categoria_movimiento_financiero = cmf.id
            WHERE (m.anulado = 0 OR m.anulado IS NULL)
              AND m.fecha_aprobacion IS NOT NULL
              AND cmf.id_tipo_movimiento IN (SELECT tmf.id FROM tipos_movimientos_financieros tmf WHERE tmf.codigo = :id_tipo AND tmf.id_tenant = cmf.id_tenant)
              AND YEAR(m.fecha) = :anio AND MONTH(m.fecha) = :mes
              AND m.id_tenant = " . TenantContext::id() . "
            GROUP BY cf.id, cf.nombre, cmf.nombre, cmf.color
            ORDER BY total DESC
            LIMIT 1";
        $stmt = $db->prepare($sqlTopCon);
        $stmt->bindValue(':id_tipo', self::TIPO_MOV_GASTO, PDO::PARAM_STR);
        $stmt->bindValue(':anio', $anio);
        $stmt->bindValue(':mes', $mes);
        $stmt->execute();
        $topConcepto = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'fecha' => $fecha,
            'ingresos_mes' => [
                'cantidad' => (int)$ingresosMes['cantidad'],
                'total' => (float)$ingresosMes['total']
            ],
            'gastos_mes' => [
                'cantidad' => (int)$gastosMes['cantidad'],
                'total' => (float)$gastosMes['total']
            ],
            'delta_ingresos_mes' => $deltaIngresosMes,
            'delta_gastos_mes' => $deltaGastosMes,
            'balance_mes' => $balanceMes,
            'ingresos_anio' => [
                'cantidad' => (int)$ingresosAnio['cantidad'],
                'total' => (float)$ingresosAnio['total']
            ],
            'gastos_anio' => [
                'cantidad' => (int)$gastosAnio['cantidad'],
                'total' => (float)$gastosAnio['total']
            ],
            'balance_anio' => $balanceAnio,
            'pendientes_aprobacion' => [
                'cantidad' => (int)$pendientes['cantidad'],
                'total' => (float)$pendientes['total']
            ],
            'top_categoria_gasto' => $topCategoria ? [
                'id' => (int)$topCategoria['id'],
                'nombre' => $topCategoria['nombre'],
                'color' => $topCategoria['color'],
                'cantidad' => (int)$topCategoria['cantidad'],
                'total' => (float)$topCategoria['total']
            ] : null,
            'top_concepto_gasto' => $topConcepto ? [
                'id' => (int)$topConcepto['id'],
                'nombre' => $topConcepto['nombre'],
                'categoria' => $topConcepto['categoria'],
                'color_categoria' => $topConcepto['color_categoria'],
                'cantidad' => (int)$topConcepto['cantidad'],
                'total' => (float)$topConcepto['total']
            ] : null
        ];
    }
}