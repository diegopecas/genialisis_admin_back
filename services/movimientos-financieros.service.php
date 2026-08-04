<?php
class MovimientosFinancieros
{

    /**
     * Configura la zona horaria de la sesión a Colombia (UTC-5)
     */
    private static function setTimeZone()
    {
        $db = Flight::db();
        $db->exec("SET time_zone = '-05:00'");
    }

    public static function getAll()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT mf.*,
                cf.nombre as concepto_nombre, cf.icono as concepto_icono, cf.requiere_detalle,
                cm.nombre as categoria_nombre, cm.color as categoria_color, cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono,
                mp.nombre as medio_pago_nombre, mp.icono as medio_pago_icono, mp.color as medio_pago_color,
                CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) as usuario_registro_nombre,
                CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido) as usuario_aprobacion_nombre,
                CONCAT_WS(' ', pan.primer_nombre, pan.primer_apellido) as usuario_anulacion_nombre
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                INNER JOIN medios_pago_financieros mp ON mf.id_medio_pago_financiero = mp.id
                INNER JOIN usuarios ur ON mf.id_usuario_registro = ur.id
                INNER JOIN personas pr ON ur.id_persona = pr.id
                LEFT JOIN usuarios ua ON mf.id_usuario_aprobacion = ua.id
                LEFT JOIN personas pa ON ua.id_persona = pa.id
                LEFT JOIN usuarios uan ON mf.id_usuario_anulacion = uan.id
                LEFT JOIN personas pan ON uan.id_persona = pan.id
                WHERE mf.id_tenant = :id_tenant
                ORDER BY mf.fecha DESC, mf.fecha_registro DESC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT mf.*,
                cf.nombre as concepto_nombre, cf.icono as concepto_icono, cf.requiere_detalle,
                cm.nombre as categoria_nombre, cm.color as categoria_color, cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono,
                mp.nombre as medio_pago_nombre, mp.icono as medio_pago_icono, mp.color as medio_pago_color,
                CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) as usuario_registro_nombre,
                CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido) as usuario_aprobacion_nombre,
                CONCAT_WS(' ', pan.primer_nombre, pan.primer_apellido) as usuario_anulacion_nombre
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                INNER JOIN medios_pago_financieros mp ON mf.id_medio_pago_financiero = mp.id
                INNER JOIN usuarios ur ON mf.id_usuario_registro = ur.id
                INNER JOIN personas pr ON ur.id_persona = pr.id
                LEFT JOIN usuarios ua ON mf.id_usuario_aprobacion = ua.id
                LEFT JOIN personas pa ON ua.id_persona = pa.id
                LEFT JOIN usuarios uan ON mf.id_usuario_anulacion = uan.id
                LEFT JOIN personas pan ON uan.id_persona = pan.id
                WHERE mf.id = :id AND mf.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getByFechas()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                SELECT mf.*,
                cf.nombre as concepto_nombre, cf.icono as concepto_icono, cf.requiere_detalle,
                cm.nombre as categoria_nombre, cm.color as categoria_color, cm.id_tipo_movimiento,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono,
                mp.nombre as medio_pago_nombre, mp.icono as medio_pago_icono, mp.color as medio_pago_color,
                CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) as usuario_registro_nombre,
                CONCAT_WS(' ', pa.primer_nombre, pa.primer_apellido) as usuario_aprobacion_nombre,
                CONCAT_WS(' ', pan.primer_nombre, pan.primer_apellido) as usuario_anulacion_nombre
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                INNER JOIN medios_pago_financieros mp ON mf.id_medio_pago_financiero = mp.id
                INNER JOIN usuarios ur ON mf.id_usuario_registro = ur.id
                INNER JOIN personas pr ON ur.id_persona = pr.id
                LEFT JOIN usuarios ua ON mf.id_usuario_aprobacion = ua.id
                LEFT JOIN personas pa ON ua.id_persona = pa.id
                LEFT JOIN usuarios uan ON mf.id_usuario_anulacion = uan.id
                LEFT JOIN personas pan ON uan.id_persona = pan.id
                WHERE mf.fecha BETWEEN :fecha_inicio AND :fecha_fin AND mf.anulado = 0 AND mf.id_tenant = :id_tenant
                ORDER BY mf.fecha DESC, mf.fecha_registro DESC
            ");
            $sentence->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $sentence->bindParam(':fecha_fin', $data['fecha_fin']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getByFechas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getResumenPeriodo()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                SELECT 
                tm.id as tipo_movimiento_id,
                tm.nombre as tipo_movimiento,
                SUM(mf.valor) as total
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE mf.fecha BETWEEN :fecha_inicio AND :fecha_fin 
                AND mf.anulado = 0 
                AND mf.id_usuario_aprobacion IS NOT NULL
                AND mf.id_tenant = :id_tenant
                GROUP BY tm.id, tm.nombre
                ORDER BY tm.id
            ");
            $sentence->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $sentence->bindParam(':fecha_fin', $data['fecha_fin']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getResumenPeriodo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getResumenPorCategoria()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                SELECT 
                cm.id as categoria_id,
                cm.nombre as categoria,
                cm.color as categoria_color,
                tm.nombre as tipo_movimiento,
                SUM(mf.valor) as total,
                COUNT(mf.id) as cantidad
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                WHERE mf.fecha BETWEEN :fecha_inicio AND :fecha_fin 
                AND mf.anulado = 0 
                AND mf.id_usuario_aprobacion IS NOT NULL
                AND mf.id_tenant = :id_tenant
                GROUP BY cm.id, cm.nombre, cm.color, tm.nombre
                ORDER BY total DESC
            ");
            $sentence->bindParam(':fecha_inicio', $data['fecha_inicio']);
            $sentence->bindParam(':fecha_fin', $data['fecha_fin']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getResumenPorCategoria: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Reporte desagregado de movimientos (ingresos y egresos) filtrado por año
     */
    public static function getReporteAnual($anio)
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT 
                mf.id,
                mf.fecha,
                mf.valor,
                mf.detalle,
                mf.referencia_externa,
                mf.observaciones,
                mf.anulado,
                mf.id_usuario_aprobacion,
                mf.fecha_aprobacion,
                cf.nombre as concepto_nombre, cf.icono as concepto_icono,
                cm.nombre as categoria_nombre, cm.color as categoria_color,
                tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono,
                mp.nombre as medio_pago_nombre, mp.icono as medio_pago_icono, mp.color as medio_pago_color,
                CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) as usuario_registro_nombre
                FROM movimientos_financieros mf
                INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
                INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
                INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
                INNER JOIN medios_pago_financieros mp ON mf.id_medio_pago_financiero = mp.id
                INNER JOIN usuarios ur ON mf.id_usuario_registro = ur.id
                INNER JOIN personas pr ON ur.id_persona = pr.id
                WHERE YEAR(mf.fecha) = :anio AND mf.id_tenant = :id_tenant
                ORDER BY mf.fecha DESC, mf.fecha_registro DESC
            ");
            $sentence->bindParam(':anio', $anio, PDO::PARAM_INT);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getReporteAnual: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $data = Flight::request()->data;

            $dataArray = [];
            foreach ($data as $key => $value) {
                $dataArray[$key] = $value;
            }

            $checkConcepto = $db->prepare("SELECT id, requiere_detalle FROM conceptos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkConcepto->bindParam(':id', $dataArray['id_concepto_financiero']);
            $checkConcepto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkConcepto->execute();
            $concepto = $checkConcepto->fetch(PDO::FETCH_ASSOC);

            if (!$concepto) {
                Flight::json(array('error' => 'El concepto financiero no existe'), 400);
                return;
            }

            if ($concepto['requiere_detalle'] == 1 && empty($dataArray['detalle'])) {
                Flight::json(array('error' => 'El concepto seleccionado requiere un detalle'), 400);
                return;
            }

            $detalle = $dataArray['detalle'] ?? null;
            $referencia_externa = $dataArray['referencia_externa'] ?? null;
            $observaciones = $dataArray['observaciones'] ?? null;

            $sentence = $db->prepare("
                INSERT INTO movimientos_financieros 
                (id, id_tenant, fecha, id_concepto_financiero, id_medio_pago_financiero, valor, detalle, 
                 referencia_externa, observaciones, id_usuario_registro, fecha_registro, 
                 anulado, created_at, updated_at) 
                VALUES (:id, :id_tenant, :fecha, :id_concepto_financiero, :id_medio_pago_financiero, :valor, :detalle, 
                        :referencia_externa, :observaciones, :id_usuario_registro, NOW(),
                        0, NOW(), NOW())
            ");

            $idMF = Uuid::generar();
            $sentence->bindValue(':id', $idMF);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':fecha', $dataArray['fecha']);
            $sentence->bindParam(':id_concepto_financiero', $dataArray['id_concepto_financiero']);
            $sentence->bindParam(':id_medio_pago_financiero', $dataArray['id_medio_pago_financiero']);
            $sentence->bindParam(':valor', $dataArray['valor']);
            $sentence->bindParam(':detalle', $detalle);
            $sentence->bindParam(':referencia_externa', $referencia_externa);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_registro', $dataArray['id_usuario_registro']);

            $sentence->execute();
            $id = $idMF;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros new: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $data = Flight::request()->data;

            $dataArray = [];
            foreach ($data as $key => $value) {
                $dataArray[$key] = $value;
            }

            $checkMovimiento = $db->prepare("SELECT id_usuario_aprobacion, anulado FROM movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkMovimiento->bindParam(':id', $dataArray['id']);
            $checkMovimiento->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkMovimiento->execute();
            $movimiento = $checkMovimiento->fetch(PDO::FETCH_ASSOC);

            if (!$movimiento) {
                Flight::json(array('error' => 'El movimiento no existe'), 400);
                return;
            }

            if ($movimiento['id_usuario_aprobacion'] !== null) {
                Flight::json(array('error' => 'No se puede editar un movimiento aprobado'), 400);
                return;
            }

            if ($movimiento['anulado'] == 1) {
                Flight::json(array('error' => 'No se puede editar un movimiento anulado'), 400);
                return;
            }

            $checkConcepto = $db->prepare("SELECT requiere_detalle FROM conceptos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkConcepto->bindParam(':id', $dataArray['id_concepto_financiero']);
            $checkConcepto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkConcepto->execute();
            $concepto = $checkConcepto->fetch(PDO::FETCH_ASSOC);

            if (!$concepto) {
                Flight::json(array('error' => 'El concepto financiero no existe'), 400);
                return;
            }

            if ($concepto['requiere_detalle'] == 1 && empty($dataArray['detalle'])) {
                Flight::json(array('error' => 'El concepto seleccionado requiere un detalle'), 400);
                return;
            }

            $detalle = $dataArray['detalle'] ?? null;
            $referencia_externa = $dataArray['referencia_externa'] ?? null;
            $observaciones = $dataArray['observaciones'] ?? null;

            $sentence = $db->prepare("
                UPDATE movimientos_financieros 
                SET fecha = :fecha, id_concepto_financiero = :id_concepto_financiero, 
                    id_medio_pago_financiero = :id_medio_pago_financiero, valor = :valor, 
                    detalle = :detalle, referencia_externa = :referencia_externa, 
                    observaciones = :observaciones, updated_at = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $dataArray['id']);
            $sentence->bindParam(':fecha', $dataArray['fecha']);
            $sentence->bindParam(':id_concepto_financiero', $dataArray['id_concepto_financiero']);
            $sentence->bindParam(':id_medio_pago_financiero', $dataArray['id_medio_pago_financiero']);
            $sentence->bindParam(':valor', $dataArray['valor']);
            $sentence->bindParam(':detalle', $detalle);
            $sentence->bindParam(':referencia_externa', $referencia_externa);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            self::getById($data['id']);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function aprobar()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $data = Flight::request()->data;

            $dataArray = [];
            foreach ($data as $key => $value) {
                $dataArray[$key] = $value;
            }

            $checkMovimiento = $db->prepare("SELECT id_usuario_aprobacion, anulado FROM movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkMovimiento->bindParam(':id', $dataArray['id']);
            $checkMovimiento->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkMovimiento->execute();
            $movimiento = $checkMovimiento->fetch(PDO::FETCH_ASSOC);

            if ($movimiento['id_usuario_aprobacion'] !== null) {
                Flight::json(array('error' => 'El movimiento ya está aprobado'), 400);
                return;
            }

            if ($movimiento['anulado'] == 1) {
                Flight::json(array('error' => 'No se puede aprobar un movimiento anulado'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE movimientos_financieros 
                SET fecha_aprobacion = NOW(), id_usuario_aprobacion = :id_usuario_aprobacion,
                    updated_at = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $dataArray['id']);
            $sentence->bindParam(':id_usuario_aprobacion', $dataArray['id_usuario_aprobacion']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json(array('message' => 'Movimiento aprobado exitosamente'));
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros aprobar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function anular()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $data = Flight::request()->data;

            $dataArray = [];
            foreach ($data as $key => $value) {
                $dataArray[$key] = $value;
            }

            $checkMovimiento = $db->prepare("SELECT anulado FROM movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkMovimiento->bindParam(':id', $dataArray['id']);
            $checkMovimiento->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkMovimiento->execute();
            $movimiento = $checkMovimiento->fetch(PDO::FETCH_ASSOC);

            if ($movimiento['anulado'] == 1) {
                Flight::json(array('error' => 'El movimiento ya está anulado'), 400);
                return;
            }

            $sentence = $db->prepare("
                UPDATE movimientos_financieros 
                SET anulado = 1, fecha_anulacion = NOW(), id_usuario_anulacion = :id_usuario_anulacion,
                    updated_at = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $dataArray['id']);
            $sentence->bindParam(':id_usuario_anulacion', $dataArray['id_usuario_anulacion']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json(array('message' => 'Movimiento anulado exitosamente'));
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros anular: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $checkMovimiento = $db->prepare("SELECT id_usuario_aprobacion, anulado FROM movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $checkMovimiento->bindParam(':id', $id);
            $checkMovimiento->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkMovimiento->execute();
            $movimiento = $checkMovimiento->fetch(PDO::FETCH_ASSOC);

            if ($movimiento['id_usuario_aprobacion'] !== null) {
                Flight::json(array('error' => 'No se puede eliminar un movimiento aprobado. Debe anularlo primero'), 400);
                return;
            }

            if ($movimiento['anulado'] == 1) {
                Flight::json(array('error' => 'No se puede eliminar un movimiento anulado'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM movimientos_financieros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtiene todos los movimientos pendientes de aprobación
     * (no anulados y sin usuario de aprobación)
     */
    public static function getPendientesAprobacion()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT mf.*,
            cf.nombre as concepto_nombre, cf.icono as concepto_icono, cf.requiere_detalle,
            cm.nombre as categoria_nombre, cm.color as categoria_color, cm.id_tipo_movimiento,
            tm.nombre as tipo_movimiento_nombre, tm.icono as tipo_movimiento_icono,
            mp.nombre as medio_pago_nombre, mp.icono as medio_pago_icono, mp.color as medio_pago_color,
            CONCAT_WS(' ', pr.primer_nombre, pr.primer_apellido) as usuario_registro_nombre,
            'Registrado' as estado
            FROM movimientos_financieros mf
            INNER JOIN conceptos_financieros cf ON mf.id_concepto_financiero = cf.id
            INNER JOIN categorias_movimientos_financieros cm ON cf.id_categoria_movimiento_financiero = cm.id
            INNER JOIN tipos_movimientos_financieros tm ON cm.id_tipo_movimiento = tm.id
            INNER JOIN medios_pago_financieros mp ON mf.id_medio_pago_financiero = mp.id
            INNER JOIN usuarios ur ON mf.id_usuario_registro = ur.id
            INNER JOIN personas pr ON ur.id_persona = pr.id
            WHERE mf.anulado = 0 
            AND mf.id_usuario_aprobacion IS NULL
            AND mf.id_tenant = :id_tenant
            ORDER BY mf.fecha DESC, mf.fecha_registro DESC
        ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros getPendientesAprobacion: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Aprueba múltiples movimientos financieros
     */
    public static function aprobarMultiple()
    {
        try {
            self::setTimeZone();
            $db = Flight::db();
            $body = Flight::request()->data->getData();

            if (!isset($body['ids']) || !is_array($body['ids']) || empty($body['ids'])) {
                Flight::json(array('error' => 'IDs de movimientos requeridos'), 400);
                return;
            }

            if (!isset($body['id_usuario_aprobacion'])) {
                Flight::json(array('error' => 'ID de usuario aprobador requerido'), 400);
                return;
            }

            $ids = $body['ids'];
            $id_usuario_aprobacion = $body['id_usuario_aprobacion'];
            $fecha_aprobacion = $body['fecha_aprobacion'] ?? date('Y-m-d H:i:s');
            $observaciones_aprobacion = $body['observaciones_aprobacion'] ?? 'Aprobación múltiple';

            $aprobados = 0;
            $errores = [];

            $db->beginTransaction();

            try {
                $sqlUpdate = "UPDATE movimientos_financieros 
                         SET id_usuario_aprobacion = :id_usuario,
                             fecha_aprobacion = :fecha,
                             observaciones = CONCAT(
                                 COALESCE(observaciones, ''),
                                 CASE 
                                     WHEN observaciones IS NULL OR observaciones = '' THEN ''
                                     ELSE '\n'
                                 END,
                                 '[APROBACIÓN ',
                                 :fecha_texto,
                                 ']: ',
                                 :observaciones_nuevas
                             ),
                             updated_at = NOW()
                         WHERE id = :id 
                         AND anulado = 0 
                         AND id_usuario_aprobacion IS NULL
                         AND id_tenant = :id_tenant";

                $stmtUpdate = $db->prepare($sqlUpdate);

                foreach ($ids as $id) {
                    $sqlCheck = "SELECT id, anulado, id_usuario_aprobacion, observaciones 
                           FROM movimientos_financieros 
                           WHERE id = :id AND id_tenant = :id_tenant";

                    $stmtCheck = $db->prepare($sqlCheck);
                    $stmtCheck->bindParam(':id', $id);
                    $stmtCheck->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtCheck->execute();
                    $movimiento = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                    if (!$movimiento) {
                        $errores[] = ['id' => $id, 'error' => 'Movimiento no encontrado'];
                        continue;
                    }

                    if ($movimiento['anulado'] == 1) {
                        $errores[] = ['id' => $id, 'error' => 'El movimiento está anulado'];
                        continue;
                    }

                    if ($movimiento['id_usuario_aprobacion']) {
                        $errores[] = ['id' => $id, 'error' => 'El movimiento ya fue aprobado'];
                        continue;
                    }

                    $stmtUpdate->bindParam(':id_usuario', $id_usuario_aprobacion);
                    $stmtUpdate->bindParam(':fecha', $fecha_aprobacion);
                    $stmtUpdate->bindParam(':fecha_texto', $fecha_aprobacion);
                    $stmtUpdate->bindParam(':observaciones_nuevas', $observaciones_aprobacion);
                    $stmtUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtUpdate->bindParam(':id', $id);

                    if ($stmtUpdate->execute() && $stmtUpdate->rowCount() > 0) {
                        $aprobados++;
                    } else {
                        $errores[] = ['id' => $id, 'error' => 'No se pudo aprobar el movimiento'];
                    }
                }

                $db->commit();

                $response = [
                    'success' => true,
                    'message' => "Se aprobaron $aprobados de " . count($ids) . " movimientos",
                    'aprobados' => $aprobados,
                    'total_solicitados' => count($ids),
                    'errores' => $errores
                ];

                if ($aprobados == 0) {
                    $response['success'] = false;
                    $response['message'] = 'No se pudo aprobar ningún movimiento';
                    Flight::json($response, 400);
                } else if ($aprobados < count($ids)) {
                    Flight::json($response, 207);
                } else {
                    Flight::json($response, 200);
                }
            } catch (Exception $e) {
                $db->rollback();
                throw $e;
            }
        } catch (Exception $e) {
            error_log("Error en movimientos_financieros aprobarMultiple: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}