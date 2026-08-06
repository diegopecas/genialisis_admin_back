<?php
class Clientes
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.listado');

        $db = Flight::db();
        $sentence = $db->prepare("SELECT e.id, e.id_persona, e.fecha_ingreso, e.activo, 
        e.alimentacion, e.permanente, e.telefono_emergencia, e.eps, e.anno,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion 
        FROM clientes e 
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        WHERE e.id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT e.id, 
               e.id_persona, 
               e.fecha_ingreso, 
               e.activo, 
               e.alimentacion, 
               e.permanente,
               e.telefono_emergencia,
               e.eps,
               e.anno,
               p.primer_nombre, 
               p.segundo_nombre,
               p.primer_apellido, 
               p.segundo_apellido, 
               p.id_tipo_identificacion, 
               ti.nombre AS tipo_identificacion,
               p.numero_identificacion, 
               p.fecha_nacimiento, 
               TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
               p.id_genero, 
               g.nombre AS nombre_genero, 
               p.direccion,
               grp.id AS id_plan, 
               grp.nombre AS nombre_plan,
               CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) AS nombre_completo
        FROM clientes e 
        INNER JOIN personas p ON e.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        LEFT JOIN clientes_x_planes eg ON e.id = eg.id_cliente AND eg.activo = 1
        LEFT JOIN planes grp ON eg.id_plan = grp.id
        WHERE e.id = :id AND e.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.administrar');

            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $fecha_ingreso = Flight::request()->data['fecha_ingreso'];
            $alimentacion = isset(Flight::request()->data['alimentacion']) ? Flight::request()->data['alimentacion'] : 0;
            $permanente = isset(Flight::request()->data['permanente']) ? Flight::request()->data['permanente'] : 0;
            $telefono_emergencia = isset(Flight::request()->data['telefono_emergencia']) ? Flight::request()->data['telefono_emergencia'] : '';
            $eps = isset(Flight::request()->data['eps']) ? Flight::request()->data['eps'] : '';
            $anno = isset(Flight::request()->data['anno']) ? Flight::request()->data['anno'] : date('Y');

            error_log("Datos recibidos para crear cliente: id_persona=$id_persona, fecha_ingreso=$fecha_ingreso, alimentacion=$alimentacion, permanente=$permanente, telefono_emergencia=$telefono_emergencia, eps=$eps, anno=$anno");

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO clientes(
            id,
            id_tenant,
            id_persona, 
            fecha_ingreso, 
            activo, 
            alimentacion, 
            permanente,
            telefono_emergencia, 
            eps, 
            anno
        ) VALUES (
            :id,
            :id_tenant,
            :id_persona, 
            :fecha_ingreso, 
            1, 
            :alimentacion, 
            :permanente,
            :telefono_emergencia, 
            :eps, 
            :anno
        )");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
            $sentence->bindParam(':alimentacion', $alimentacion);
            $sentence->bindParam(':permanente', $permanente);
            $sentence->bindParam(':telefono_emergencia', $telefono_emergencia);
            $sentence->bindParam(':eps', $eps);
            $sentence->bindParam(':anno', $anno);

            $sentence->execute();

            $id = $idNew;

            if ($id == 0) {
                error_log("Error: El ID insertado es 0. Verifica la ejecución del INSERT.");
                Flight::json(array('error' => 'No se pudo crear el cliente. Intente de nuevo.'), 500);
                return;
            }

            error_log("ID cliente insertado: $id");

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de clientes: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        $fecha_ingreso = Flight::request()->data['fecha_ingreso'];
        $activo = Flight::request()->data['activo'];
        $alimentacion = isset(Flight::request()->data['alimentacion']) ? Flight::request()->data['alimentacion'] : 0;
        $permanente = isset(Flight::request()->data['permanente']) ? Flight::request()->data['permanente'] : 0;
        $telefono_emergencia = isset(Flight::request()->data['telefono_emergencia']) ? Flight::request()->data['telefono_emergencia'] : '';
        $eps = isset(Flight::request()->data['eps']) ? Flight::request()->data['eps'] : '';
        $anno = isset(Flight::request()->data['anno']) ? Flight::request()->data['anno'] : date('Y');

        $sentence = $db->prepare("UPDATE clientes SET 
                                id_persona = :id_persona, 
                                fecha_ingreso = :fecha_ingreso, 
                                activo = :activo,
                                alimentacion = :alimentacion,
                                permanente = :permanente,
                                telefono_emergencia = :telefono_emergencia,
                                eps = :eps,
                                anno = :anno
                                WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':fecha_ingreso', $fecha_ingreso);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':alimentacion', $alimentacion);
        $sentence->bindParam(':permanente', $permanente);
        $sentence->bindParam(':telefono_emergencia', $telefono_emergencia);
        $sentence->bindParam(':eps', $eps);
        $sentence->bindParam(':anno', $anno);
        $sentence->bindParam(':id', $id);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM clientes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getByCliente($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_cliente, 
                                 p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                                 exg.id_plan, g.nombre AS nombre_plan, e.activo, e.alimentacion,
                                 e.telefono_emergencia, e.eps, e.fecha_ingreso, e.anno
                                 FROM clientes_x_planes exg
                                 INNER JOIN clientes e ON exg.id_cliente = e.id 
                                 INNER JOIN personas p ON e.id_persona = p.id 
                                 INNER JOIN planes g ON exg.id_plan = g.id 
                                 WHERE exg.id_cliente = :id AND exg.activo = 1 AND exg.id_tenant = :id_tenant
                                 ORDER BY g.orden");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT exg.id, exg.anio, exg.id_cliente, 
                                 p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
                                 exg.id_plan, g.nombre nombre_plan, e.activo, e.alimentacion,
                                 e.telefono_emergencia, e.eps, e.anno
                                 FROM clientes_x_planes exg
                                 INNER JOIN clientes e ON exg.id_cliente = e.id 
                                 INNER JOIN personas p ON e.id_persona = p.id 
                                 INNER JOIN planes g ON exg.id_plan = g.id 
                                 WHERE e.activo = 1 AND exg.activo = 1 AND exg.id_tenant = :id_tenant
                                 ORDER BY g.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Datos recibidos para crear verificarDuplicados: id_persona=$id_persona");

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM clientes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getReporteCompleto()
    {
        try {
            $db = Flight::db();

            /* ========================================================
               Variable: año académico actual
               ======================================================== */
            $db->exec("SET @anio_actual = (SELECT valor_texto FROM configuracion_global WHERE clave = 'anio_academico_actual' AND id_tenant = " . TenantContext::id() . " LIMIT 1)");

            /* ========================================================
               Tabla temporal: cobrado por persona, concepto y periodo
               clasif y categ se resuelven por CÓDIGO (estable ante el
               cambio de PK INT -> UUID de las tablas de catálogo).
               period sigue siendo entero (periodicidad_cobro no cambió).
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cobrado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_anterior
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* ========================================================
               Tabla temporal: pagado por persona, concepto y periodo
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_pagado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_anterior
                FROM cuenta_pagada cp
                INNER JOIN cuentas_por_cobrar cxc ON cp.id_cuenta_por_cobrar = cxc.id
                INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND pr.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* ========================================================
               Tabla temporal: pivoteo por persona con las 36 columnas
               Concepto = (clasif codigo, period entero, categ codigo)
               ======================================================== */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cartera AS
                SELECT 
                    id_persona,
                    /* MATRÍCULA - Año actual (ACADEMICO, period=1, MENSUAL) */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS implementacion_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS implementacion_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS implementacion_saldo_actual,
                    /* MATRÍCULA - Años anteriores */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS implementacion_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS implementacion_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS implementacion_saldo_anterior,

                    /* PENSIÓN - Año actual (ACADEMICO, period=2, MENSUAL) */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS suscripcion_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS suscripcion_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS suscripcion_saldo_actual,
                    /* PENSIÓN - Años anteriores */
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS suscripcion_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS suscripcion_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS suscripcion_saldo_anterior,

                    /* ALMUERZO - Año actual (ALIMENTACION, period=2, MENSUAL) */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS almuerzo_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_saldo_actual,
                    /* ALMUERZO - Años anteriores */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS almuerzo_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_saldo_anterior,

                    /* ONCES - Año actual (ALIMENTACION, period=3, EXTRA) */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS onces_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_saldo_actual,
                    /* ONCES - Años anteriores */
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS onces_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_saldo_anterior,

                    /* HORAS EXTRAS - Año actual (EXTRA_ACADEMICO, period=3, EXTRA) */
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS horas_extras_cobrado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_pagado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_saldo_actual,
                    /* HORAS EXTRAS - Años anteriores */
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS horas_extras_cobrado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_pagado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_saldo_anterior,

                    /* VESTUARIO - Año actual (VESTUARIO, period=4, EXTRA) */
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS vestuario_cobrado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_pagado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_saldo_actual,
                    /* VESTUARIO - Años anteriores */
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS vestuario_cobrado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_pagado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_saldo_anterior
                FROM (
                    SELECT id_persona, clasif, period, categ, cobrado_actual, cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior
                    FROM tmp_cobrado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, pagado_actual, pagado_anterior
                    FROM tmp_pagado
                ) combined
                GROUP BY id_persona
            ");

            /* ========================================================
               Query principal con LEFT JOIN a tmp_cartera
               ======================================================== */
            $sentence = $db->prepare("
                SELECT 
                    e.id,
                    e.id AS id_cliente,
                    e.id_persona,
                    e.fecha_ingreso,
                    e.activo,
                    e.alimentacion,
                    e.telefono_emergencia,
                    e.eps,
                    e.anno,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                    p.id_tipo_identificacion,
                    ti.nombre AS tipo_identificacion,
                    p.numero_identificacion,
                    p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    p.id_genero,
                    g.nombre AS nombre_genero,
                    p.direccion,
                    IFNULL(grp.id, 0) AS id_plan,
                    IFNULL(grp.nombre, 'Sin plan') AS nombre_plan,
                    CASE WHEN e.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                    CASE WHEN e.alimentacion = 1 THEN 'Sí' ELSE 'No' END AS alimentacion_texto,
                    e.permanente,
                    CASE WHEN e.permanente = 1 THEN 'Sí' ELSE 'No' END AS permanente_texto,

                    /* Contrato */
                    (SELECT cm.id 
                        FROM contratos_cliente cm 
                        WHERE cm.id_cliente = e.id 
                        AND cm.anio = @anio_actual
                        AND cm.activo = 1 
                        LIMIT 1) AS id_contrato,
                    CASE 
                        WHEN (SELECT cm2.id FROM contratos_cliente cm2 
                              WHERE cm2.id_cliente = e.id AND cm2.anio = @anio_actual AND cm2.activo = 1 LIMIT 1) IS NULL THEN 'Sin contrato'
                        WHEN (SELECT cm3.firmado FROM contratos_cliente cm3 
                              WHERE cm3.id_cliente = e.id AND cm3.anio = @anio_actual AND cm3.activo = 1 LIMIT 1) = 1 THEN 'Firmado'
                        ELSE 'Pendiente'
                    END AS estado_contrato,
                    IFNULL((SELECT cm4.valor_implementacion FROM contratos_cliente cm4 
                        WHERE cm4.id_cliente = e.id AND cm4.anio = @anio_actual AND cm4.activo = 1 LIMIT 1), 0) AS valor_implementacion,
                    IFNULL((SELECT cm5.valor_suscripcion FROM contratos_cliente cm5 
                        WHERE cm5.id_cliente = e.id AND cm5.anio = @anio_actual AND cm5.activo = 1 LIMIT 1), 0) AS valor_suscripcion,

                    /* Implementación mes actual */
                    IFNULL((SELECT cmv.valor 
                        FROM contratos_cliente_valores cmv
                        INNER JOIN contratos_cliente cm6 ON cmv.id_contrato = cm6.id
                        INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                        WHERE cm6.id_cliente = e.id AND cm6.anio = @anio_actual AND cm6.activo = 1
                        AND ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps.id_tenant) AND ps.id_periodicidad_cobro = 1 AND ps.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps.id_tenant)
                        AND MONTH(cmv.fecha) = MONTH(CURDATE()) AND YEAR(cmv.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS implementacion_mes_actual,

                    /* Suscripción mes actual */
                    IFNULL((SELECT cmv2.valor 
                        FROM contratos_cliente_valores cmv2
                        INNER JOIN contratos_cliente cm7 ON cmv2.id_contrato = cm7.id
                        INNER JOIN productos_servicios ps2 ON cmv2.id_producto_servicio = ps2.id
                        WHERE cm7.id_cliente = e.id AND cm7.anio = @anio_actual AND cm7.activo = 1
                        AND ps2.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps2.id_tenant) AND ps2.id_periodicidad_cobro = 2 AND ps2.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps2.id_tenant)
                        AND MONTH(cmv2.fecha) = MONTH(CURDATE()) AND YEAR(cmv2.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS suscripcion_mes_actual,

                    /* 36 columnas de cartera desde tmp_cartera */
                    IFNULL(tc.implementacion_cobrado_actual, 0) AS implementacion_cobrado_actual,
                    IFNULL(tc.implementacion_pagado_actual, 0) AS implementacion_pagado_actual,
                    IFNULL(tc.implementacion_saldo_actual, 0) AS implementacion_saldo_actual,
                    IFNULL(tc.implementacion_cobrado_anterior, 0) AS implementacion_cobrado_anterior,
                    IFNULL(tc.implementacion_pagado_anterior, 0) AS implementacion_pagado_anterior,
                    IFNULL(tc.implementacion_saldo_anterior, 0) AS implementacion_saldo_anterior,

                    IFNULL(tc.suscripcion_cobrado_actual, 0) AS suscripcion_cobrado_actual,
                    IFNULL(tc.suscripcion_pagado_actual, 0) AS suscripcion_pagado_actual,
                    IFNULL(tc.suscripcion_saldo_actual, 0) AS suscripcion_saldo_actual,
                    IFNULL(tc.suscripcion_cobrado_anterior, 0) AS suscripcion_cobrado_anterior,
                    IFNULL(tc.suscripcion_pagado_anterior, 0) AS suscripcion_pagado_anterior,
                    IFNULL(tc.suscripcion_saldo_anterior, 0) AS suscripcion_saldo_anterior,

                    IFNULL(tc.almuerzo_cobrado_actual, 0) AS almuerzo_cobrado_actual,
                    IFNULL(tc.almuerzo_pagado_actual, 0) AS almuerzo_pagado_actual,
                    IFNULL(tc.almuerzo_saldo_actual, 0) AS almuerzo_saldo_actual,
                    IFNULL(tc.almuerzo_cobrado_anterior, 0) AS almuerzo_cobrado_anterior,
                    IFNULL(tc.almuerzo_pagado_anterior, 0) AS almuerzo_pagado_anterior,
                    IFNULL(tc.almuerzo_saldo_anterior, 0) AS almuerzo_saldo_anterior,

                    IFNULL(tc.onces_cobrado_actual, 0) AS onces_cobrado_actual,
                    IFNULL(tc.onces_pagado_actual, 0) AS onces_pagado_actual,
                    IFNULL(tc.onces_saldo_actual, 0) AS onces_saldo_actual,
                    IFNULL(tc.onces_cobrado_anterior, 0) AS onces_cobrado_anterior,
                    IFNULL(tc.onces_pagado_anterior, 0) AS onces_pagado_anterior,
                    IFNULL(tc.onces_saldo_anterior, 0) AS onces_saldo_anterior,

                    IFNULL(tc.horas_extras_cobrado_actual, 0) AS horas_extras_cobrado_actual,
                    IFNULL(tc.horas_extras_pagado_actual, 0) AS horas_extras_pagado_actual,
                    IFNULL(tc.horas_extras_saldo_actual, 0) AS horas_extras_saldo_actual,
                    IFNULL(tc.horas_extras_cobrado_anterior, 0) AS horas_extras_cobrado_anterior,
                    IFNULL(tc.horas_extras_pagado_anterior, 0) AS horas_extras_pagado_anterior,
                    IFNULL(tc.horas_extras_saldo_anterior, 0) AS horas_extras_saldo_anterior,

                    IFNULL(tc.vestuario_cobrado_actual, 0) AS vestuario_cobrado_actual,
                    IFNULL(tc.vestuario_pagado_actual, 0) AS vestuario_pagado_actual,
                    IFNULL(tc.vestuario_saldo_actual, 0) AS vestuario_saldo_actual,
                    IFNULL(tc.vestuario_cobrado_anterior, 0) AS vestuario_cobrado_anterior,
                    IFNULL(tc.vestuario_pagado_anterior, 0) AS vestuario_pagado_anterior,
                    IFNULL(tc.vestuario_saldo_anterior, 0) AS vestuario_saldo_anterior,

                    IFNULL((
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                IFNULL(pa.primer_nombre, ''), ' ', 
                                IFNULL(pa.segundo_nombre, ''), ' ', 
                                IFNULL(pa.primer_apellido, ''), ' ', 
                                IFNULL(pa.segundo_apellido, ''), ' - ', 
                                ta.nombre
                            ) SEPARATOR '; '
                        )
                        FROM representantes a
                        INNER JOIN personas pa ON a.id_persona = pa.id
                        INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
                        WHERE a.id_cliente = e.id
                    ), 'Sin representantes registrados') AS representantes,

                    /* Documentos obligatorios pendientes */
                    IFNULL((
                        SELECT COUNT(*)
                        FROM tipos_personas_documentos tpd
                        INNER JOIN tipos_documentos td ON tpd.id_tipo_documento = td.id
                        INNER JOIN tipos_personas tp ON tp.id = tpd.id_tipo_persona AND tp.id_tenant = tpd.id_tenant
                        WHERE tp.codigo = 'cliente'
                        AND tpd.id_tenant = " . TenantContext::id() . "
                        AND tpd.obligatorio = 1
                        AND td.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp
                            WHERE dp.id_persona = e.id_persona
                            AND dp.id_tipo_documento = tpd.id_tipo_documento
                            AND dp.activo = 1
                        )
                    ), 0) AS docs_pendientes_cantidad,

                    IFNULL((
                        SELECT GROUP_CONCAT(td2.nombre ORDER BY tpd2.orden ASC SEPARATOR ', ')
                        FROM tipos_personas_documentos tpd2
                        INNER JOIN tipos_documentos td2 ON tpd2.id_tipo_documento = td2.id
                        INNER JOIN tipos_personas tp2 ON tp2.id = tpd2.id_tipo_persona AND tp2.id_tenant = tpd2.id_tenant
                        WHERE tp2.codigo = 'cliente'
                        AND tpd2.id_tenant = " . TenantContext::id() . "
                        AND tpd2.obligatorio = 1
                        AND td2.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp2
                            WHERE dp2.id_persona = e.id_persona
                            AND dp2.id_tipo_documento = tpd2.id_tipo_documento
                            AND dp2.activo = 1
                        )
                    ), '') AS docs_pendientes_detalle

                FROM clientes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                LEFT JOIN generos g ON p.id_genero = g.id
                LEFT JOIN clientes_x_planes eg ON e.id = eg.id_cliente AND eg.activo = 1
                LEFT JOIN planes grp ON eg.id_plan = grp.id
                LEFT JOIN tmp_cartera tc ON tc.id_persona = e.id_persona
                WHERE e.id_tenant = :id_tenant
                ORDER BY grp.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();

            if (is_array($response)) {
                foreach ($response as &$row) {
                    if (isset($row['nombre_completo'])) {
                        $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                    }
                    $row['telefono_emergencia'] = isset($row['telefono_emergencia']) && $row['telefono_emergencia'] ? $row['telefono_emergencia'] : '';
                    $row['eps'] = isset($row['eps']) && $row['eps'] ? $row['eps'] : '';
                    $row['direccion'] = isset($row['direccion']) && $row['direccion'] ? $row['direccion'] : '';
                }
            }

            /* Limpiar temporales */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");

            Flight::json($response);
        } catch (Exception $e) {
            error_log("Error en getReporteCompleto: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el reporte: ' . $e->getMessage()), 500);
        }
    }

    public static function actualizacionMasiva()
    {
        try {
            $db = Flight::db();
            $ids = Flight::request()->data['ids'];
            $campos = Flight::request()->data['campos'];

            if (empty($ids) || empty($campos)) {
                Flight::json(array('success' => false, 'message' => 'Datos incompletos'), 400);
                return;
            }

            $setClauses = [];
            $params = [];

            if (isset($campos['activo'])) {
                $setClauses[] = 'activo = ?';
                $params[] = $campos['activo'];
            }
            if (isset($campos['anno'])) {
                $setClauses[] = 'anno = ?';
                $params[] = $campos['anno'];
            }
            if (isset($campos['alimentacion'])) {
                $setClauses[] = 'alimentacion = ?';
                $params[] = $campos['alimentacion'];
            }
            if (isset($campos['permanente'])) {
                $setClauses[] = 'permanente = ?';
                $params[] = $campos['permanente'];
            }

            if (empty($setClauses)) {
                Flight::json(array('success' => false, 'message' => 'No hay campos para actualizar'), 400);
                return;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE clientes SET " . implode(', ', $setClauses) . " WHERE id IN ($placeholders) AND id_tenant = ?";

            $sentence = $db->prepare($sql);

            $paramIndex = 1;
            foreach ($params as $value) {
                $sentence->bindValue($paramIndex++, $value);
            }
            foreach ($ids as $id) {
                $sentence->bindValue($paramIndex++, $id, PDO::PARAM_STR);
            }
            $sentence->bindValue($paramIndex++, TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();
            $actualizados = $sentence->rowCount();

            Flight::json(array(
                'success' => true,
                'actualizados' => $actualizados,
                'message' => 'Actualización completada'
            ));

        } catch (Exception $e) {
            error_log("Error en actualizacionMasiva: " . $e->getMessage());
            Flight::json(array('success' => false, 'message' => $e->getMessage()), 500);
        }
    }

    /**
     * Registro rápido desde módulo de asistencia.
     * Crea persona del niño (o reutiliza), cliente, asigna plan,
     * crea persona del representante (o reutiliza), crea representante.
     * NO registra asistencia — eso lo hace el flujo normal del módulo.
     */
    public static function registroRapido()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.administrar');

            $db = Flight::db();
            $db->beginTransaction();

            $data = Flight::request()->data;

            // === DATOS DEL NIÑO ===
            $nino_id_tipo_identificacion = $data['nino_id_tipo_identificacion'];
            $nino_numero_identificacion = $data['nino_numero_identificacion'];
            $nino_primer_nombre = $data['nino_primer_nombre'];
            $nino_primer_apellido = $data['nino_primer_apellido'];
            $nino_segundo_nombre = isset($data['nino_segundo_nombre']) ? $data['nino_segundo_nombre'] : null;
            $nino_segundo_apellido = isset($data['nino_segundo_apellido']) ? $data['nino_segundo_apellido'] : null;
            $nino_fecha_nacimiento = isset($data['nino_fecha_nacimiento']) ? $data['nino_fecha_nacimiento'] : null;
            $id_plan = $data['id_plan'];

            // === DATOS DEL REPRESENTANTE ===
            $acud_id_tipo_identificacion = $data['acud_id_tipo_identificacion'];
            $acud_numero_identificacion = $data['acud_numero_identificacion'];
            $acud_primer_nombre = $data['acud_primer_nombre'];
            $acud_primer_apellido = $data['acud_primer_apellido'];
            $acud_segundo_nombre = isset($data['acud_segundo_nombre']) ? $data['acud_segundo_nombre'] : null;
            $acud_segundo_apellido = isset($data['acud_segundo_apellido']) ? $data['acud_segundo_apellido'] : null;
            $acud_telefono = isset($data['acud_telefono']) ? $data['acud_telefono'] : null;
            $id_tipo_representante = $data['id_tipo_representante'];

            // ============================================================
            // 1. PERSONA DEL NIÑO: buscar o crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM personas WHERE id_tipo_identificacion = :tipo AND numero_identificacion = :numero AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $nino_id_tipo_identificacion);
            $stmt->bindParam(':numero', $nino_numero_identificacion);
            $stmt->execute();
            $personaNino = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($personaNino) {
                $id_persona_nino = $personaNino['id'];
            } else {
                $idPersonaNino = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO personas (id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, id_tipo_identificacion, numero_identificacion, fecha_nacimiento, nacionalidad, ocupacion) 
                    VALUES (:id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :id_tipo_identificacion, :numero_identificacion, :fecha_nacimiento, 'Colombiana', 'Cliente')");
                $stmt->bindValue(':id', $idPersonaNino);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':primer_nombre', $nino_primer_nombre);
                $stmt->bindParam(':segundo_nombre', $nino_segundo_nombre);
                $stmt->bindParam(':primer_apellido', $nino_primer_apellido);
                $stmt->bindParam(':segundo_apellido', $nino_segundo_apellido);
                $stmt->bindParam(':id_tipo_identificacion', $nino_id_tipo_identificacion);
                $stmt->bindParam(':numero_identificacion', $nino_numero_identificacion);
                $stmt->bindParam(':fecha_nacimiento', $nino_fecha_nacimiento);
                $stmt->execute();
                $id_persona_nino = $idPersonaNino;

                if ($id_persona_nino == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear la persona del niño'), 500);
                    return;
                }
            }

            // ============================================================
            // 2. CLIENTE: verificar que no exista, crear
            // ============================================================
            $stmt = $db->prepare("SELECT id, activo FROM clientes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_persona', $id_persona_nino);
            $stmt->execute();
            $clienteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            $cliente_ya_existia = false;
            if ($clienteExistente) {
                $id_cliente = $clienteExistente['id'];
                $cliente_ya_existia = true;

                if ($clienteExistente['activo'] == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Este cliente existe pero está inactivo. Active el cliente primero desde el módulo de clientes.'), 400);
                    return;
                }
            } else {
                $fecha_hoy = date('Y-m-d');
                $anno_actual = date('Y');
                $idCliente = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO clientes (id, id_tenant, id_persona, fecha_ingreso, activo, alimentacion, permanente, telefono_emergencia, eps, anno) 
                    VALUES (:id, :id_tenant, :id_persona, :fecha_ingreso, 1, 0, 0, '', '', :anno)");
                $stmt->bindValue(':id', $idCliente);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_persona', $id_persona_nino);
                $stmt->bindParam(':fecha_ingreso', $fecha_hoy);
                $stmt->bindParam(':anno', $anno_actual);
                $stmt->execute();
                $id_cliente = $idCliente;

                if ($id_cliente == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear el cliente'), 500);
                    return;
                }
            }

            // ============================================================
            // 3. ASIGNAR PLAN (si no tiene uno activo)
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM clientes_x_planes WHERE id_cliente = :id_cliente AND activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_cliente', $id_cliente);
            $stmt->execute();
            $planActual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$planActual) {
                $anno_actual = date('Y');
                $stmt = $db->prepare("INSERT INTO clientes_x_planes (id_tenant, id_cliente, id_plan, anio, activo) VALUES (:id_tenant, :id_cliente, :id_plan, :anio, 1)");
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_cliente', $id_cliente);
                $stmt->bindParam(':id_plan', $id_plan);
                $stmt->bindParam(':anio', $anno_actual);
                $stmt->execute();
            }

            // ============================================================
            // 4. PERSONA DEL REPRESENTANTE: buscar o crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM personas WHERE id_tipo_identificacion = :tipo AND numero_identificacion = :numero AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':tipo', $acud_id_tipo_identificacion);
            $stmt->bindParam(':numero', $acud_numero_identificacion);
            $stmt->execute();
            $personaAcud = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($personaAcud) {
                $id_persona_representante = $personaAcud['id'];
                if ($acud_telefono) {
                    $stmtTel = $db->prepare("UPDATE personas SET telefono = :telefono WHERE id = :id AND id_tenant = :id_tenant AND (telefono IS NULL OR telefono = '')");
                    $stmtTel->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtTel->bindParam(':telefono', $acud_telefono);
                    $stmtTel->bindParam(':id', $id_persona_representante);
                    $stmtTel->execute();
                }
            } else {
                $idPersonaAcud = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO personas (id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido, id_tipo_identificacion, numero_identificacion, telefono, nacionalidad) 
                    VALUES (:id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido, :id_tipo_identificacion, :numero_identificacion, :telefono, 'Colombiana')");
                $stmt->bindValue(':id', $idPersonaAcud);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':primer_nombre', $acud_primer_nombre);
                $stmt->bindParam(':segundo_nombre', $acud_segundo_nombre);
                $stmt->bindParam(':primer_apellido', $acud_primer_apellido);
                $stmt->bindParam(':segundo_apellido', $acud_segundo_apellido);
                $stmt->bindParam(':id_tipo_identificacion', $acud_id_tipo_identificacion);
                $stmt->bindParam(':numero_identificacion', $acud_numero_identificacion);
                $stmt->bindParam(':telefono', $acud_telefono);
                $stmt->execute();
                $id_persona_representante = $idPersonaAcud;

                if ($id_persona_representante == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'No se pudo crear la persona del representante'), 500);
                    return;
                }
            }

            // ============================================================
            // 5. REPRESENTANTE: verificar duplicado y crear
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM representantes WHERE id_cliente = :id_cliente AND id_persona = :id_persona AND id_tipo_representante = :id_tipo_representante AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_cliente', $id_cliente);
            $stmt->bindParam(':id_persona', $id_persona_representante);
            $stmt->bindParam(':id_tipo_representante', $id_tipo_representante);
            $stmt->execute();
            $representanteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            $id_representante = 0;
            if ($representanteExistente) {
                $id_representante = $representanteExistente['id'];
            } else {
                $idRepresentante = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO representantes (id, id_tenant, id_cliente, id_persona, id_tipo_representante, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo) 
                    VALUES (:id, :id_tenant, :id_cliente, :id_persona, :id_tipo_representante, 1, 1, 1, 1)");
                $stmt->bindValue(':id', $idRepresentante);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_cliente', $id_cliente);
                $stmt->bindParam(':id_persona', $id_persona_representante);
                $stmt->bindParam(':id_tipo_representante', $id_tipo_representante);
                $stmt->execute();
                $id_representante = $idRepresentante;
            }

            $db->commit();

            Flight::json(array(
                'id_cliente' => $id_cliente,
                'id_persona_nino' => $id_persona_nino,
                'id_persona_representante' => $id_persona_representante,
                'id_representante' => $id_representante,
                'cliente_ya_existia' => $cliente_ya_existia,
                'nombre_cliente' => trim($nino_primer_nombre . ' ' . $nino_primer_apellido)
            ));

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en registroRapido: " . $e->getMessage());
            Flight::json(array('error' => 'Error en registro rápido: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Reporte para recordatorios generales.
     * Igual a getReporteCompleto pero agrega último recordatorio general
     * y devuelve representantes desglosados con teléfono y correo.
     */
    public static function getReporteRecordatorios()
    {
        try {
            $db = Flight::db();

            /* Variable: año académico actual */
            $db->exec("SET @anio_actual = (SELECT valor_texto FROM configuracion_global WHERE clave = 'anio_academico_actual' AND id_tenant = " . TenantContext::id() . " LIMIT 1)");

            /* Tabla temporal: cobrado */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cobrado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cxc.valor ELSE 0 END) AS cobrado_anterior
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: pagado */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_pagado AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(CASE WHEN YEAR(cxc.fecha) = @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_actual,
                    SUM(CASE WHEN YEAR(cxc.fecha) < @anio_actual THEN cp.valor_aplicado ELSE 0 END) AS pagado_anterior
                FROM cuenta_pagada cp
                INNER JOIN cuentas_por_cobrar cxc ON cp.id_cuenta_por_cobrar = cxc.id
                INNER JOIN pagos_recibidos pr ON cp.id_pago_recibido = pr.id
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND pr.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: saldo vencido por concepto (fecha < hoy, saldo > 0) */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_vencido");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_vencido AS
                SELECT 
                    cxc.id_persona,
                    cl.codigo AS clasif,
                    ps.id_periodicidad_cobro AS period,
                    cat.codigo AS categ,
                    SUM(
                        cxc.valor - COALESCE((
                            SELECT SUM(cpx.valor_aplicado) 
                            FROM cuenta_pagada cpx 
                            INNER JOIN pagos_recibidos prx ON cpx.id_pago_recibido = prx.id
                            WHERE cpx.id_cuenta_por_cobrar = cxc.id AND prx.anulado = 0
                        ), 0)
                    ) AS saldo_vencido
                FROM cuentas_por_cobrar cxc
                INNER JOIN productos_servicios ps ON cxc.id_producto_servicio = ps.id
                LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios AND cl.id_tenant = ps.id_tenant
                LEFT JOIN categoria_productos_servicios cat ON cat.id = ps.id_categoria_productos_servicios AND cat.id_tenant = ps.id_tenant
                WHERE cxc.anulado = 0 AND cxc.id_tenant = " . TenantContext::id() . "
                AND cxc.fecha < CURDATE()
                AND (cxc.valor - COALESCE((
                    SELECT SUM(cpx2.valor_aplicado) 
                    FROM cuenta_pagada cpx2 
                    INNER JOIN pagos_recibidos prx2 ON cpx2.id_pago_recibido = prx2.id
                    WHERE cpx2.id_cuenta_por_cobrar = cxc.id AND prx2.anulado = 0
                ), 0)) > 0
                GROUP BY cxc.id_persona, clasif, period, categ
            ");

            /* Tabla temporal: pivoteo 36 columnas + 6 vencido */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");
            $db->exec("
                CREATE TEMPORARY TABLE tmp_cartera AS
                SELECT 
                    id_persona,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS implementacion_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS implementacion_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS implementacion_saldo_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS implementacion_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS implementacion_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS implementacion_saldo_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=1 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS implementacion_vencido,

                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS suscripcion_cobrado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS suscripcion_pagado_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS suscripcion_saldo_actual,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS suscripcion_cobrado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS suscripcion_pagado_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS suscripcion_saldo_anterior,
                    SUM(CASE WHEN clasif='ACADEMICO' AND period=2 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS suscripcion_vencido,

                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) AS almuerzo_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_actual ELSE 0 END) AS almuerzo_saldo_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) AS almuerzo_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN pagado_anterior ELSE 0 END) AS almuerzo_saldo_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=2 AND categ='MENSUAL' THEN saldo_vencido ELSE 0 END) AS almuerzo_vencido,

                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS onces_cobrado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_pagado_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS onces_saldo_actual,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS onces_cobrado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_pagado_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS onces_saldo_anterior,
                    SUM(CASE WHEN clasif='ALIMENTACION' AND period=3 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS onces_vencido,

                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS horas_extras_cobrado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_pagado_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS horas_extras_saldo_actual,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS horas_extras_cobrado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_pagado_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS horas_extras_saldo_anterior,
                    SUM(CASE WHEN clasif='EXTRA_ACADEMICO' AND period=3 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS horas_extras_vencido,

                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) AS vestuario_cobrado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_pagado_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_actual ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_actual ELSE 0 END) AS vestuario_saldo_actual,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) AS vestuario_cobrado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_pagado_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN cobrado_anterior ELSE 0 END) 
                    - SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN pagado_anterior ELSE 0 END) AS vestuario_saldo_anterior,
                    SUM(CASE WHEN clasif='VESTUARIO' AND period=4 AND categ='EXTRA' THEN saldo_vencido ELSE 0 END) AS vestuario_vencido
                FROM (
                    SELECT id_persona, clasif, period, categ, cobrado_actual, cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior, 0 AS saldo_vencido
                    FROM tmp_cobrado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, pagado_actual, pagado_anterior, 0 AS saldo_vencido
                    FROM tmp_pagado
                    UNION ALL
                    SELECT id_persona, clasif, period, categ, 0 AS cobrado_actual, 0 AS cobrado_anterior, 0 AS pagado_actual, 0 AS pagado_anterior, saldo_vencido
                    FROM tmp_vencido
                ) combined
                GROUP BY id_persona
            ");

            /* Query principal: igual a getReporteCompleto + ultimo_recordatorio */
            $sentence = $db->prepare("
                SELECT 
                    e.id,
                    e.id AS id_cliente,
                    e.id_persona,
                    e.fecha_ingreso,
                    e.activo,
                    e.alimentacion,
                    e.telefono_emergencia,
                    e.eps,
                    e.anno,
                    p.primer_nombre,
                    p.segundo_nombre,
                    p.primer_apellido,
                    p.segundo_apellido,
                    CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                    p.id_tipo_identificacion,
                    ti.nombre AS tipo_identificacion,
                    p.numero_identificacion,
                    p.fecha_nacimiento,
                    TIMESTAMPDIFF(YEAR, p.fecha_nacimiento, CURDATE()) AS edad,
                    p.id_genero,
                    g.nombre AS nombre_genero,
                    p.direccion,
                    IFNULL(grp.id, 0) AS id_plan,
                    IFNULL(grp.nombre, 'Sin plan') AS nombre_plan,
                    CASE WHEN e.activo = 1 THEN 'Activo' ELSE 'Inactivo' END AS estado,
                    CASE WHEN e.alimentacion = 1 THEN 'Sí' ELSE 'No' END AS alimentacion_texto,

                    /* Contrato */
                    (SELECT cm.id 
                        FROM contratos_cliente cm 
                        WHERE cm.id_cliente = e.id 
                        AND cm.anio = @anio_actual
                        AND cm.activo = 1 
                        LIMIT 1) AS id_contrato,
                    CASE 
                        WHEN (SELECT cm2.id FROM contratos_cliente cm2 
                              WHERE cm2.id_cliente = e.id AND cm2.anio = @anio_actual AND cm2.activo = 1 LIMIT 1) IS NULL THEN 'Sin contrato'
                        WHEN (SELECT cm3.firmado FROM contratos_cliente cm3 
                              WHERE cm3.id_cliente = e.id AND cm3.anio = @anio_actual AND cm3.activo = 1 LIMIT 1) = 1 THEN 'Firmado'
                        ELSE 'Pendiente'
                    END AS estado_contrato,
                    IFNULL((SELECT cm4.valor_implementacion FROM contratos_cliente cm4 
                        WHERE cm4.id_cliente = e.id AND cm4.anio = @anio_actual AND cm4.activo = 1 LIMIT 1), 0) AS valor_implementacion,
                    IFNULL((SELECT cm5.valor_suscripcion FROM contratos_cliente cm5 
                        WHERE cm5.id_cliente = e.id AND cm5.anio = @anio_actual AND cm5.activo = 1 LIMIT 1), 0) AS valor_suscripcion,

                    /* Implementación mes actual */
                    IFNULL((SELECT cmv.valor 
                        FROM contratos_cliente_valores cmv
                        INNER JOIN contratos_cliente cm6 ON cmv.id_contrato = cm6.id
                        INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
                        WHERE cm6.id_cliente = e.id AND cm6.anio = @anio_actual AND cm6.activo = 1
                        AND ps.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps.id_tenant) AND ps.id_periodicidad_cobro = 1 AND ps.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps.id_tenant)
                        AND MONTH(cmv.fecha) = MONTH(CURDATE()) AND YEAR(cmv.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS implementacion_mes_actual,

                    /* Suscripción mes actual */
                    IFNULL((SELECT cmv2.valor 
                        FROM contratos_cliente_valores cmv2
                        INNER JOIN contratos_cliente cm7 ON cmv2.id_contrato = cm7.id
                        INNER JOIN productos_servicios ps2 ON cmv2.id_producto_servicio = ps2.id
                        WHERE cm7.id_cliente = e.id AND cm7.anio = @anio_actual AND cm7.activo = 1
                        AND ps2.id_clasificacion_productos_servicios IN (SELECT cps.id FROM clasificacion_productos_servicios cps WHERE cps.codigo = 'ACADEMICO' AND cps.id_tenant = ps2.id_tenant) AND ps2.id_periodicidad_cobro = 2 AND ps2.id_categoria_productos_servicios IN (SELECT cat.id FROM categoria_productos_servicios cat WHERE cat.codigo = 'MENSUAL' AND cat.id_tenant = ps2.id_tenant)
                        AND MONTH(cmv2.fecha) = MONTH(CURDATE()) AND YEAR(cmv2.fecha) = YEAR(CURDATE())
                        LIMIT 1), 0) AS suscripcion_mes_actual,

                    /* 36 columnas de cartera */
                    IFNULL(tc.implementacion_cobrado_actual, 0) AS implementacion_cobrado_actual,
                    IFNULL(tc.implementacion_pagado_actual, 0) AS implementacion_pagado_actual,
                    IFNULL(tc.implementacion_saldo_actual, 0) AS implementacion_saldo_actual,
                    IFNULL(tc.implementacion_cobrado_anterior, 0) AS implementacion_cobrado_anterior,
                    IFNULL(tc.implementacion_pagado_anterior, 0) AS implementacion_pagado_anterior,
                    IFNULL(tc.implementacion_saldo_anterior, 0) AS implementacion_saldo_anterior,
                    IFNULL(tc.suscripcion_cobrado_actual, 0) AS suscripcion_cobrado_actual,
                    IFNULL(tc.suscripcion_pagado_actual, 0) AS suscripcion_pagado_actual,
                    IFNULL(tc.suscripcion_saldo_actual, 0) AS suscripcion_saldo_actual,
                    IFNULL(tc.suscripcion_cobrado_anterior, 0) AS suscripcion_cobrado_anterior,
                    IFNULL(tc.suscripcion_pagado_anterior, 0) AS suscripcion_pagado_anterior,
                    IFNULL(tc.suscripcion_saldo_anterior, 0) AS suscripcion_saldo_anterior,
                    IFNULL(tc.almuerzo_cobrado_actual, 0) AS almuerzo_cobrado_actual,
                    IFNULL(tc.almuerzo_pagado_actual, 0) AS almuerzo_pagado_actual,
                    IFNULL(tc.almuerzo_saldo_actual, 0) AS almuerzo_saldo_actual,
                    IFNULL(tc.almuerzo_cobrado_anterior, 0) AS almuerzo_cobrado_anterior,
                    IFNULL(tc.almuerzo_pagado_anterior, 0) AS almuerzo_pagado_anterior,
                    IFNULL(tc.almuerzo_saldo_anterior, 0) AS almuerzo_saldo_anterior,
                    IFNULL(tc.onces_cobrado_actual, 0) AS onces_cobrado_actual,
                    IFNULL(tc.onces_pagado_actual, 0) AS onces_pagado_actual,
                    IFNULL(tc.onces_saldo_actual, 0) AS onces_saldo_actual,
                    IFNULL(tc.onces_cobrado_anterior, 0) AS onces_cobrado_anterior,
                    IFNULL(tc.onces_pagado_anterior, 0) AS onces_pagado_anterior,
                    IFNULL(tc.onces_saldo_anterior, 0) AS onces_saldo_anterior,
                    IFNULL(tc.horas_extras_cobrado_actual, 0) AS horas_extras_cobrado_actual,
                    IFNULL(tc.horas_extras_pagado_actual, 0) AS horas_extras_pagado_actual,
                    IFNULL(tc.horas_extras_saldo_actual, 0) AS horas_extras_saldo_actual,
                    IFNULL(tc.horas_extras_cobrado_anterior, 0) AS horas_extras_cobrado_anterior,
                    IFNULL(tc.horas_extras_pagado_anterior, 0) AS horas_extras_pagado_anterior,
                    IFNULL(tc.horas_extras_saldo_anterior, 0) AS horas_extras_saldo_anterior,
                    IFNULL(tc.vestuario_cobrado_actual, 0) AS vestuario_cobrado_actual,
                    IFNULL(tc.vestuario_pagado_actual, 0) AS vestuario_pagado_actual,
                    IFNULL(tc.vestuario_saldo_actual, 0) AS vestuario_saldo_actual,
                    IFNULL(tc.vestuario_cobrado_anterior, 0) AS vestuario_cobrado_anterior,
                    IFNULL(tc.vestuario_pagado_anterior, 0) AS vestuario_pagado_anterior,
                    IFNULL(tc.vestuario_saldo_anterior, 0) AS vestuario_saldo_anterior,

                    /* Saldo vencido por concepto */
                    IFNULL(tc.implementacion_vencido, 0) AS implementacion_vencido,
                    IFNULL(tc.suscripcion_vencido, 0) AS suscripcion_vencido,
                    IFNULL(tc.almuerzo_vencido, 0) AS almuerzo_vencido,
                    IFNULL(tc.onces_vencido, 0) AS onces_vencido,
                    IFNULL(tc.horas_extras_vencido, 0) AS horas_extras_vencido,
                    IFNULL(tc.vestuario_vencido, 0) AS vestuario_vencido,

                    /* Representantes como texto */
                    IFNULL((
                        SELECT GROUP_CONCAT(
                            CONCAT(
                                IFNULL(pa.primer_nombre, ''), ' ', 
                                IFNULL(pa.segundo_nombre, ''), ' ', 
                                IFNULL(pa.primer_apellido, ''), ' ', 
                                IFNULL(pa.segundo_apellido, ''), ' - ', 
                                ta.nombre
                            ) SEPARATOR '; '
                        )
                        FROM representantes a
                        INNER JOIN personas pa ON a.id_persona = pa.id
                        INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
                        WHERE a.id_cliente = e.id
                    ), 'Sin representantes registrados') AS representantes,

                    /* Documentos pendientes */
                    IFNULL((
                        SELECT COUNT(*)
                        FROM tipos_personas_documentos tpd
                        INNER JOIN tipos_documentos td ON tpd.id_tipo_documento = td.id
                        INNER JOIN tipos_personas tp ON tp.id = tpd.id_tipo_persona AND tp.id_tenant = tpd.id_tenant
                        WHERE tp.codigo = 'cliente'
                        AND tpd.id_tenant = " . TenantContext::id() . "
                        AND tpd.obligatorio = 1
                        AND td.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp
                            WHERE dp.id_persona = e.id_persona
                            AND dp.id_tipo_documento = tpd.id_tipo_documento
                            AND dp.activo = 1
                        )
                    ), 0) AS docs_pendientes_cantidad,

                    IFNULL((
                        SELECT GROUP_CONCAT(td2.nombre ORDER BY tpd2.orden ASC SEPARATOR ', ')
                        FROM tipos_personas_documentos tpd2
                        INNER JOIN tipos_documentos td2 ON tpd2.id_tipo_documento = td2.id
                        INNER JOIN tipos_personas tp2 ON tp2.id = tpd2.id_tipo_persona AND tp2.id_tenant = tpd2.id_tenant
                        WHERE tp2.codigo = 'cliente'
                        AND tpd2.id_tenant = " . TenantContext::id() . "
                        AND tpd2.obligatorio = 1
                        AND td2.activo = 1
                        AND NOT EXISTS (
                            SELECT 1 FROM documentos_personas dp2
                            WHERE dp2.id_persona = e.id_persona
                            AND dp2.id_tipo_documento = tpd2.id_tipo_documento
                            AND dp2.activo = 1
                        )
                    ), '') AS docs_pendientes_detalle

                FROM clientes e
                INNER JOIN personas p ON e.id_persona = p.id
                INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
                LEFT JOIN generos g ON p.id_genero = g.id
                LEFT JOIN clientes_x_planes eg ON e.id = eg.id_cliente AND eg.activo = 1
                LEFT JOIN planes grp ON eg.id_plan = grp.id
                LEFT JOIN tmp_cartera tc ON tc.id_persona = e.id_persona
                WHERE e.id_tenant = :id_tenant
                ORDER BY grp.orden, p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $clientes = $sentence->fetchAll(PDO::FETCH_ASSOC);

            // Limpiar datos
            if (is_array($clientes)) {
                foreach ($clientes as &$row) {
                    if (isset($row['nombre_completo'])) {
                        $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
                    }
                    $row['telefono_emergencia'] = isset($row['telefono_emergencia']) && $row['telefono_emergencia'] ? $row['telefono_emergencia'] : '';
                    $row['eps'] = isset($row['eps']) && $row['eps'] ? $row['eps'] : '';
                    $row['direccion'] = isset($row['direccion']) && $row['direccion'] ? $row['direccion'] : '';
                }
            }

            // Representantes desglosados con teléfono y correo
            $stmtAcud = $db->prepare("
                SELECT 
                    a.id AS id_representante,
                    a.id_cliente,
                    e.id_persona AS id_persona,
                    TRIM(CONCAT(
                        IFNULL(pest.primer_nombre, ''), ' ', 
                        IFNULL(pest.segundo_nombre, ''), ' ', 
                        IFNULL(pest.primer_apellido, ''), ' ', 
                        IFNULL(pest.segundo_apellido, '')
                    )) AS nombre_cliente,
                    a.id_tipo_representante,
                    ta.nombre AS nombre_tipo_representante,
                    a.id_persona AS id_persona_representante,
                    TRIM(CONCAT(
                        IFNULL(pa.primer_nombre, ''), ' ', 
                        IFNULL(pa.segundo_nombre, ''), ' ', 
                        IFNULL(pa.primer_apellido, ''), ' ', 
                        IFNULL(pa.segundo_apellido, '')
                    )) AS nombre_representante,
                    IFNULL(pa.telefono, '') AS telefono,
                    IFNULL(pa.correo_electronico, '') AS correo_electronico
                FROM representantes a
                INNER JOIN clientes e ON a.id_cliente = e.id
                INNER JOIN personas pest ON e.id_persona = pest.id
                INNER JOIN personas pa ON a.id_persona = pa.id
                INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
                WHERE a.activo = 1
                AND a.id_tenant = :id_tenant
                ORDER BY e.id, ta.nombre
            ");

            $stmtAcud->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtAcud->execute();
            $representantes = $stmtAcud->fetchAll(PDO::FETCH_ASSOC);

            /* Limpiar temporales */
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cobrado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_pagado");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_vencido");
            $db->exec("DROP TEMPORARY TABLE IF EXISTS tmp_cartera");

            Flight::json([
                'fecha_generacion' => date('Y-m-d H:i:s'),
                'clientes' => $clientes,
                'representantes' => $representantes
            ]);
        } catch (Exception $e) {
            error_log("Error en getReporteRecordatorios: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el reporte para recordatorios: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Lee la foto/archivo de un registro civil colombiano con IA y devuelve los
     * datos del niño y de sus padres en JSON, para prellenar el registro rápido.
     * NO crea nada en la BD: solo lee y devuelve. El usuario revisa y completa
     * en el asistente antes de guardar (registroRapidoCompleto).
     *
     * POST /clientes/analizar-registro-civil  (multipart, campo 'registro_civil')
     *
     * La cadena de proveedores, reintentos y registro de uso los maneja IaVision;
     * aquí solo se arma el prompt e interpreta el texto (mismo patrón que
     * Pagos::analizarComprobante).
     */
    public static function analizarRegistroCivil()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.administrar');

        try {
            if (!isset($_FILES['registro_civil']) || $_FILES['registro_civil']['error'] !== UPLOAD_ERR_OK) {
                Flight::json(array('error' => 'No se recibió el archivo o hubo un error al subirlo'), 400);
                return;
            }

            $archivo = $_FILES['registro_civil'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

            $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
            if (!in_array($extension, $extensiones_permitidas)) {
                Flight::json(array('error' => 'Solo se permiten archivos PDF, JPG, JPEG o PNG'), 400);
                return;
            }

            if ($archivo['size'] > 10 * 1024 * 1024) {
                Flight::json(array('error' => 'El archivo excede el tamaño máximo de 10MB'), 400);
                return;
            }

            // Configuración de IA del tenant: se cargan todas las claves en un solo arreglo.
            $db = Flight::db();
            $stmtConfig = $db->prepare("SELECT clave, valor FROM ia_configuracion WHERE id_tenant = :id_tenant");
            $stmtConfig->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtConfig->execute();
            $config = array();
            foreach ($stmtConfig->fetchAll(PDO::FETCH_ASSOC) as $filaConfig) {
                $config[$filaConfig['clave']] = $filaConfig['valor'];
            }

            if (empty($config['gemini_api_key'])) {
                Flight::json(array('error' => 'API Key de Gemini no configurada en ia_configuracion'), 500);
                return;
            }

            if (isset($config['estado_servicio']) && $config['estado_servicio'] !== 'activo') {
                Flight::json(array('error' => 'El servicio de IA se encuentra pausado o en mantenimiento'), 503);
                return;
            }

            // Preparar el archivo para la IA.
            $contenidoArchivo = file_get_contents($archivo['tmp_name']);
            $base64 = base64_encode($contenidoArchivo);
            $esPdf = ($extension === 'pdf');
            $mimeType = $esPdf ? 'application/pdf' : 'image/' . ($extension === 'jpg' ? 'jpeg' : $extension);

            // El registro civil colombiano trae al inscrito (el niño) y a sus padres.
            // Del niño se toma el NUIP (número único de identificación personal, arriba
            // del documento), NO el serial/indicativo. El sexo y los tipos de documento
            // se resuelven a id en el backend por nombre; aquí solo se extrae el texto.
            $prompt = "Analiza este REGISTRO CIVIL DE NACIMIENTO colombiano y extrae ÚNICAMENTE los siguientes datos en formato JSON estricto. "
                . "No incluyas explicaciones ni texto adicional, SOLO el JSON:\n\n"
                . "{\n"
                . "  \"nino\": {\n"
                . "    \"primer_nombre\": (string o null),\n"
                . "    \"segundo_nombre\": (string o null),\n"
                . "    \"primer_apellido\": (string o null),\n"
                . "    \"segundo_apellido\": (string o null),\n"
                . "    \"numero_identificacion\": (string con el NUIP del inscrito, solo dígitos, o null),\n"
                . "    \"fecha_nacimiento\": (string en formato YYYY-MM-DD o null),\n"
                . "    \"sexo\": (\"Masculino\", \"Femenino\" o null)\n"
                . "  },\n"
                . "  \"padre\": {\n"
                . "    \"primer_nombre\": (string o null),\n"
                . "    \"segundo_nombre\": (string o null),\n"
                . "    \"primer_apellido\": (string o null),\n"
                . "    \"segundo_apellido\": (string o null),\n"
                . "    \"numero_identificacion\": (string con el documento del padre, solo dígitos, o null)\n"
                . "  },\n"
                . "  \"madre\": {\n"
                . "    \"primer_nombre\": (string o null),\n"
                . "    \"segundo_nombre\": (string o null),\n"
                . "    \"primer_apellido\": (string o null),\n"
                . "    \"segundo_apellido\": (string o null),\n"
                . "    \"numero_identificacion\": (string con el documento de la madre, solo dígitos, o null)\n"
                . "  }\n"
                . "}\n\n"
                . "Reglas:\n"
                . "- El NUIP es el número que aparece rotulado como 'NUIP' en la parte superior del documento (por ejemplo 1.072.680.919). NO uses el 'Serial' ni el 'Indicativo Serial'.\n"
                . "- Si un campo no aparece o no es legible, usa null.\n"
                . "- Si el padre o la madre no aparecen en el documento, devuelve ese objeto con todos sus campos en null.\n"
                . "- numero_identificacion: devuelve solo los dígitos, sin puntos ni espacios.";

            // Se sube el límite de tokens porque la respuesta trae niño + 2 padres,
            // más larga que un comprobante (que usa el default de 500 en IaVision).
            $resultado = IaVision::extraerDeImagen($config, $base64, $mimeType, $prompt, $esPdf, 1200);

            // Registro de uso por proveedor (best-effort; nunca rompe la lectura).
            IaVision::registrarUso($db, TenantContext::id(), $resultado);

            if (!$resultado['success']) {
                Flight::json(array('error' => 'No se pudo analizar el registro civil con ningún proveedor de IA: ' . $resultado['error']), 503);
                return;
            }

            // Limpiar posibles cercos de código markdown y decodificar el JSON.
            $textoRespuesta = $resultado['texto'];
            $textoRespuesta = preg_replace('/```json\s*/', '', $textoRespuesta);
            $textoRespuesta = preg_replace('/```\s*/', '', $textoRespuesta);
            $textoRespuesta = trim($textoRespuesta);

            $datosExtraidos = json_decode($textoRespuesta, true);

            if (!$datosExtraidos) {
                Flight::json(array(
                    'error' => 'No se pudieron extraer los datos del registro civil',
                    'respuesta_ia' => $textoRespuesta
                ), 422);
                return;
            }

            // Registrar uso: contador de mensajes y acumulado de tokens consumidos.
            $stmtContador = $db->prepare("UPDATE ia_configuracion SET valor = valor + 1, fecha_actualizacion = NOW() WHERE clave = 'mensajes_generados_hoy' AND id_tenant = :id_tenant");
            $stmtContador->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtContador->execute();

            $tokensTotal = $resultado['tokens']['total'];
            if ($tokensTotal > 0) {
                $stmtTokens = $db->prepare("UPDATE ia_configuracion SET valor = valor + :tokens, fecha_actualizacion = NOW() WHERE clave = 'tokens_consumidos_hoy' AND id_tenant = :id_tenant");
                $stmtTokens->bindParam(':tokens', $tokensTotal);
                $stmtTokens->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtTokens->execute();
            }

            Flight::json(array(
                'success' => true,
                'datos' => $datosExtraidos,
                'proveedor' => $resultado['proveedor']
            ));
        } catch (Exception $e) {
            error_log("Error en analizarRegistroCivil: " . $e->getMessage());
            Flight::json(array('error' => 'Error interno al procesar el registro civil: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Registro rápido COMPLETO desde el asistente de registro civil.
     * En una sola transacción crea: persona del niño, cliente, asignación de
     * plan (con grado opcional), horarios del cliente, y por cada representante
     * presente su persona + representante. Los usuarios del portal de padres NO se
     * crean aquí: el front los crea en un segundo llamado a POST /usuarios con los
     * id_persona que devuelve este método.
     *
     * A diferencia de registroRapido() (usado por el módulo de asistencia, un solo
     * representante y sin grado/horario), este método asigna grado y horarios y admite
     * varios representantes. registroRapido() NO se modifica.
     *
     * POST /clientes/registro-rapido-completo
     *
     * Body (JSON): {
     *   nino: { id_tipo_identificacion, numero_identificacion, primer_nombre,
     *           segundo_nombre, primer_apellido, segundo_apellido,
     *           fecha_nacimiento, id_genero, fecha_ingreso },
     *   id_plan, anno (opcional),
     *   horarios: [ { id_dia_semana, hora_entrada, hora_salida } ] (opcional),
     *   representantes: [ {
     *       id_tipo_identificacion, numero_identificacion, primer_nombre,
     *       segundo_nombre, primer_apellido, segundo_apellido,
     *       telefono, correo_electronico, id_tipo_representante,
     *       es_responsable_pago, autorizado_recoger, autorizado_sistema
     *   } ]
     * }
     */
    public static function registroRapidoCompleto()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'clientes.administrar');

            $data = Flight::request()->data;

            $nino = isset($data['nino']) ? $data['nino'] : null;
            $id_plan = isset($data['id_plan']) ? $data['id_plan'] : null;
            $anno = isset($data['anno']) && $data['anno'] ? $data['anno'] : date('Y');
            $horarios = isset($data['horarios']) ? $data['horarios'] : array();
            $representantes = isset($data['representantes']) ? $data['representantes'] : array();

            // Validaciones mínimas: el niño y su documento son obligatorios (como toda
            // persona), y debe venir al menos un representante. El plan es obligatorio
            // porque el cliente nace asignado a un plan.
            if (!$nino || empty($nino['numero_identificacion'])) {
                Flight::json(array('error' => 'Faltan los datos del niño o su número de identificación'), 400);
                return;
            }
            if (empty($nino['id_tipo_identificacion'])) {
                Flight::json(array('error' => 'Falta el tipo de identificación del niño'), 400);
                return;
            }
            if (!$id_plan) {
                Flight::json(array('error' => 'Debe seleccionar un plan para el cliente'), 400);
                return;
            }
            if (!is_array($representantes) || count($representantes) === 0) {
                Flight::json(array('error' => 'Debe registrar al menos un representante'), 400);
                return;
            }
            foreach ($representantes as $ac) {
                if (empty($ac['numero_identificacion']) || empty($ac['id_tipo_identificacion'])) {
                    Flight::json(array('error' => 'Cada representante debe tener tipo y número de identificación'), 400);
                    return;
                }
            }

            $db->beginTransaction();

            $idTenant = TenantContext::id();

            // ============================================================
            // 1. PERSONA DEL NIÑO: buscar o crear
            // ============================================================
            $id_persona_nino = self::buscarOCrearPersona($db, $idTenant, array(
                'id_tipo_identificacion' => $nino['id_tipo_identificacion'],
                'numero_identificacion'  => $nino['numero_identificacion'],
                'primer_nombre'          => isset($nino['primer_nombre']) ? $nino['primer_nombre'] : null,
                'segundo_nombre'         => isset($nino['segundo_nombre']) ? $nino['segundo_nombre'] : null,
                'primer_apellido'        => isset($nino['primer_apellido']) ? $nino['primer_apellido'] : null,
                'segundo_apellido'       => isset($nino['segundo_apellido']) ? $nino['segundo_apellido'] : null,
                'fecha_nacimiento'       => isset($nino['fecha_nacimiento']) ? $nino['fecha_nacimiento'] : null,
                'id_genero'              => isset($nino['id_genero']) ? $nino['id_genero'] : null,
                'nacionalidad'           => 'Colombiana',
                'ocupacion'              => 'Cliente',
                'telefono'               => null,
                'correo_electronico'     => null,
            ));

            // ============================================================
            // 2. CLIENTE: verificar que no exista, crear
            // ============================================================
            $stmt = $db->prepare("SELECT id, activo FROM clientes WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->bindParam(':id_persona', $id_persona_nino);
            $stmt->execute();
            $clienteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

            $cliente_ya_existia = false;
            if ($clienteExistente) {
                if ($clienteExistente['activo'] == 0) {
                    $db->rollBack();
                    Flight::json(array('error' => 'Este cliente existe pero está inactivo. Actívelo primero desde el módulo de clientes.'), 400);
                    return;
                }
                $id_cliente = $clienteExistente['id'];
                $cliente_ya_existia = true;
            } else {
                $fecha_ingreso = (isset($nino['fecha_ingreso']) && $nino['fecha_ingreso']) ? $nino['fecha_ingreso'] : date('Y-m-d');
                $idCliente = Uuid::generar();
                $stmt = $db->prepare("INSERT INTO clientes (id, id_tenant, id_persona, fecha_ingreso, activo, alimentacion, permanente, telefono_emergencia, eps, anno)
                    VALUES (:id, :id_tenant, :id_persona, :fecha_ingreso, 1, 0, 0, '', '', :anno)");
                $stmt->bindValue(':id', $idCliente);
                $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stmt->bindParam(':id_persona', $id_persona_nino);
                $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
                $stmt->bindParam(':anno', $anno);
                $stmt->execute();
                $id_cliente = $idCliente;
            }

            // ============================================================
            // 3. ASIGNAR PLAN Y GRADO (si no tiene uno activo)
            // ============================================================
            $stmt = $db->prepare("SELECT id FROM clientes_x_planes WHERE id_cliente = :id_cliente AND activo = 1 AND id_tenant = :id_tenant");
            $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
            $stmt->bindParam(':id_cliente', $id_cliente);
            $stmt->execute();
            $planActual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$planActual) {
                $stmt = $db->prepare("INSERT INTO clientes_x_planes (id_tenant, id_cliente, id_plan, anio, activo)
                    VALUES (:id_tenant, :id_cliente, :id_plan, :anio, 1)");
                $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stmt->bindParam(':id_cliente', $id_cliente);
                $stmt->bindParam(':id_plan', $id_plan);
                $stmt->bindParam(':anio', $anno);
                $stmt->execute();
            }

            // ============================================================
            // 5. REPRESENTANTES: persona + representante + usuario del portal
            // ============================================================
            $representantesCreados = array();
            foreach ($representantes as $ac) {
                // 5.1 Persona del representante (buscar o crear)
                $id_persona_acud = self::buscarOCrearPersona($db, $idTenant, array(
                    'id_tipo_identificacion' => $ac['id_tipo_identificacion'],
                    'numero_identificacion'  => $ac['numero_identificacion'],
                    'primer_nombre'          => isset($ac['primer_nombre']) ? $ac['primer_nombre'] : null,
                    'segundo_nombre'         => isset($ac['segundo_nombre']) ? $ac['segundo_nombre'] : null,
                    'primer_apellido'        => isset($ac['primer_apellido']) ? $ac['primer_apellido'] : null,
                    'segundo_apellido'       => isset($ac['segundo_apellido']) ? $ac['segundo_apellido'] : null,
                    'fecha_nacimiento'       => null,
                    'id_genero'              => null,
                    'nacionalidad'           => 'Colombiana',
                    'ocupacion'              => null,
                    'telefono'               => isset($ac['telefono']) ? $ac['telefono'] : null,
                    'correo_electronico'     => isset($ac['correo_electronico']) ? $ac['correo_electronico'] : null,
                ));

                // 5.2 Representante (buscar duplicado por cliente+persona+tipo, o crear)
                $id_tipo_representante = isset($ac['id_tipo_representante']) ? $ac['id_tipo_representante'] : null;
                $es_responsable_pago = isset($ac['es_responsable_pago']) ? intval($ac['es_responsable_pago']) : 1;
                $autorizado_recoger = isset($ac['autorizado_recoger']) ? intval($ac['autorizado_recoger']) : 1;
                $autorizado_sistema = isset($ac['autorizado_sistema']) ? intval($ac['autorizado_sistema']) : 1;

                $stmt = $db->prepare("SELECT id FROM representantes WHERE id_cliente = :id_cliente AND id_persona = :id_persona AND id_tipo_representante = :id_tipo_representante AND id_tenant = :id_tenant");
                $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $stmt->bindParam(':id_cliente', $id_cliente);
                $stmt->bindParam(':id_persona', $id_persona_acud);
                $stmt->bindValue(':id_tipo_representante', $id_tipo_representante);
                $stmt->execute();
                $representanteExistente = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($representanteExistente) {
                    $id_representante = $representanteExistente['id'];
                } else {
                    $idRepresentante = Uuid::generar();
                    $stmt = $db->prepare("INSERT INTO representantes (id, id_tenant, id_cliente, id_persona, id_tipo_representante, es_responsable_pago, autorizado_recoger, autorizado_sistema, activo)
                        VALUES (:id, :id_tenant, :id_cliente, :id_persona, :id_tipo_representante, :es_responsable_pago, :autorizado_recoger, :autorizado_sistema, 1)");
                    $stmt->bindValue(':id', $idRepresentante);
                    $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                    $stmt->bindParam(':id_cliente', $id_cliente);
                    $stmt->bindParam(':id_persona', $id_persona_acud);
                    $stmt->bindValue(':id_tipo_representante', $id_tipo_representante);
                    $stmt->bindParam(':es_responsable_pago', $es_responsable_pago);
                    $stmt->bindParam(':autorizado_recoger', $autorizado_recoger);
                    $stmt->bindParam(':autorizado_sistema', $autorizado_sistema);
                    $stmt->execute();
                    $id_representante = $idRepresentante;
                }

                // El usuario del portal de padres NO se crea aquí: lo crea el front en
                // un segundo llamado a POST /usuarios (Usuarios::new) por cada representante,
                // con usuario y clave = numero_identificacion. Por eso se devuelve el
                // id_persona y el numero_identificacion de cada representante.
                $representantesCreados[] = array(
                    'id_representante' => $id_representante,
                    'id_persona' => $id_persona_acud,
                    'numero_identificacion' => $ac['numero_identificacion'],
                    'correo_electronico' => isset($ac['correo_electronico']) ? $ac['correo_electronico'] : null
                );
            }

            $db->commit();

            Flight::json(array(
                'id_cliente' => $id_cliente,
                'id_persona_nino' => $id_persona_nino,
                'cliente_ya_existia' => $cliente_ya_existia,
                'representantes' => $representantesCreados,
                'nombre_cliente' => trim(
                    (isset($nino['primer_nombre']) ? $nino['primer_nombre'] : '') . ' ' .
                    (isset($nino['primer_apellido']) ? $nino['primer_apellido'] : '')
                )
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en registroRapidoCompleto: " . $e->getMessage());
            Flight::json(array('error' => 'Error en registro rápido completo: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Busca una persona por tipo y número de identificación dentro del tenant; si
     * existe devuelve su id (y completa teléfono/correo si estaban vacíos y llegan
     * ahora), y si no existe la crea. Usada por registroRapidoCompleto para no
     * duplicar personas al registrar niño y representantes.
     *
     * @param PDO   $db
     * @param int   $idTenant
     * @param array $p Campos de la persona (ver INSERT abajo).
     * @return string id (UUID) de la persona.
     */
    private static function buscarOCrearPersona($db, $idTenant, $p)
    {
        $stmt = $db->prepare("SELECT id FROM personas WHERE id_tipo_identificacion = :tipo AND numero_identificacion = :numero AND id_tenant = :id_tenant");
        $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $p['id_tipo_identificacion']);
        $stmt->bindValue(':numero', $p['numero_identificacion']);
        $stmt->execute();
        $existente = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existente) {
            // Completar teléfono/correo solo si estaban vacíos, para no pisar datos
            // que ya tuviera la persona.
            if (!empty($p['telefono'])) {
                $up = $db->prepare("UPDATE personas SET telefono = :telefono WHERE id = :id AND id_tenant = :id_tenant AND (telefono IS NULL OR telefono = '')");
                $up->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $up->bindValue(':telefono', $p['telefono']);
                $up->bindValue(':id', $existente['id']);
                $up->execute();
            }
            if (!empty($p['correo_electronico'])) {
                $up = $db->prepare("UPDATE personas SET correo_electronico = :correo WHERE id = :id AND id_tenant = :id_tenant AND (correo_electronico IS NULL OR correo_electronico = '')");
                $up->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
                $up->bindValue(':correo', $p['correo_electronico']);
                $up->bindValue(':id', $existente['id']);
                $up->execute();
            }
            return $existente['id'];
        }

        $id = Uuid::generar();
        $stmt = $db->prepare("INSERT INTO personas (
                id, id_tenant, primer_nombre, segundo_nombre, primer_apellido, segundo_apellido,
                id_tipo_identificacion, numero_identificacion, nacionalidad, fecha_nacimiento,
                id_genero, correo_electronico, telefono, ocupacion
            ) VALUES (
                :id, :id_tenant, :primer_nombre, :segundo_nombre, :primer_apellido, :segundo_apellido,
                :id_tipo_identificacion, :numero_identificacion, :nacionalidad, :fecha_nacimiento,
                :id_genero, :correo_electronico, :telefono, :ocupacion
            )");
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':id_tenant', $idTenant, PDO::PARAM_INT);
        $stmt->bindValue(':primer_nombre', $p['primer_nombre']);
        $stmt->bindValue(':segundo_nombre', $p['segundo_nombre']);
        $stmt->bindValue(':primer_apellido', $p['primer_apellido']);
        $stmt->bindValue(':segundo_apellido', $p['segundo_apellido']);
        $stmt->bindValue(':id_tipo_identificacion', $p['id_tipo_identificacion']);
        $stmt->bindValue(':numero_identificacion', $p['numero_identificacion']);
        $stmt->bindValue(':nacionalidad', isset($p['nacionalidad']) ? $p['nacionalidad'] : 'Colombiana');
        $stmt->bindValue(':fecha_nacimiento', $p['fecha_nacimiento']);
        $stmt->bindValue(':id_genero', $p['id_genero'] ? $p['id_genero'] : null);
        $stmt->bindValue(':correo_electronico', $p['correo_electronico']);
        $stmt->bindValue(':telefono', $p['telefono']);
        $stmt->bindValue(':ocupacion', $p['ocupacion']);
        $stmt->execute();

        return $id;
    }
}