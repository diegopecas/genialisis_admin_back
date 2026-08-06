<?php
class RegistrosLimpieza
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                rl.*,
                af.nombre as nombre_area,
                tpl.nombre as nombre_proceso,
                erl.nombre as nombre_estado,
                erl.color as color_estado,
                CONCAT_WS(' ', ue.primer_nombre, ue.primer_apellido) as nombre_ejecutor,
                CONCAT_WS(' ', us.primer_nombre, us.primer_apellido) as nombre_supervisor,
                (SELECT COUNT(*) FROM registros_limpieza_detalle WHERE id_registro_limpieza = rl.id) as total_elementos,
                (SELECT SUM(cantidad_consumida) FROM registros_limpieza_consumos WHERE id_registro_limpieza = rl.id) as total_productos_consumidos,
                DATE_FORMAT(rl.fecha_programada, '%d/%m/%Y') as fecha_programada_formateada
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tpl ON rl.id_tipo_proceso_limpieza = tpl.id
            INNER JOIN estados_registro_limpieza erl ON rl.id_estado = erl.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas ue ON ue_user.id_persona = ue.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas us ON us_user.id_persona = us.id
            WHERE rl.id_tenant = :id_tenant
            ORDER BY rl.fecha_programada DESC, rl.id DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();

        // Obtener registro principal
        $sentence = $db->prepare("
            SELECT 
                rl.*,
                af.nombre as nombre_area,
                tpl.nombre as nombre_proceso,
                erl.nombre as nombre_estado,
                erl.color as color_estado,
                CONCAT_WS(' ', ue.primer_nombre, ue.primer_apellido) as nombre_ejecutor,
                CONCAT_WS(' ', us.primer_nombre, us.primer_apellido) as nombre_supervisor
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tpl ON rl.id_tipo_proceso_limpieza = tpl.id
            INNER JOIN estados_registro_limpieza erl ON rl.id_estado = erl.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas ue ON ue_user.id_persona = ue.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas us ON us_user.id_persona = us.id
            WHERE rl.id = :id AND rl.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro = $sentence->fetch();

        if (!$registro) {
            Flight::json(array('error' => 'Registro no encontrado'), 404);
            return;
        }

        // Obtener detalles de elementos
        $sentence = $db->prepare("
            SELECT 
                rld.*,
                ef.nombre as nombre_elemento,
                p.nombre as nombre_mobiliario
            FROM registros_limpieza_detalle rld
            LEFT JOIN elementos_fisicos ef ON rld.id_elemento_fisico = ef.id
            LEFT JOIN productos_mobiliario pm ON rld.id_producto_mobiliario = pm.id
            LEFT JOIN productos p ON pm.id_producto = p.id
            WHERE rld.id_registro_limpieza = :id AND rld.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro['detalles'] = $sentence->fetchAll();

        // Obtener consumos
        $sentence = $db->prepare("
            SELECT 
                rlc.*,
                p.nombre as nombre_producto,
                um.abreviatura as unidad
            FROM registros_limpieza_consumos rlc
            INNER JOIN productos_limpieza pl ON rlc.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON rlc.id_unidad_medida = um.id
            WHERE rlc.id_registro_limpieza = :id AND rlc.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registro['consumos'] = $sentence->fetchAll();

        Flight::json($registro);
    }

    public static function new()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_area_fisica = Flight::request()->data['id_area_fisica'];
            $id_tipo_proceso_limpieza = Flight::request()->data['id_tipo_proceso_limpieza'];
            // CAMBIO: usar fecha_programada
            $fecha_programada = Flight::request()->data['fecha_programada'] ?? date('Y-m-d');
            $id_usuario_ejecutor = Flight::request()->data['id_usuario_ejecutor'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;

            // Crear registro principal - CAMBIO: NO registrar fecha, solo fecha_programada
            $sentence = $db->prepare("
                INSERT INTO registros_limpieza (
                    id,
                    id_tenant,
                    id_area_fisica,
                    id_tipo_proceso_limpieza,
                    fecha_programada,
                    id_estado,
                    id_usuario_ejecutor,
                    observaciones
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_area_fisica,
                    :id_tipo_proceso_limpieza,
                    :fecha_programada,
                    1, -- Estado: Programado
                    :id_usuario_ejecutor,
                    :observaciones
                )
            ");

            $idRL = Uuid::generar();
            $sentence->bindValue(':id', $idRL);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_area_fisica', $id_area_fisica);
            $sentence->bindParam(':id_tipo_proceso_limpieza', $id_tipo_proceso_limpieza);
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':id_usuario_ejecutor', $id_usuario_ejecutor);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->execute();

            $id_registro = $idRL;

            // Insertar detalles si vienen
            if (isset(Flight::request()->data['detalles'])) {
                foreach (Flight::request()->data['detalles'] as $detalle) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            :id_producto_mobiliario
                        )
                    ");

                    $id_elemento = $detalle['id_elemento_fisico'] ?? null;
                    $id_mobiliario = $detalle['id_producto_mobiliario'] ?? null;

                    $idRLD = Uuid::generar();
                    $sentence->bindValue(':id', $idRLD);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_elemento_fisico', $id_elemento);
                    $sentence->bindParam(':id_producto_mobiliario', $id_mobiliario);
                    $sentence->execute();
                }
            }

            // Insertar consumos programados si vienen
            if (isset(Flight::request()->data['consumos'])) {
                foreach (Flight::request()->data['consumos'] as $consumo) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $idRLC = Uuid::generar();
                    $sentence->bindValue(':id', $idRLC);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_limpieza', $consumo['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $consumo['cantidad_consumida']);
                    $sentence->bindParam(':id_unidad_medida', $consumo['id_unidad_medida']);
                    $sentence->execute();
                }
            }

            $db->commit();
            Flight::json(array('id' => $id_registro));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function iniciar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $hora_inicio = date('H:i:s');
        $fecha_actual = date('Y-m-d'); // CAMBIO: registrar fecha real de inicio

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET hora_inicio = :hora_inicio,
                fecha = :fecha,  -- CAMBIO: registrar fecha real
                id_estado = 2 -- En Proceso
            WHERE id = :id AND id_estado = 1 AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':hora_inicio', $hora_inicio);
        $sentence->bindParam(':fecha', $fecha_actual);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'No se pudo iniciar el registro'), 400);
            return;
        }

        Flight::json(array(
            'success' => true,
            'hora_inicio' => $hora_inicio,
            'fecha' => $fecha_actual
        ));
    }

    public static function finalizar()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $hora_fin = date('H:i:s');
            $id_usuario = Flight::request()->data['id_usuario'];

            // Obtener los consumos programados
            $sentence = $db->prepare("
                SELECT 
                    rlc.*,
                    pl.id_producto,
                    p.stock_actual,
                    p.nombre as nombre_producto
                FROM registros_limpieza_consumos rlc
                INNER JOIN productos_limpieza pl ON rlc.id_producto_limpieza = pl.id
                INNER JOIN productos p ON pl.id_producto = p.id
                WHERE rlc.id_registro_limpieza = :id AND rlc.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $consumos = $sentence->fetchAll();

            if (count($consumos) == 0) {
                throw new Exception('No hay productos para consumir');
            }

            // Crear movimiento de salida
            $sentence = $db->prepare("
                INSERT INTO movimientos_productos (
                    id,
                    id_tenant,
                    fecha_movimiento,
                    id_concepto_movimiento,
                    id_estado,
                    observaciones,
                    id_usuario_registro
                ) VALUES (
                    :id,
                    :id_tenant,
                    NOW(),
                    (SELECT id FROM conceptos_movimiento WHERE nombre = 'Salida por Limpieza' AND id_tenant = " . TenantContext::id() . " LIMIT 1),
                    3, -- Aprobado
                    CONCAT('Registro de limpieza #', :id),
                    :id_usuario
                )
            ");
            $idMov = Uuid::generar();
            $sentence->bindValue(':id', $idMov);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->execute();

            $id_movimiento = $idMov;

            // Variables para tracking
            $productos_ajustados = [];

            // Crear detalles del movimiento y actualizar stock
            foreach ($consumos as $consumo) {
                // CAMBIO: Validar stock y ajustar cantidad si es necesario
                $cantidad_a_descontar = $consumo['cantidad_consumida'];
                $stock_disponible = $consumo['stock_actual'];

                // Si no hay suficiente stock, usar solo lo disponible
                if ($cantidad_a_descontar > $stock_disponible) {
                    $cantidad_a_descontar = $stock_disponible;
                    $productos_ajustados[] = array(
                        'producto' => $consumo['nombre_producto'],
                        'solicitado' => $consumo['cantidad_consumida'],
                        'disponible' => $stock_disponible,
                        'descontado' => $cantidad_a_descontar
                    );
                }

                // Si no hay stock, continuar con el siguiente producto
                if ($cantidad_a_descontar <= 0) {
                    error_log("Producto sin stock: " . $consumo['nombre_producto']);
                    continue;
                }

                // Insertar detalle del movimiento con la cantidad ajustada
                $sentence = $db->prepare("
                    INSERT INTO movimientos_productos_detalle (
                        id,
                        id_tenant,
                        id_movimiento,
                        id_producto,
                        cantidad,
                        stock_anterior,
                        precio_unitario
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :id_movimiento,
                        :id_producto,
                        :cantidad,
                        :stock_anterior,
                        (SELECT precio_unitario FROM productos WHERE id = :id_producto2)
                    )
                ");

                $idMovDet = Uuid::generar();
                $sentence->bindValue(':id', $idMovDet);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_movimiento', $id_movimiento);
                $sentence->bindParam(':id_producto', $consumo['id_producto']);
                $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                $sentence->bindParam(':stock_anterior', $consumo['stock_actual']);
                $sentence->bindParam(':id_producto2', $consumo['id_producto']);
                $sentence->execute();

                $id_movimiento_detalle = $idMovDet;

                // Actualizar stock del producto - NUNCA permitir negativos
                $sentence = $db->prepare("
                    UPDATE productos 
                    SET stock_anterior = stock_actual,
                        stock_actual = GREATEST(0, stock_actual - :cantidad), -- CAMBIO: usar GREATEST para evitar negativos
                        id_ultimo_movimiento = :id_movimiento,
                        fecha_ultimo_movimiento = NOW()
                    WHERE id = :id_producto AND id_tenant = :id_tenant
                ");

                $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                $sentence->bindParam(':id_movimiento', $id_movimiento);
                $sentence->bindParam(':id_producto', $consumo['id_producto']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();

                // Actualizar referencia en consumos
                $sentence = $db->prepare("
                    UPDATE registros_limpieza_consumos 
                    SET id_movimiento_inventario = :id_movimiento_detalle
                    WHERE id = :id_consumo AND id_tenant = :id_tenant
                ");

                $sentence->bindParam(':id_movimiento_detalle', $id_movimiento_detalle);
                $sentence->bindParam(':id_consumo', $consumo['id']);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
            }

            // Actualizar registro de limpieza
            $sentence = $db->prepare("
                UPDATE registros_limpieza 
                SET hora_fin = :hora_fin,
                    id_estado = 3 -- Realizado
                WHERE id = :id AND id_estado = 2 AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':hora_fin', $hora_fin);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                throw new Exception('El registro debe estar en proceso para finalizar');
            }

            $db->commit();

            // Preparar respuesta
            $response = array(
                'success' => true,
                'hora_fin' => $hora_fin,
                'id_movimiento' => $id_movimiento
            );

            // Agregar información de ajustes si hubo
            if (count($productos_ajustados) > 0) {
                $response['productos_ajustados'] = $productos_ajustados;
            }

            Flight::json($response);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::finalizar: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function supervisar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_usuario_supervisor = Flight::request()->data['id_usuario_supervisor'];
        $observaciones = Flight::request()->data['observaciones'] ?? null;

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET id_estado = 4, -- Supervisado
                id_usuario_supervisor = :id_usuario_supervisor,
                observaciones = CONCAT(IFNULL(observaciones, ''), ' | Supervisión: ', :observaciones)
            WHERE id = :id AND id_estado = 3 AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':id_usuario_supervisor', $id_usuario_supervisor);
        $sentence->bindParam(':observaciones', $observaciones);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'El registro debe estar realizado para supervisar'), 400);
            return;
        }

        Flight::json(array('success' => true));
    }

    public static function cancelar()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $motivo = Flight::request()->data['motivo'] ?? 'Sin motivo especificado';

        $sentence = $db->prepare("
            UPDATE registros_limpieza 
            SET id_estado = 5, -- Cancelado
                observaciones = CONCAT(IFNULL(observaciones, ''), ' | Cancelado: ', :motivo)
            WHERE id = :id AND id_estado IN (1, 2) AND id_tenant = :id_tenant
        ");

        $sentence->bindParam(':motivo', $motivo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        if ($sentence->rowCount() == 0) {
            Flight::json(array('error' => 'Solo se pueden cancelar registros programados o en proceso'), 400);
            return;
        }

        Flight::json(array('success' => true));
    }


    public static function getElementosParaProceso()
    {
        $db = Flight::db();

        $id_area = Flight::request()->query['id_area'] ??
            Flight::request()->query->id_area ??
            $_GET['id_area'] ??
            null;

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        if (!$id_area || !$id_proceso) {
            Flight::json(array('error' => 'Faltan parámetros requeridos (id_area, id_proceso)'), 400);
            return;
        }

        $response = array(
            'elementos_fisicos' => [],
            'productos_mobiliario' => [],
            'productos_limpieza' => []
        );

        // Obtener elementos físicos que aplican al proceso con sus cantidades en el área
        $sentence = $db->prepare("
                        SELECT DISTINCT
                            ef.id,
                            ef.nombre,
                            ef.descripcion,
                            IFNULL(efaf.cantidad, 0) as cantidad_en_area
                        FROM elementos_fisicos ef
                        INNER JOIN elementos_fisicos_x_procesos_limpieza efpl ON ef.id = efpl.id_elemento_fisico
                        LEFT JOIN elementos_fisicos_x_areas_fisicas efaf 
                            ON ef.id = efaf.id_elemento_fisico 
                            AND efaf.id_area_fisica = :id_area
                        WHERE efpl.id_tipo_proceso_limpieza = :id_proceso AND ef.id_tenant = :id_tenant
                        ORDER BY ef.nombre
                    ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response['elementos_fisicos'] = $sentence->fetchAll();

        // Obtener mobiliario del área que aplica al proceso
        $sentence = $db->prepare("
                                SELECT DISTINCT
                                    pm.id,
                                    p.nombre,
                                    pmxa.cantidad,
                                    p.id as id_producto
                                FROM productos_mobiliario pm
                                INNER JOIN productos p ON pm.id_producto = p.id
                                INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
                                INNER JOIN productos_mobiliario_x_procesos_limpieza pmpl ON pm.id = pmpl.id_producto_mobiliario
                                WHERE pmxa.id_area = :id_area 
                                AND pmpl.id_tipo_proceso_limpieza = :id_proceso
                                AND p.activo = 1
                                AND pm.id_tenant = :id_tenant
                                ORDER BY p.nombre
                            ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response['productos_mobiliario'] = $sentence->fetchAll();

        // Calcular productos de limpieza necesarios
        $productos_temp = array();

        // PARTE 1: Productos para elementos físicos
        // cantidad_total = cantidad_sugerida * cantidad_del_elemento_en_el_area
        $sentence = $db->prepare("
                                SELECT 
                                    pl.id as id_producto_limpieza,
                                    p.id as id_producto,
                                    p.nombre,
                                    p.stock_actual,
                                    um.id as id_unidad_medida,
                                    um.abreviatura,
                                    efplp.cantidad_sugerida,
                                    IFNULL(efaf.cantidad, 0) as cantidad_elemento_area,
                                    ef.nombre as nombre_elemento,
                                    ef.id as id_elemento
                                FROM elementos_fisicos_x_procesos_limpieza efpl
                                INNER JOIN elementos_fisicos ef ON efpl.id_elemento_fisico = ef.id
                                INNER JOIN elementos_fisicos_x_procesos_limpieza_productos efplp 
                                    ON efpl.id = efplp.id_elementos_fisicos_x_procesos_limpieza
                                INNER JOIN productos_limpieza pl ON efplp.id_producto_limpieza = pl.id
                                INNER JOIN productos p ON pl.id_producto = p.id
                                INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                LEFT JOIN elementos_fisicos_x_areas_fisicas efaf 
                                    ON efpl.id_elemento_fisico = efaf.id_elemento_fisico 
                                    AND efaf.id_area_fisica = :id_area
                                WHERE efpl.id_tipo_proceso_limpieza = :id_proceso
                                AND p.activo = 1
                                AND efpl.id_tenant = :id_tenant
                            ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $elementosProductos = $sentence->fetchAll();

        // Procesar productos de elementos físicos
        foreach ($elementosProductos as $prod) {
            $key = $prod['id_producto_limpieza'];

            // Calcular cantidad: cantidad_sugerida * cantidad_del_elemento_en_area
            $cantidad_calculada = $prod['cantidad_sugerida'] * $prod['cantidad_elemento_area'];

            if (!isset($productos_temp[$key])) {
                $productos_temp[$key] = array(
                    'id_producto_limpieza' => $prod['id_producto_limpieza'],
                    'id_producto' => $prod['id_producto'],
                    'nombre' => $prod['nombre'],
                    'stock_actual' => $prod['stock_actual'],
                    'id_unidad_medida' => $prod['id_unidad_medida'],
                    'abreviatura' => $prod['abreviatura'],
                    'cantidad_total' => $cantidad_calculada,
                    'detalle_elementos' => array(),
                    'detalle_mobiliario' => array()
                );
            } else {
                $productos_temp[$key]['cantidad_total'] += $cantidad_calculada;
            }

            // Agregar detalle para debugging
            if ($cantidad_calculada > 0) {
                $productos_temp[$key]['detalle_elementos'][] = array(
                    'elemento' => $prod['nombre_elemento'],
                    'id_elemento' => $prod['id_elemento'],
                    'cantidad_sugerida' => $prod['cantidad_sugerida'],
                    'cantidad_area' => $prod['cantidad_elemento_area'],
                    'subtotal' => $cantidad_calculada
                );
            }
        }

        // PARTE 2: Productos para mobiliario del área
        if (count($response['productos_mobiliario']) > 0) {
            $ids_mobiliario = array_column($response['productos_mobiliario'], 'id');
            $placeholders = implode(',', array_fill(0, count($ids_mobiliario), '?'));

            $sql = "
                    SELECT 
                        pl.id as id_producto_limpieza,
                        p.id as id_producto,
                        p.nombre,
                        p.stock_actual,
                        um.id as id_unidad_medida,
                        um.abreviatura,
                        pmplp.cantidad_sugerida,
                        pm.id as id_mobiliario,
                        prod_mob.nombre as nombre_mobiliario
                    FROM productos_mobiliario_x_procesos_limpieza pmpl
                    INNER JOIN productos_mobiliario pm ON pmpl.id_producto_mobiliario = pm.id
                    INNER JOIN productos prod_mob ON pm.id_producto = prod_mob.id
                    INNER JOIN productos_mobiliario_x_procesos_limpieza_productos pmplp 
                        ON pmpl.id = pmplp.id_proceso_limpieza
                    INNER JOIN productos_mobiliario_x_productos_limpieza pmxpl 
                        ON pmplp.id_productos_mobiliario_x_productos_limpieza = pmxpl.id
                    INNER JOIN productos_limpieza pl ON pmxpl.id_producto_limpieza = pl.id
                    INNER JOIN productos p ON pl.id_producto = p.id
                    INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
                    WHERE pmpl.id_producto_mobiliario IN ($placeholders)
                    AND pmpl.id_tipo_proceso_limpieza = ?
                    AND p.activo = 1
                    AND pmpl.id_tenant = ?
                ";

            $sentence = $db->prepare($sql);
            $params = array_merge($ids_mobiliario, [$id_proceso, TenantContext::id()]);
            $sentence->execute($params);
            $productosMobiliario = $sentence->fetchAll();

            // Crear lookup de cantidades de mobiliario
            $cantidadesMobiliario = array();
            foreach ($response['productos_mobiliario'] as $mob) {
                $cantidadesMobiliario[$mob['id']] = array(
                    'cantidad' => $mob['cantidad'],
                    'nombre' => $mob['nombre']
                );
            }

            // Procesar productos del mobiliario
            foreach ($productosMobiliario as $prod) {
                $key = $prod['id_producto_limpieza'];
                $cantidad_mobiliario = $cantidadesMobiliario[$prod['id_mobiliario']]['cantidad'] ?? 0;

                // Calcular cantidad: cantidad_sugerida * cantidad_de_mobiliario
                $cantidad_calculada = $prod['cantidad_sugerida'] * $cantidad_mobiliario;

                if (!isset($productos_temp[$key])) {
                    $productos_temp[$key] = array(
                        'id_producto_limpieza' => $prod['id_producto_limpieza'],
                        'id_producto' => $prod['id_producto'],
                        'nombre' => $prod['nombre'],
                        'stock_actual' => $prod['stock_actual'],
                        'id_unidad_medida' => $prod['id_unidad_medida'],
                        'abreviatura' => $prod['abreviatura'],
                        'cantidad_total' => $cantidad_calculada,
                        'detalle_elementos' => array(),
                        'detalle_mobiliario' => array()
                    );
                } else {
                    $productos_temp[$key]['cantidad_total'] += $cantidad_calculada;
                }

                // Agregar detalle para debugging
                if ($cantidad_calculada > 0) {
                    $productos_temp[$key]['detalle_mobiliario'][] = array(
                        'mobiliario' => $prod['nombre_mobiliario'],
                        'id_mobiliario' => $prod['id_mobiliario'],
                        'cantidad_sugerida' => $prod['cantidad_sugerida'],
                        'cantidad_mobiliario' => $cantidad_mobiliario,
                        'subtotal' => $cantidad_calculada
                    );
                }
            }
        }

        // Redondear cantidades totales a 1 decimal y limpiar estructura para respuesta
        foreach ($productos_temp as &$producto) {
            $producto['cantidad_total'] = round($producto['cantidad_total'], 1);

            // Opcional: Incluir detalles del cálculo para debugging
            // Si no quieres enviar los detalles al frontend, comenta estas líneas
            $producto['calculo_detallado'] = array(
                'elementos' => $producto['detalle_elementos'],
                'mobiliario' => $producto['detalle_mobiliario']
            );

            // Remover arrays temporales si no se necesitan
            unset($producto['detalle_elementos']);
            unset($producto['detalle_mobiliario']);
        }

        $response['productos_limpieza'] = array_values($productos_temp);

        Flight::json($response);
    }

    public static function update()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $fecha_programada = Flight::request()->data['fecha_programada'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;

            // Verificar que esté en estado programado
            $sentence = $db->prepare("SELECT id_estado FROM registros_limpieza WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $registro = $sentence->fetch();

            if (!$registro || $registro['id_estado'] != 1) {
                throw new Exception('Solo se pueden editar registros en estado Programado');
            }

            // Actualizar fecha_programada y observaciones
            $sentence = $db->prepare("
                UPDATE registros_limpieza 
                SET fecha_programada = :fecha_programada,
                    observaciones = :observaciones
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':fecha_programada', $fecha_programada);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Limpiar detalles anteriores
            $sentence = $db->prepare("DELETE FROM registros_limpieza_detalle WHERE id_registro_limpieza = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Limpiar consumos anteriores
            $sentence = $db->prepare("DELETE FROM registros_limpieza_consumos WHERE id_registro_limpieza = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            // Insertar nuevos detalles
            if (isset(Flight::request()->data['detalles'])) {
                foreach (Flight::request()->data['detalles'] as $detalle) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            :id_producto_mobiliario
                        )
                    ");

                    $id_elemento = $detalle['id_elemento_fisico'] ?? null;
                    $id_mobiliario = $detalle['id_producto_mobiliario'] ?? null;

                    $idRLD2 = Uuid::generar();
                    $sentence->bindValue(':id', $idRLD2);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id);
                    $sentence->bindParam(':id_elemento_fisico', $id_elemento);
                    $sentence->bindParam(':id_producto_mobiliario', $id_mobiliario);
                    $sentence->execute();
                }
            }

            // Insertar nuevos consumos
            if (isset(Flight::request()->data['consumos'])) {
                foreach (Flight::request()->data['consumos'] as $consumo) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $idRLC2 = Uuid::generar();
                    $sentence->bindValue(':id', $idRLC2);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id);
                    $sentence->bindParam(':id_producto_limpieza', $consumo['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $consumo['cantidad_consumida']);
                    $sentence->bindParam(':id_unidad_medida', $consumo['id_unidad_medida']);
                    $sentence->execute();
                }
            }

            $db->commit();
            self::getById($id);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::update: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Devuelve las áreas configuradas para un proceso de limpieza, con el consumo
     * calculado de cada una, cuáles aplican hoy y cuáles venían marcadas la última
     * vez que se registró ese proceso.
     * Usado por la pantalla de Registro Rápido de Aseo.
     */
    public static function getRapidoPreview()
    {
        $db = Flight::db();

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        if (!$id_proceso) {
            Flight::json(array('error' => 'Falta el parámetro requerido (id_proceso)'), 400);
            return;
        }

        $response = array(
            'areas' => [],
            'ultima_fecha' => null
        );

        // La columna del día de hoy dentro de la configuración área/proceso
        $columnasDia = array(
            1 => 'lunes',
            2 => 'martes',
            3 => 'miercoles',
            4 => 'jueves',
            5 => 'viernes',
            6 => 'sabado',
            7 => 'domingo'
        );
        $columnaHoy = $columnasDia[(int) date('N')];

        // Todas las áreas activas. Las que tienen configuración para el proceso traen
        // su tiempo/prioridad/día; las que no, quedan con valores por defecto (LEFT JOIN).
        // Así se puede registrar aseo en cualquier área sin configurarla antes.
        $sentence = $db->prepare("
            SELECT
                af.id,
                af.nombre,
                af.descripcion,
                af.ubicacion,
                COALESCE(axp.prioridad, 999) as prioridad,
                COALESCE(axp.tiempo_estimado_minutos, 0) as tiempo_estimado_minutos,
                axp.hora_sugerida,
                COALESCE(axp.$columnaHoy, 0) as aplica_hoy,
                CASE WHEN axp.id_area_fisica IS NULL THEN 0 ELSE 1 END as tiene_config
            FROM areas_fisicas af
            LEFT JOIN areas_fisicas_x_procesos_limpieza axp
                ON axp.id_area_fisica = af.id
                AND axp.id_tipo_proceso_limpieza = :id_proceso
                AND axp.activo = 1
                AND axp.id_tenant = :id_tenant
            WHERE af.activo = 1
                AND af.id_tenant = :id_tenant2
            ORDER BY COALESCE(axp.prioridad, 999) ASC, af.nombre ASC
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $areas = $sentence->fetchAll();

        if (count($areas) == 0) {
            Flight::json($response);
            return;
        }

        $ids_areas = array_column($areas, 'id');

        // Última fecha en la que se registró este proceso (realizado o supervisado)
        $sentence = $db->prepare("
            SELECT MAX(fecha) as ultima_fecha
            FROM registros_limpieza
            WHERE id_tipo_proceso_limpieza = :id_proceso
                AND id_estado IN (3, 4)
                AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        $ultima_fecha = $fila['ultima_fecha'] ?? null;

        // Áreas que se limpiaron esa última fecha
        $areas_ultima = array();
        if ($ultima_fecha) {
            $sentence = $db->prepare("
                SELECT DISTINCT id_area_fisica
                FROM registros_limpieza
                WHERE id_tipo_proceso_limpieza = :id_proceso
                    AND fecha = :fecha
                    AND id_estado IN (3, 4)
                    AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id_proceso', $id_proceso);
            $sentence->bindParam(':fecha', $ultima_fecha);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $areas_ultima = array_flip(array_column($sentence->fetchAll(), 'id_area_fisica'));
        }

        $calculo = self::calcularAreasProceso($db, $id_proceso, $ids_areas);

        foreach ($areas as &$area) {
            $datos = $calculo[$area['id']] ?? array('elementos' => [], 'mobiliario' => [], 'productos' => []);

            $area['aplica_hoy'] = ((int) $area['aplica_hoy']) === 1;
            $area['prioridad'] = (int) $area['prioridad'];
            $area['tiempo_estimado_minutos'] = (int) ($area['tiempo_estimado_minutos'] ?? 0);
            $area['tiene_config'] = ((int) $area['tiene_config']) === 1;
            $area['total_elementos'] = count($datos['elementos']);
            $area['total_mobiliario'] = count($datos['mobiliario']);
            $area['productos'] = $datos['productos'];

            // Premarcado: si el proceso ya se registró, se premarcan las áreas de ese
            // último registro (sin importar si tienen config). Si nunca se ha registrado,
            // se premarcan solo las que aplican hoy por configuración. Un área sin config
            // e inédita aparece desmarcada, para marcarla a propósito.
            $area['preseleccionada'] = $ultima_fecha
                ? isset($areas_ultima[$area['id']])
                : $area['aplica_hoy'];
        }
        unset($area);

        $response['areas'] = $areas;
        $response['ultima_fecha'] = $ultima_fecha;

        Flight::json($response);
    }

    /**
     * Crea en una sola transacción los registros de limpieza de todas las áreas
     * seleccionadas y descuenta el inventario con un único movimiento consolidado.
     * Si viene supervisor los registros quedan en estado Supervisado (4); si no,
     * en Realizado (3).
     * Usado por la pantalla de Registro Rápido de Aseo.
     */
    public static function crearRapido()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_proceso = Flight::request()->data['id_tipo_proceso_limpieza'] ?? null;
            $fecha = Flight::request()->data['fecha'] ?? date('Y-m-d');
            $hora_inicio = Flight::request()->data['hora_inicio'] ?? null;
            $hora_fin = Flight::request()->data['hora_fin'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $id_usuario_ejecutor = Flight::request()->data['id_usuario_ejecutor'] ?? null;
            $id_usuario_supervisor = Flight::request()->data['id_usuario_supervisor'] ?? null;
            $areas = Flight::request()->data['areas'] ?? array();

            // Con supervisor el registro nace supervisado; sin supervisor queda pendiente de supervisión
            $id_estado = $id_usuario_supervisor ? 4 : 3;

            if (!$id_proceso) {
                throw new Exception('Debe indicar el tipo de proceso de limpieza');
            }
            if (!is_array($areas) || count($areas) == 0) {
                throw new Exception('Debe seleccionar al menos un área');
            }

            // Solo se aceptan áreas configuradas y activas para el proceso
            $placeholders = implode(',', array_fill(0, count($areas), '?'));
            $sentence = $db->prepare("
                SELECT axp.id_area_fisica
                FROM areas_fisicas_x_procesos_limpieza axp
                INNER JOIN areas_fisicas af ON axp.id_area_fisica = af.id
                WHERE axp.id_tipo_proceso_limpieza = ?
                    AND axp.activo = 1
                    AND af.activo = 1
                    AND axp.id_tenant = ?
                    AND axp.id_area_fisica IN ($placeholders)
            ");
            $sentence->execute(array_merge([$id_proceso, TenantContext::id()], array_values($areas)));
            $areas_validas = array_column($sentence->fetchAll(), 'id_area_fisica');

            if (count($areas_validas) == 0) {
                throw new Exception('Ninguna de las áreas seleccionadas está configurada para este proceso');
            }

            // El consumo se recalcula en el servidor, no se toma del cliente
            $calculo = self::calcularAreasProceso($db, $id_proceso, $areas_validas);

            $ids_registros = array();
            $consumos_por_producto = array();
            $consumo_consolidado = array();

            foreach ($areas_validas as $id_area) {
                $datos = $calculo[$id_area] ?? array('elementos' => [], 'mobiliario' => [], 'productos' => []);

                // Registro principal, directo en estado Realizado
                $sentence = $db->prepare("
                    INSERT INTO registros_limpieza (
                        id,
                        id_tenant,
                        fecha,
                        fecha_programada,
                        hora_inicio,
                        hora_fin,
                        id_estado,
                        observaciones,
                        id_area_fisica,
                        id_tipo_proceso_limpieza,
                        id_usuario_ejecutor,
                        id_usuario_supervisor
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :fecha,
                        :fecha_programada,
                        :hora_inicio,
                        :hora_fin,
                        :id_estado,
                        :observaciones,
                        :id_area_fisica,
                        :id_tipo_proceso_limpieza,
                        :id_usuario_ejecutor,
                        :id_usuario_supervisor
                    )
                ");

                $id_registro = Uuid::generar();
                $sentence->bindValue(':id', $id_registro);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':fecha', $fecha);
                $sentence->bindParam(':fecha_programada', $fecha);
                $sentence->bindParam(':hora_inicio', $hora_inicio);
                $sentence->bindParam(':hora_fin', $hora_fin);
                $sentence->bindValue(':id_estado', $id_estado, PDO::PARAM_INT);
                $sentence->bindParam(':observaciones', $observaciones);
                $sentence->bindParam(':id_area_fisica', $id_area);
                $sentence->bindParam(':id_tipo_proceso_limpieza', $id_proceso);
                $sentence->bindParam(':id_usuario_ejecutor', $id_usuario_ejecutor);
                $sentence->bindParam(':id_usuario_supervisor', $id_usuario_supervisor);
                $sentence->execute();

                $ids_registros[] = $id_registro;

                // Detalle: todos los elementos y mobiliario del área para ese proceso
                foreach ($datos['elementos'] as $elemento) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_elemento_fisico,
                            NULL
                        )
                    ");
                    $sentence->bindValue(':id', Uuid::generar());
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_elemento_fisico', $elemento['id']);
                    $sentence->execute();
                }

                foreach ($datos['mobiliario'] as $mobiliario) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_detalle (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_elemento_fisico,
                            id_producto_mobiliario
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            NULL,
                            :id_producto_mobiliario
                        )
                    ");
                    $sentence->bindValue(':id', Uuid::generar());
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_mobiliario', $mobiliario['id']);
                    $sentence->execute();
                }

                // Consumos del área. Un área sin productos configurados queda sin consumo.
                foreach ($datos['productos'] as $producto) {
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza_consumos (
                            id, id_tenant,
                            id_registro_limpieza,
                            id_producto_limpieza,
                            cantidad_consumida,
                            id_unidad_medida
                        ) VALUES (
                            :id, :id_tenant,
                            :id_registro_limpieza,
                            :id_producto_limpieza,
                            :cantidad_consumida,
                            :id_unidad_medida
                        )
                    ");

                    $id_consumo = Uuid::generar();
                    $sentence->bindValue(':id', $id_consumo);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_registro_limpieza', $id_registro);
                    $sentence->bindParam(':id_producto_limpieza', $producto['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad_consumida', $producto['cantidad']);
                    $sentence->bindParam(':id_unidad_medida', $producto['id_unidad_medida']);
                    $sentence->execute();

                    $id_producto = $producto['id_producto'];

                    // Se acumula por producto para descontar una sola vez
                    if (!isset($consumo_consolidado[$id_producto])) {
                        $consumo_consolidado[$id_producto] = array(
                            'id_producto' => $id_producto,
                            'nombre' => $producto['nombre'],
                            'abreviatura' => $producto['abreviatura'],
                            'stock_actual' => $producto['stock_actual'],
                            'cantidad' => 0
                        );
                    }
                    $consumo_consolidado[$id_producto]['cantidad'] += $producto['cantidad'];
                    $consumos_por_producto[$id_producto][] = $id_consumo;
                }
            }

            $id_movimiento = null;
            $productos_ajustados = array();

            if (count($consumo_consolidado) > 0) {
                // Concepto de movimiento usado también por RegistrosLimpieza::finalizar
                $sentence = $db->prepare("
                    SELECT id
                    FROM conceptos_movimiento
                    WHERE nombre = 'Salida por Limpieza' AND id_tenant = :id_tenant
                    LIMIT 1
                ");
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                $concepto = $sentence->fetch();

                if (!$concepto) {
                    throw new Exception("No existe el concepto de movimiento 'Salida por Limpieza'");
                }

                $observaciones_movimiento = 'Registro rápido de aseo - ' . count($ids_registros) . ' área(s)';

                $sentence = $db->prepare("
                    INSERT INTO movimientos_productos (
                        id,
                        id_tenant,
                        fecha_movimiento,
                        id_concepto_movimiento,
                        id_estado,
                        observaciones,
                        id_usuario_registro
                    ) VALUES (
                        :id,
                        :id_tenant,
                        NOW(),
                        :id_concepto_movimiento,
                        3, -- Aprobado
                        :observaciones,
                        :id_usuario
                    )
                ");

                $id_movimiento = Uuid::generar();
                $sentence->bindValue(':id', $id_movimiento);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_concepto_movimiento', $concepto['id']);
                $sentence->bindParam(':observaciones', $observaciones_movimiento);
                $sentence->bindParam(':id_usuario', $id_usuario_ejecutor);
                $sentence->execute();

                foreach ($consumo_consolidado as $id_producto => $item) {
                    $cantidad_a_descontar = round($item['cantidad'], 2);
                    $stock_disponible = $item['stock_actual'];

                    // Si no alcanza el stock se descuenta solo lo disponible y se avisa
                    if ($cantidad_a_descontar > $stock_disponible) {
                        $productos_ajustados[] = array(
                            'producto' => $item['nombre'],
                            'abreviatura' => $item['abreviatura'],
                            'solicitado' => $cantidad_a_descontar,
                            'disponible' => $stock_disponible,
                            'descontado' => max(0, $stock_disponible)
                        );
                        $cantidad_a_descontar = max(0, $stock_disponible);
                    }

                    if ($cantidad_a_descontar <= 0) {
                        error_log("Producto sin stock en registro rápido: " . $item['nombre']);
                        continue;
                    }

                    $sentence = $db->prepare("
                        INSERT INTO movimientos_productos_detalle (
                            id,
                            id_tenant,
                            id_movimiento,
                            id_producto,
                            cantidad,
                            stock_anterior,
                            precio_unitario
                        ) VALUES (
                            :id,
                            :id_tenant,
                            :id_movimiento,
                            :id_producto,
                            :cantidad,
                            :stock_anterior,
                            (SELECT precio_unitario FROM productos WHERE id = :id_producto2)
                        )
                    ");

                    $id_movimiento_detalle = Uuid::generar();
                    $sentence->bindValue(':id', $id_movimiento_detalle);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $id_producto);
                    $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                    $sentence->bindParam(':stock_anterior', $item['stock_actual']);
                    $sentence->bindParam(':id_producto2', $id_producto);
                    $sentence->execute();

                    // Actualizar stock del producto - NUNCA permitir negativos
                    $sentence = $db->prepare("
                        UPDATE productos
                        SET stock_anterior = stock_actual,
                            stock_actual = GREATEST(0, stock_actual - :cantidad),
                            id_ultimo_movimiento = :id_movimiento,
                            fecha_ultimo_movimiento = NOW()
                        WHERE id = :id_producto AND id_tenant = :id_tenant
                    ");
                    $sentence->bindParam(':cantidad', $cantidad_a_descontar);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $id_producto);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();

                    // Todos los consumos de ese producto apuntan al mismo detalle de movimiento
                    foreach ($consumos_por_producto[$id_producto] as $id_consumo) {
                        $sentence = $db->prepare("
                            UPDATE registros_limpieza_consumos
                            SET id_movimiento_inventario = :id_movimiento_detalle
                            WHERE id = :id_consumo AND id_tenant = :id_tenant
                        ");
                        $sentence->bindParam(':id_movimiento_detalle', $id_movimiento_detalle);
                        $sentence->bindParam(':id_consumo', $id_consumo);
                        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $sentence->execute();
                    }
                }
            }

            $db->commit();

            $response = array(
                'success' => true,
                'ids' => $ids_registros,
                'total_registros' => count($ids_registros),
                'id_movimiento' => $id_movimiento,
                'id_estado' => $id_estado
            );

            if (count($productos_ajustados) > 0) {
                $response['productos_ajustados'] = $productos_ajustados;
            }

            Flight::json($response);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::crearRapido: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Calcula, para un proceso y un conjunto de áreas, los elementos físicos y el
     * mobiliario a limpiar en cada área y los productos de limpieza que consumen.
     * Misma fórmula que getElementosParaProceso (cantidad_sugerida * cantidad en el
     * área) pero resuelta para todas las áreas en un solo juego de consultas.
     *
     * Devuelve un mapa id_area => ['elementos' => [], 'mobiliario' => [], 'productos' => []]
     */
    private static function calcularAreasProceso($db, $id_proceso, array $ids_areas)
    {
        $resultado = array();
        foreach ($ids_areas as $id_area) {
            $resultado[$id_area] = array('elementos' => [], 'mobiliario' => [], 'productos' => []);
        }

        if (count($ids_areas) == 0) {
            return $resultado;
        }

        $id_tenant = TenantContext::id();
        $placeholders = implode(',', array_fill(0, count($ids_areas), '?'));

        // Elementos físicos del proceso presentes en cada área
        $sentence = $db->prepare("
            SELECT DISTINCT
                efaf.id_area_fisica,
                ef.id,
                ef.nombre,
                efaf.cantidad
            FROM elementos_fisicos ef
            INNER JOIN elementos_fisicos_x_procesos_limpieza efpl ON ef.id = efpl.id_elemento_fisico
            INNER JOIN elementos_fisicos_x_areas_fisicas efaf ON ef.id = efaf.id_elemento_fisico
            WHERE efpl.id_tipo_proceso_limpieza = ?
                AND efpl.id_tenant = ?
                AND ef.id_tenant = ?
                AND efaf.id_area_fisica IN ($placeholders)
            ORDER BY ef.nombre
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_area_fisica']]['elementos'][] = array(
                'id' => $fila['id'],
                'nombre' => $fila['nombre'],
                'cantidad' => (float) $fila['cantidad']
            );
        }

        // Mobiliario del proceso presente en cada área
        $sentence = $db->prepare("
            SELECT DISTINCT
                pmxa.id_area,
                pm.id,
                p.nombre,
                pmxa.cantidad
            FROM productos_mobiliario pm
            INNER JOIN productos p ON pm.id_producto = p.id
            INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
            INNER JOIN productos_mobiliario_x_procesos_limpieza pmpl ON pm.id = pmpl.id_producto_mobiliario
            WHERE pmpl.id_tipo_proceso_limpieza = ?
                AND p.activo = 1
                AND pm.id_tenant = ?
                AND pmxa.id_area IN ($placeholders)
            ORDER BY p.nombre
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_area']]['mobiliario'][] = array(
                'id' => $fila['id'],
                'nombre' => $fila['nombre'],
                'cantidad' => (float) $fila['cantidad']
            );
        }

        $acumulado = array();

        // PARTE 1: productos que consumen los elementos físicos
        // cantidad = cantidad_sugerida * cantidad_del_elemento_en_el_area
        $sentence = $db->prepare("
            SELECT
                efaf.id_area_fisica,
                pl.id as id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                um.id as id_unidad_medida,
                um.abreviatura,
                efplp.cantidad_sugerida,
                efaf.cantidad as cantidad_elemento_area
            FROM elementos_fisicos_x_procesos_limpieza efpl
            INNER JOIN elementos_fisicos_x_procesos_limpieza_productos efplp
                ON efpl.id = efplp.id_elementos_fisicos_x_procesos_limpieza
            INNER JOIN productos_limpieza pl ON efplp.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
            INNER JOIN elementos_fisicos_x_areas_fisicas efaf
                ON efpl.id_elemento_fisico = efaf.id_elemento_fisico
            WHERE efpl.id_tipo_proceso_limpieza = ?
                AND p.activo = 1
                AND efpl.id_tenant = ?
                AND efaf.id_area_fisica IN ($placeholders)
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            self::acumularProducto(
                $acumulado,
                $fila['id_area_fisica'],
                $fila,
                $fila['cantidad_sugerida'] * $fila['cantidad_elemento_area']
            );
        }

        // PARTE 2: productos que consume el mobiliario
        // cantidad = cantidad_sugerida * cantidad_de_mobiliario_en_el_area
        $sentence = $db->prepare("
            SELECT
                pmxa.id_area,
                pl.id as id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                um.id as id_unidad_medida,
                um.abreviatura,
                pmplp.cantidad_sugerida,
                pmxa.cantidad as cantidad_mobiliario_area
            FROM productos_mobiliario_x_procesos_limpieza pmpl
            INNER JOIN productos_mobiliario pm ON pmpl.id_producto_mobiliario = pm.id
            INNER JOIN productos prod_mob ON pm.id_producto = prod_mob.id
            INNER JOIN productos_mobiliario_x_areas_fisicas pmxa ON pm.id = pmxa.id_producto_mobiliario
            INNER JOIN productos_mobiliario_x_procesos_limpieza_productos pmplp
                ON pmpl.id = pmplp.id_proceso_limpieza
            INNER JOIN productos_mobiliario_x_productos_limpieza pmxpl
                ON pmplp.id_productos_mobiliario_x_productos_limpieza = pmxpl.id
            INNER JOIN productos_limpieza pl ON pmxpl.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE pmpl.id_tipo_proceso_limpieza = ?
                AND prod_mob.activo = 1
                AND p.activo = 1
                AND pmpl.id_tenant = ?
                AND pmxa.id_area IN ($placeholders)
        ");
        $sentence->execute(array_merge([$id_proceso, $id_tenant], $ids_areas));
        foreach ($sentence->fetchAll() as $fila) {
            self::acumularProducto(
                $acumulado,
                $fila['id_area'],
                $fila,
                $fila['cantidad_sugerida'] * $fila['cantidad_mobiliario_area']
            );
        }

        // Redondear y descartar los productos que no aportan consumo
        foreach ($acumulado as $id_area => $productos) {
            foreach ($productos as $producto) {
                $producto['cantidad'] = round($producto['cantidad'], 1);
                if ($producto['cantidad'] > 0) {
                    $resultado[$id_area]['productos'][] = $producto;
                }
            }
        }

        // Respaldo: las áreas que quedaron sin productos por elementos toman el
        // consumo general configurado (areas_fisicas_x_procesos_limpieza_consumo).
        // La granularidad por elementos manda; el consumo general solo cubre lo que
        // el cálculo por elementos dejó vacío.
        $areas_sin_consumo = array();
        foreach ($ids_areas as $id_area) {
            if (count($resultado[$id_area]['productos']) == 0) {
                $areas_sin_consumo[] = $id_area;
            }
        }

        if (count($areas_sin_consumo) > 0) {
            $general = self::obtenerConsumoGeneral($db, $id_proceso, $areas_sin_consumo);
            foreach ($general as $id_area => $productos) {
                $resultado[$id_area]['productos'] = $productos;
            }
        }

        return $resultado;
    }

    /**
     * Consumo general fijo por área/proceso para las áreas indicadas.
     * Devuelve las cantidades con la misma forma que el cálculo por elementos, para
     * que crearRapido y getRapidoPreview las traten igual sin distinguir el origen.
     * Mapa id_area => [ productos ].
     */
    private static function obtenerConsumoGeneral($db, $id_proceso, array $ids_areas)
    {
        $resultado = array();
        foreach ($ids_areas as $id_area) {
            $resultado[$id_area] = array();
        }

        if (count($ids_areas) == 0) {
            return $resultado;
        }

        $placeholders = implode(',', array_fill(0, count($ids_areas), '?'));

        $sentence = $db->prepare("
            SELECT
                c.id_area_fisica,
                c.id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                c.cantidad,
                c.id_unidad_medida,
                um.abreviatura
            FROM areas_fisicas_x_procesos_limpieza_consumo c
            INNER JOIN productos_limpieza pl ON c.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON c.id_unidad_medida = um.id
            WHERE c.id_tipo_proceso_limpieza = ?
                AND c.activo = 1
                AND p.activo = 1
                AND c.id_tenant = ?
                AND c.id_area_fisica IN ($placeholders)
        ");
        $sentence->execute(array_merge([$id_proceso, TenantContext::id()], $ids_areas));

        foreach ($sentence->fetchAll() as $fila) {
            $resultado[$fila['id_area_fisica']][] = array(
                'id_producto_limpieza' => $fila['id_producto_limpieza'],
                'id_producto' => $fila['id_producto'],
                'nombre' => $fila['nombre'],
                'stock_actual' => (float) $fila['stock_actual'],
                'id_unidad_medida' => $fila['id_unidad_medida'],
                'abreviatura' => $fila['abreviatura'],
                'cantidad' => round((float) $fila['cantidad'], 1)
            );
        }

        return $resultado;
    }

    /**
     * Suma la cantidad calculada de un producto de limpieza dentro del acumulado del área.
     */
    private static function acumularProducto(array &$acumulado, $id_area, array $fila, $cantidad)
    {
        $key = $fila['id_producto_limpieza'];

        if (!isset($acumulado[$id_area][$key])) {
            $acumulado[$id_area][$key] = array(
                'id_producto_limpieza' => $fila['id_producto_limpieza'],
                'id_producto' => $fila['id_producto'],
                'nombre' => $fila['nombre'],
                'stock_actual' => (float) $fila['stock_actual'],
                'id_unidad_medida' => $fila['id_unidad_medida'],
                'abreviatura' => $fila['abreviatura'],
                'cantidad' => 0
            );
        }

        $acumulado[$id_area][$key]['cantidad'] += $cantidad;
    }

    /**
     * Devuelve los registros de limpieza que están en estado Realizado (3) y por tanto
     * pendientes de supervisión, vengan del registro rápido o del flujo normal.
     * Filtros opcionales por rango de fechas y por proceso.
     * Usado por la pantalla de Supervisión de Aseo.
     */
    public static function getPendientesSupervision()
    {
        $db = Flight::db();

        $fecha_desde = Flight::request()->query['fecha_desde'] ??
            Flight::request()->query->fecha_desde ??
            $_GET['fecha_desde'] ??
            null;

        $fecha_hasta = Flight::request()->query['fecha_hasta'] ??
            Flight::request()->query->fecha_hasta ??
            $_GET['fecha_hasta'] ??
            null;

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        $sql = "
            SELECT
                rl.id,
                rl.fecha,
                rl.hora_inicio,
                rl.hora_fin,
                rl.observaciones,
                af.id as id_area_fisica,
                af.nombre as area,
                af.ubicacion,
                tp.id as id_tipo_proceso_limpieza,
                tp.nombre as proceso,
                rl.id_usuario_ejecutor,
                TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre,
                                    p.primer_apellido, p.segundo_apellido)) as ejecutor,
                (SELECT COUNT(*) FROM registros_limpieza_detalle
                  WHERE id_registro_limpieza = rl.id) as total_elementos,
                (SELECT IFNULL(SUM(cantidad_consumida), 0) FROM registros_limpieza_consumos
                  WHERE id_registro_limpieza = rl.id) as total_consumido
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tp ON rl.id_tipo_proceso_limpieza = tp.id
            LEFT JOIN usuarios u ON rl.id_usuario_ejecutor = u.id
            LEFT JOIN personas p ON u.id_persona = p.id
            WHERE rl.id_estado = 3
                AND rl.id_tenant = :id_tenant
        ";

        // Los filtros son opcionales: sin ellos salen todos los pendientes
        if ($fecha_desde) {
            $sql .= " AND rl.fecha >= :fecha_desde";
        }
        if ($fecha_hasta) {
            $sql .= " AND rl.fecha <= :fecha_hasta";
        }
        if ($id_proceso) {
            $sql .= " AND rl.id_tipo_proceso_limpieza = :id_proceso";
        }

        $sql .= " ORDER BY rl.fecha DESC, tp.nombre, af.nombre";

        $sentence = $db->prepare($sql);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

        if ($fecha_desde) {
            $sentence->bindParam(':fecha_desde', $fecha_desde);
        }
        if ($fecha_hasta) {
            $sentence->bindParam(':fecha_hasta', $fecha_hasta);
        }
        if ($id_proceso) {
            $sentence->bindParam(':id_proceso', $id_proceso);
        }

        $sentence->execute();

        Flight::json($sentence->fetchAll());
    }

    /**
     * Pasa a estado Supervisado (4) todos los registros indicados, en una sola
     * transacción. Solo afecta los que estén en estado Realizado (3); los demás se
     * ignoran y se reportan como omitidos.
     * Usado por la pantalla de Supervisión de Aseo.
     */
    public static function supervisarLote()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $ids = Flight::request()->data['ids'] ?? array();
            $id_usuario_supervisor = Flight::request()->data['id_usuario_supervisor'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;

            if (!is_array($ids) || count($ids) == 0) {
                throw new Exception('Debe seleccionar al menos un registro');
            }
            if (!$id_usuario_supervisor) {
                throw new Exception('Debe indicar el usuario supervisor');
            }

            $observaciones = is_string($observaciones) ? trim($observaciones) : null;
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // La observación solo se concatena si trae texto, para no dejar el sufijo colgando
            if ($observaciones) {
                $sql = "
                    UPDATE registros_limpieza
                    SET id_estado = 4, -- Supervisado
                        id_usuario_supervisor = ?,
                        observaciones = CONCAT(IFNULL(observaciones, ''), ' | Supervisión: ', ?)
                    WHERE id IN ($placeholders)
                        AND id_estado = 3
                        AND id_tenant = ?
                ";
                $params = array_merge(
                    [$id_usuario_supervisor, $observaciones],
                    array_values($ids),
                    [TenantContext::id()]
                );
            } else {
                $sql = "
                    UPDATE registros_limpieza
                    SET id_estado = 4, -- Supervisado
                        id_usuario_supervisor = ?
                    WHERE id IN ($placeholders)
                        AND id_estado = 3
                        AND id_tenant = ?
                ";
                $params = array_merge(
                    [$id_usuario_supervisor],
                    array_values($ids),
                    [TenantContext::id()]
                );
            }

            $sentence = $db->prepare($sql);
            $sentence->execute($params);
            $supervisados = $sentence->rowCount();

            if ($supervisados == 0) {
                throw new Exception('Ninguno de los registros seleccionados estaba pendiente de supervisión');
            }

            $db->commit();

            $response = array(
                'success' => true,
                'total_supervisados' => $supervisados
            );

            // Un registro se omite si alguien más ya lo supervisó o lo canceló entre tanto
            $omitidos = count($ids) - $supervisados;
            if ($omitidos > 0) {
                $response['omitidos'] = $omitidos;
            }

            Flight::json($response);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::supervisarLote: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Devuelve los registros de aseo de un rango de fechas y un proceso, con el
     * mobiliario aseado agrupado por tipo y el consumo total, para el reporte de
     * cumplimiento. Filtros: fecha_desde, fecha_hasta, id_proceso (requeridos);
     * id_area (opcional, para una sola área).
     * Usado por la pantalla de Reporte de Aseo.
     */
    public static function getReporteAseo()
    {
        $db = Flight::db();

        $fecha_desde = Flight::request()->query['fecha_desde'] ??
            Flight::request()->query->fecha_desde ??
            $_GET['fecha_desde'] ??
            null;

        $fecha_hasta = Flight::request()->query['fecha_hasta'] ??
            Flight::request()->query->fecha_hasta ??
            $_GET['fecha_hasta'] ??
            null;

        $id_proceso = Flight::request()->query['id_proceso'] ??
            Flight::request()->query->id_proceso ??
            $_GET['id_proceso'] ??
            null;

        $id_area = Flight::request()->query['id_area'] ??
            Flight::request()->query->id_area ??
            $_GET['id_area'] ??
            null;

        if (!$fecha_desde || !$fecha_hasta || !$id_proceso) {
            Flight::json(array('error' => 'Faltan parámetros requeridos (fecha_desde, fecha_hasta, id_proceso)'), 400);
            return;
        }

        // Registros del rango + proceso, ya realizados o supervisados
        $sql = "
            SELECT
                rl.id,
                rl.fecha,
                rl.hora_inicio,
                rl.hora_fin,
                rl.id_estado,
                rl.observaciones,
                af.id as id_area_fisica,
                af.nombre as area,
                af.ubicacion,
                af.mobiliario_general,
                tp.nombre as proceso,
                er.nombre as estado,
                TRIM(CONCAT_WS(' ', pe.primer_nombre, pe.segundo_nombre,
                                    pe.primer_apellido, pe.segundo_apellido)) as ejecutor,
                TRIM(CONCAT_WS(' ', ps.primer_nombre, ps.segundo_nombre,
                                    ps.primer_apellido, ps.segundo_apellido)) as supervisor
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tp ON rl.id_tipo_proceso_limpieza = tp.id
            LEFT JOIN estados_registro_limpieza er ON rl.id_estado = er.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas pe ON ue_user.id_persona = pe.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas ps ON us_user.id_persona = ps.id
            WHERE rl.id_tipo_proceso_limpieza = :id_proceso
                AND rl.fecha BETWEEN :fecha_desde AND :fecha_hasta
                AND rl.id_estado IN (3, 4)
                AND rl.id_tenant = :id_tenant
        ";
        if ($id_area) {
            $sql .= " AND rl.id_area_fisica = :id_area";
        }
        $sql .= " ORDER BY af.nombre, rl.fecha";

        $sentence = $db->prepare($sql);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindParam(':fecha_desde', $fecha_desde);
        $sentence->bindParam(':fecha_hasta', $fecha_hasta);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        if ($id_area) {
            $sentence->bindParam(':id_area', $id_area);
        }
        $sentence->execute();
        $registros = $sentence->fetchAll();

        if (count($registros) == 0) {
            Flight::json(array('registros' => array(), 'productos_usados' => array()));
            return;
        }

        $ids = array_column($registros, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // Mobiliario aseado por registro, con su tipo. Se cuenta cuántos ítems de
        // cada tipo entraron en cada registro.
        $sentence = $db->prepare("
            SELECT
                rld.id_registro_limpieza,
                COALESCE(tpm.nombre, 'Sin clasificar') as tipo,
                p.nombre as mueble,
                pmxa.cantidad
            FROM registros_limpieza_detalle rld
            INNER JOIN productos_mobiliario pm ON rld.id_producto_mobiliario = pm.id
            INNER JOIN productos p ON pm.id_producto = p.id
            LEFT JOIN tipos_producto_mobiliario tpm ON pm.id_tipo_producto_mobiliario = tpm.id
            LEFT JOIN productos_mobiliario_x_areas_fisicas pmxa
                ON pmxa.id_producto_mobiliario = pm.id
            WHERE rld.id_producto_mobiliario IS NOT NULL
                AND rld.id_registro_limpieza IN ($placeholders)
            ORDER BY tpm.nombre, p.nombre
        ");
        $sentence->execute($ids);

        $mobiliario_por_registro = array();
        foreach ($sentence->fetchAll() as $fila) {
            $id_reg = $fila['id_registro_limpieza'];
            if (!isset($mobiliario_por_registro[$id_reg])) {
                $mobiliario_por_registro[$id_reg] = array();
            }
            $mobiliario_por_registro[$id_reg][] = array(
                'tipo' => $fila['tipo'],
                'mueble' => $fila['mueble'],
                'cantidad' => $fila['cantidad'] !== null ? (int) $fila['cantidad'] : 1
            );
        }

        // Consumo de productos por registro (con modo de uso, para el encabezado)
        $sentence = $db->prepare("
            SELECT
                rlc.id_registro_limpieza,
                pl.id as id_producto_limpieza,
                p.nombre as producto,
                pl.modo_uso,
                rlc.cantidad_consumida,
                um.abreviatura
            FROM registros_limpieza_consumos rlc
            INNER JOIN productos_limpieza pl ON rlc.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            LEFT JOIN unidades_medida um ON rlc.id_unidad_medida = um.id
            WHERE rlc.id_registro_limpieza IN ($placeholders)
            ORDER BY p.nombre
        ");
        $sentence->execute($ids);

        $consumo_por_registro = array();
        $productos_usados = array(); // Consolidado global: producto => modo_uso (sin cantidades)
        foreach ($sentence->fetchAll() as $fila) {
            $id_reg = $fila['id_registro_limpieza'];
            if (!isset($consumo_por_registro[$id_reg])) {
                $consumo_por_registro[$id_reg] = array();
            }
            $consumo_por_registro[$id_reg][] = array(
                'producto' => $fila['producto'],
                'cantidad' => (float) $fila['cantidad_consumida'],
                'abreviatura' => $fila['abreviatura']
            );

            // El consolidado guarda cada producto una sola vez, con su modo de uso
            if (!isset($productos_usados[$fila['id_producto_limpieza']])) {
                $productos_usados[$fila['id_producto_limpieza']] = array(
                    'producto' => $fila['producto'],
                    'modo_uso' => $fila['modo_uso']
                );
            }
        }

        // Se arma la respuesta agrupando el mobiliario por tipo (conteo por tipo)
        foreach ($registros as &$registro) {
            $items = $mobiliario_por_registro[$registro['id']] ?? array();

            $por_tipo = array();
            foreach ($items as $item) {
                if (!isset($por_tipo[$item['tipo']])) {
                    $por_tipo[$item['tipo']] = 0;
                }
                $por_tipo[$item['tipo']]++;
            }

            $resumen_tipos = array();
            foreach ($por_tipo as $tipo => $conteo) {
                $resumen_tipos[] = array('tipo' => $tipo, 'conteo' => $conteo);
            }

            $registro['mobiliario'] = $items;
            $registro['resumen_tipos'] = $resumen_tipos;
            $registro['consumos'] = $consumo_por_registro[$registro['id']] ?? array();
        }
        unset($registro);

        // Se devuelve la lista de registros + el consolidado de productos usados
        // (con modo de uso, sin cantidades) para mostrarlo en el encabezado del reporte.
        Flight::json(array(
            'registros' => $registros,
            'productos_usados' => array_values($productos_usados)
        ));
    }

    /**
     * Devuelve los productos (distintos, con su modo de uso, SIN cantidades) que se
     * usarían al asear las áreas indicadas en un proceso. Reutiliza el mismo cálculo
     * que el registro rápido, pero consolida por producto y agrega el modo de uso.
     * Usado por el modal "Productos y modo de uso" del registro rápido.
     */
    public static function getProductosModoUso()
    {
        $db = Flight::db();

        $id_proceso = Flight::request()->data['id_proceso'] ?? null;
        $areas = Flight::request()->data['areas'] ?? array();

        if (!$id_proceso || !is_array($areas) || count($areas) == 0) {
            Flight::json(array('error' => 'Debe indicar el proceso y al menos un área'), 400);
            return;
        }

        // Mismo cálculo que usa el registro rápido (elementos + mobiliario + respaldo)
        $resultado = self::calcularAreasProceso($db, $id_proceso, $areas);

        // Consolidar los productos distintos usados en todas las áreas
        $ids_productos = array();
        foreach ($resultado as $area) {
            foreach (($area['productos'] ?? array()) as $prod) {
                $ids_productos[$prod['id_producto_limpieza']] = $prod['nombre'];
            }
        }

        if (count($ids_productos) == 0) {
            Flight::json(array());
            return;
        }

        // Traer el modo de uso de cada producto de limpieza
        $ids = array_keys($ids_productos);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sentence = $db->prepare("
            SELECT
                pl.id as id_producto_limpieza,
                p.nombre as producto,
                pl.modo_uso
            FROM productos_limpieza pl
            INNER JOIN productos p ON pl.id_producto = p.id
            WHERE pl.id IN ($placeholders)
            ORDER BY p.nombre
        ");
        $sentence->execute($ids);

        Flight::json($sentence->fetchAll());
    }

    /**
     * Previsualiza el registro masivo: dado un proceso y un rango de fechas, calcula
     * para cada área configurada cuántos días laborales del rango coinciden con sus
     * días marcados. NO crea nada, solo informa qué se generaría.
     * Áreas sin días marcados quedan fuera (se informan aparte).
     */
    public static function getMasivoPreview()
    {
        $db = Flight::db();

        $id_proceso = Flight::request()->query['id_proceso'] ?? $_GET['id_proceso'] ?? null;
        $fecha_desde = Flight::request()->query['fecha_desde'] ?? $_GET['fecha_desde'] ?? null;
        $fecha_hasta = Flight::request()->query['fecha_hasta'] ?? $_GET['fecha_hasta'] ?? null;

        if (!$id_proceso || !$fecha_desde || !$fecha_hasta) {
            Flight::json(array('error' => 'Faltan parámetros (id_proceso, fecha_desde, fecha_hasta)'), 400);
            return;
        }
        if ($fecha_desde > $fecha_hasta) {
            Flight::json(array('error' => 'La fecha inicial no puede ser mayor que la final'), 400);
            return;
        }

        // Días laborales del rango (id_tipo_dia = 1 = Laboral), con su día de semana
        $dias_laborales = self::obtenerDiasLaborales($db, $fecha_desde, $fecha_hasta);

        // Áreas configuradas y activas para el proceso, con sus días marcados
        $areas = self::obtenerAreasConDias($db, $id_proceso);

        $columnasDia = array(1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves',
                             5 => 'viernes', 6 => 'sabado', 7 => 'domingo');

        $resultado = array();
        $total_registros = 0;

        foreach ($areas as $area) {
            // Días de la semana marcados para el área
            $dias_marcados = array();
            foreach ($columnasDia as $num => $col) {
                if ((int) $area[$col] === 1) {
                    $dias_marcados[] = $num;
                }
            }

            // Cuántos días laborales del rango caen en los días marcados
            $dias_aplicables = 0;
            if (count($dias_marcados) > 0) {
                foreach ($dias_laborales as $dl) {
                    if (in_array((int) $dl['id_dia_semana'], $dias_marcados)) {
                        $dias_aplicables++;
                    }
                }
            }

            $resultado[] = array(
                'id_area_fisica' => $area['id_area_fisica'],
                'area' => $area['area'],
                'dias_marcados' => count($dias_marcados),
                'dias_aplicables' => $dias_aplicables,
                'sin_dias' => count($dias_marcados) === 0
            );
            $total_registros += $dias_aplicables;
        }

        Flight::json(array(
            'areas' => $resultado,
            'total_dias_laborales' => count($dias_laborales),
            'total_registros' => $total_registros
        ));
    }

    /**
     * Crea en lote los registros de aseo de un rango de fechas. Por cada área
     * seleccionada y cada día laboral del rango que coincida con sus días marcados,
     * genera un registro (con detalle y consumo) igual que el registro rápido.
     * Todo en una transacción.
     */
    public static function crearMasivo()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $id_proceso = Flight::request()->data['id_tipo_proceso_limpieza'] ?? null;
            $fecha_desde = Flight::request()->data['fecha_desde'] ?? null;
            $fecha_hasta = Flight::request()->data['fecha_hasta'] ?? null;
            $hora_inicio = Flight::request()->data['hora_inicio'] ?? null;
            $hora_fin = Flight::request()->data['hora_fin'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $id_usuario_ejecutor = Flight::request()->data['id_usuario_ejecutor'] ?? null;
            $id_usuario_supervisor = Flight::request()->data['id_usuario_supervisor'] ?? null;
            $areas = Flight::request()->data['areas'] ?? array();

            $id_estado = $id_usuario_supervisor ? 4 : 3;

            if (!$id_proceso) {
                throw new Exception('Debe indicar el tipo de proceso de limpieza');
            }
            if (!$fecha_desde || !$fecha_hasta) {
                throw new Exception('Debe indicar el rango de fechas');
            }
            if ($fecha_desde > $fecha_hasta) {
                throw new Exception('La fecha inicial no puede ser mayor que la final');
            }
            if (!is_array($areas) || count($areas) == 0) {
                throw new Exception('Debe seleccionar al menos un área');
            }

            // Áreas válidas (configuradas para el proceso) con sus días marcados
            $areas_config = self::obtenerAreasConDias($db, $id_proceso);
            $mapa_areas = array();
            foreach ($areas_config as $a) {
                $mapa_areas[$a['id_area_fisica']] = $a;
            }

            // Días laborales del rango
            $dias_laborales = self::obtenerDiasLaborales($db, $fecha_desde, $fecha_hasta);

            $columnasDia = array(1 => 'lunes', 2 => 'martes', 3 => 'miercoles', 4 => 'jueves',
                                 5 => 'viernes', 6 => 'sabado', 7 => 'domingo');

            // Consumo por área (se calcula una vez por área, se reutiliza cada día)
            $ids_areas_validas = array_values(array_intersect(array_keys($mapa_areas), $areas));
            if (count($ids_areas_validas) == 0) {
                throw new Exception('Ninguna de las áreas seleccionadas está configurada para este proceso');
            }
            $calculo = self::calcularAreasProceso($db, $id_proceso, $ids_areas_validas);

            $total_creados = 0;
            $areas_sin_dias = array();

            foreach ($ids_areas_validas as $id_area) {
                $area = $mapa_areas[$id_area];

                // Días de semana marcados del área
                $dias_marcados = array();
                foreach ($columnasDia as $num => $col) {
                    if ((int) $area[$col] === 1) {
                        $dias_marcados[] = $num;
                    }
                }
                if (count($dias_marcados) === 0) {
                    $areas_sin_dias[] = $area['area'];
                    continue;
                }

                $datos = $calculo[$id_area] ?? array('elementos' => [], 'mobiliario' => [], 'productos' => []);

                // Un registro por cada día laboral que caiga en los días marcados
                foreach ($dias_laborales as $dl) {
                    if (!in_array((int) $dl['id_dia_semana'], $dias_marcados)) {
                        continue;
                    }
                    $fecha = $dl['fecha'];

                    // Registro principal
                    $sentence = $db->prepare("
                        INSERT INTO registros_limpieza (
                            id, id_tenant, fecha, fecha_programada,
                            hora_inicio, hora_fin, id_estado, observaciones,
                            id_area_fisica, id_tipo_proceso_limpieza,
                            id_usuario_ejecutor, id_usuario_supervisor
                        ) VALUES (
                            :id, :id_tenant, :fecha, :fecha_programada,
                            :hora_inicio, :hora_fin, :id_estado, :observaciones,
                            :id_area_fisica, :id_tipo_proceso_limpieza,
                            :id_usuario_ejecutor, :id_usuario_supervisor
                        )
                    ");
                    $id_registro = Uuid::generar();
                    $sentence->bindValue(':id', $id_registro);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':fecha', $fecha);
                    $sentence->bindParam(':fecha_programada', $fecha);
                    $sentence->bindParam(':hora_inicio', $hora_inicio);
                    $sentence->bindParam(':hora_fin', $hora_fin);
                    $sentence->bindValue(':id_estado', $id_estado, PDO::PARAM_INT);
                    $sentence->bindParam(':observaciones', $observaciones);
                    $sentence->bindParam(':id_area_fisica', $id_area);
                    $sentence->bindParam(':id_tipo_proceso_limpieza', $id_proceso);
                    $sentence->bindParam(':id_usuario_ejecutor', $id_usuario_ejecutor);
                    $sentence->bindParam(':id_usuario_supervisor', $id_usuario_supervisor);
                    $sentence->execute();

                    // Detalle: elementos y mobiliario
                    foreach ($datos['elementos'] as $elemento) {
                        $s2 = $db->prepare("
                            INSERT INTO registros_limpieza_detalle
                                (id, id_tenant, id_registro_limpieza, id_elemento_fisico, id_producto_mobiliario)
                            VALUES (:id, :id_tenant, :id_registro_limpieza, :id_elemento_fisico, NULL)
                        ");
                        $s2->bindValue(':id', Uuid::generar());
                        $s2->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $s2->bindParam(':id_registro_limpieza', $id_registro);
                        $s2->bindParam(':id_elemento_fisico', $elemento['id']);
                        $s2->execute();
                    }
                    foreach ($datos['mobiliario'] as $mobiliario) {
                        $s2 = $db->prepare("
                            INSERT INTO registros_limpieza_detalle
                                (id, id_tenant, id_registro_limpieza, id_elemento_fisico, id_producto_mobiliario)
                            VALUES (:id, :id_tenant, :id_registro_limpieza, NULL, :id_producto_mobiliario)
                        ");
                        $s2->bindValue(':id', Uuid::generar());
                        $s2->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $s2->bindParam(':id_registro_limpieza', $id_registro);
                        $s2->bindParam(':id_producto_mobiliario', $mobiliario['id']);
                        $s2->execute();
                    }

                    // Consumos
                    foreach ($datos['productos'] as $producto) {
                        $s2 = $db->prepare("
                            INSERT INTO registros_limpieza_consumos
                                (id, id_tenant, id_registro_limpieza, id_producto_limpieza,
                                 cantidad_consumida, id_unidad_medida)
                            VALUES (:id, :id_tenant, :id_registro_limpieza, :id_producto_limpieza,
                                 :cantidad_consumida, :id_unidad_medida)
                        ");
                        $s2->bindValue(':id', Uuid::generar());
                        $s2->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                        $s2->bindParam(':id_registro_limpieza', $id_registro);
                        $s2->bindParam(':id_producto_limpieza', $producto['id_producto_limpieza']);
                        $s2->bindParam(':cantidad_consumida', $producto['cantidad']);
                        $s2->bindParam(':id_unidad_medida', $producto['id_unidad_medida']);
                        $s2->execute();
                    }

                    $total_creados++;
                }
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'total_creados' => $total_creados,
                'areas_sin_dias' => $areas_sin_dias
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::crearMasivo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Días laborales (id_tipo_dia = 1) de un rango, con su día de semana.
     */
    private static function obtenerDiasLaborales($db, $fecha_desde, $fecha_hasta)
    {
        $sentence = $db->prepare("
            SELECT fecha, id_dia_semana
            FROM calendarios
            WHERE fecha BETWEEN :desde AND :hasta
                AND id_tipo_dia = 1
            ORDER BY fecha
        ");
        $sentence->bindParam(':desde', $fecha_desde);
        $sentence->bindParam(':hasta', $fecha_hasta);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Áreas activas configuradas para el proceso, con sus días de semana marcados.
     */
    private static function obtenerAreasConDias($db, $id_proceso)
    {
        $sentence = $db->prepare("
            SELECT
                af.id as id_area_fisica,
                af.nombre as area,
                axp.lunes, axp.martes, axp.miercoles, axp.jueves,
                axp.viernes, axp.sabado, axp.domingo
            FROM areas_fisicas_x_procesos_limpieza axp
            INNER JOIN areas_fisicas af ON axp.id_area_fisica = af.id
            WHERE axp.id_tipo_proceso_limpieza = :id_proceso
                AND axp.activo = 1
                AND af.activo = 1
                AND axp.id_tenant = :id_tenant
            ORDER BY af.nombre
        ");
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Lista los registros de aseo que caen en un filtro, para editarlos o eliminarlos
     * en lote. Filtra por rango de fechas (obligatorio) y, opcionalmente, por proceso,
     * área y estado.
     */
    public static function getEdicionMasivaPreview()
    {
        $db = Flight::db();

        $fecha_desde = Flight::request()->query['fecha_desde'] ?? $_GET['fecha_desde'] ?? null;
        $fecha_hasta = Flight::request()->query['fecha_hasta'] ?? $_GET['fecha_hasta'] ?? null;
        $id_proceso = Flight::request()->query['id_proceso'] ?? $_GET['id_proceso'] ?? null;
        $id_area = Flight::request()->query['id_area'] ?? $_GET['id_area'] ?? null;
        $id_estado = Flight::request()->query['id_estado'] ?? $_GET['id_estado'] ?? null;

        if (!$fecha_desde || !$fecha_hasta) {
            Flight::json(array('error' => 'Debe indicar el rango de fechas'), 400);
            return;
        }
        if ($fecha_desde > $fecha_hasta) {
            Flight::json(array('error' => 'La fecha inicial no puede ser mayor que la final'), 400);
            return;
        }

        $where = " WHERE rl.id_tenant = :id_tenant AND rl.fecha BETWEEN :desde AND :hasta ";
        if ($id_proceso) {
            $where .= " AND rl.id_tipo_proceso_limpieza = :id_proceso ";
        }
        if ($id_area) {
            $where .= " AND rl.id_area_fisica = :id_area ";
        }
        if ($id_estado) {
            $where .= " AND rl.id_estado = :id_estado ";
        }

        $sentence = $db->prepare("
            SELECT
                rl.id,
                rl.fecha,
                rl.hora_inicio,
                rl.hora_fin,
                rl.id_estado,
                rl.observaciones,
                af.nombre as area,
                tpl.nombre as proceso,
                erl.nombre as estado,
                TRIM(CONCAT_WS(' ', ue.primer_nombre, ue.primer_apellido)) as ejecutor,
                TRIM(CONCAT_WS(' ', us.primer_nombre, us.primer_apellido)) as supervisor,
                (SELECT COALESCE(SUM(cantidad_consumida), 0)
                   FROM registros_limpieza_consumos
                  WHERE id_registro_limpieza = rl.id) as total_consumido
            FROM registros_limpieza rl
            INNER JOIN areas_fisicas af ON rl.id_area_fisica = af.id
            INNER JOIN tipos_proceso_limpieza tpl ON rl.id_tipo_proceso_limpieza = tpl.id
            INNER JOIN estados_registro_limpieza erl ON rl.id_estado = erl.id
            LEFT JOIN usuarios ue_user ON rl.id_usuario_ejecutor = ue_user.id
            LEFT JOIN personas ue ON ue_user.id_persona = ue.id
            LEFT JOIN usuarios us_user ON rl.id_usuario_supervisor = us_user.id
            LEFT JOIN personas us ON us_user.id_persona = us.id
            $where
            ORDER BY rl.fecha DESC, af.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':desde', $fecha_desde);
        $sentence->bindParam(':hasta', $fecha_hasta);
        if ($id_proceso) {
            $sentence->bindParam(':id_proceso', $id_proceso);
        }
        if ($id_area) {
            $sentence->bindParam(':id_area', $id_area);
        }
        if ($id_estado) {
            $sentence->bindValue(':id_estado', $id_estado, PDO::PARAM_INT);
        }
        $sentence->execute();

        Flight::json($sentence->fetchAll());
    }

    /**
     * Edita en lote los registros indicados. Solo se actualizan los campos que vengan
     * en 'cambios'; los que no se envían quedan intactos.
     * No toca el inventario ni los consumos: cambiar quién ejecutó, la fecha o la hora
     * no altera lo que se gastó.
     */
    public static function editarLote()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $ids = Flight::request()->data['ids'] ?? array();
            $cambios = Flight::request()->data['cambios'] ?? array();

            if (!is_array($ids) || count($ids) == 0) {
                throw new Exception('Debe seleccionar al menos un registro');
            }
            if (!is_array($cambios) || count($cambios) == 0) {
                throw new Exception('Debe indicar al menos un cambio a aplicar');
            }

            // Campos permitidos en la edición masiva
            $permitidos = array(
                'fecha', 'hora_inicio', 'hora_fin',
                'id_estado', 'id_usuario_ejecutor', 'id_usuario_supervisor'
            );

            $sets = array();
            $valores = array();
            foreach ($permitidos as $campo) {
                if (array_key_exists($campo, $cambios)) {
                    $sets[] = "$campo = :$campo";
                    $valores[$campo] = $cambios[$campo] !== '' ? $cambios[$campo] : null;
                }
            }

            if (count($sets) == 0) {
                throw new Exception('Ninguno de los campos enviados se puede editar en lote');
            }

            // Marcadores con nombre para el IN (PDO no permite mezclar :nombre con ?)
            $marcadores = array();
            $ids_valores = array();
            foreach (array_values($ids) as $i => $id) {
                $marcadores[] = ':id' . $i;
                $ids_valores[':id' . $i] = $id;
            }
            $placeholders = implode(',', $marcadores);

            // Si se marca como Supervisado hay que tener supervisor: el del lote o el que ya tenga cada registro
            if (isset($valores['id_estado']) && (int) $valores['id_estado'] === 4
                && empty($valores['id_usuario_supervisor'])) {

                $verifica = $db->prepare("
                    SELECT COUNT(*) as sin_supervisor
                    FROM registros_limpieza
                    WHERE id_tenant = :id_tenant
                        AND id_usuario_supervisor IS NULL
                        AND id IN ($placeholders)
                ");
                $verifica->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                foreach ($ids_valores as $marcador => $valor) {
                    $verifica->bindValue($marcador, $valor);
                }
                $verifica->execute();
                $fila = $verifica->fetch();

                if ((int) $fila['sin_supervisor'] > 0) {
                    throw new Exception('Hay ' . $fila['sin_supervisor'] . ' registro(s) sin supervisor. '
                        . 'Para marcarlos como Supervisado debe indicar también quién supervisó.');
                }
            }

            $sql = "UPDATE registros_limpieza SET " . implode(', ', $sets)
                 . " WHERE id_tenant = :id_tenant AND id IN ($placeholders)";

            $sentence = $db->prepare($sql);
            foreach ($valores as $campo => $valor) {
                $sentence->bindValue(':' . $campo, $valor);
            }
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($ids_valores as $marcador => $valor) {
                $sentence->bindValue($marcador, $valor);
            }
            $sentence->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'total_editados' => $sentence->rowCount()
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::editarLote: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Elimina en lote los registros indicados y devuelve al inventario lo que habían
     * descontado. El movimiento original no se toca (queda como histórico): se genera
     * un movimiento de entrada compensatorio con lo devuelto.
     *
     * Solo se devuelve el consumo que efectivamente movió inventario, es decir el que
     * tiene id_movimiento_inventario. El consumo registrado cuando no había existencias
     * no descontó nada, así que tampoco se devuelve.
     */
    public static function eliminarLote()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $ids = Flight::request()->data['ids'] ?? array();
            $id_usuario = Flight::request()->data['id_usuario'] ?? null;

            if (!is_array($ids) || count($ids) == 0) {
                throw new Exception('Debe seleccionar al menos un registro');
            }

            // Marcadores con nombre para el IN
            $marcadores = array();
            $ids_valores = array();
            foreach (array_values($ids) as $i => $id) {
                $marcadores[] = ':id' . $i;
                $ids_valores[':id' . $i] = $id;
            }
            $placeholders = implode(',', $marcadores);

            // Consumo a devolver, consolidado por producto
            $sentence = $db->prepare("
                SELECT
                    rlc.id_producto_limpieza,
                    SUM(rlc.cantidad_consumida) as cantidad,
                    p.nombre,
                    p.stock_actual,
                    p.precio_unitario
                FROM registros_limpieza_consumos rlc
                INNER JOIN productos p ON rlc.id_producto_limpieza = p.id
                WHERE rlc.id_tenant = :id_tenant
                    AND rlc.id_movimiento_inventario IS NOT NULL
                    AND rlc.id_registro_limpieza IN ($placeholders)
                GROUP BY rlc.id_producto_limpieza, p.nombre, p.stock_actual, p.precio_unitario
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($ids_valores as $marcador => $valor) {
                $sentence->bindValue($marcador, $valor);
            }
            $sentence->execute();
            $a_devolver = $sentence->fetchAll();

            $productos_devueltos = array();

            if (count($a_devolver) > 0) {
                // Concepto dedicado para que la entrada quede identificable en inventario
                $sentence = $db->prepare("
                    SELECT id
                    FROM conceptos_movimiento
                    WHERE nombre = 'Entrada por Anulación de Aseo' AND id_tenant = :id_tenant
                    LIMIT 1
                ");
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
                $concepto = $sentence->fetch();

                if (!$concepto) {
                    throw new Exception("No existe el concepto de movimiento 'Entrada por Anulación de Aseo'. "
                        . "Ejecute el script 12-concepto-anulacion-aseo.sql en esta base de datos.");
                }

                $observaciones_movimiento = 'Anulación de ' . count($ids) . ' registro(s) de aseo';

                $sentence = $db->prepare("
                    INSERT INTO movimientos_productos (
                        id,
                        id_tenant,
                        fecha_movimiento,
                        id_concepto_movimiento,
                        id_estado,
                        observaciones,
                        id_usuario_registro
                    ) VALUES (
                        :id,
                        :id_tenant,
                        NOW(),
                        :id_concepto_movimiento,
                        3, -- Aprobado
                        :observaciones,
                        :id_usuario
                    )
                ");
                $id_movimiento = Uuid::generar();
                $sentence->bindValue(':id', $id_movimiento);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->bindParam(':id_concepto_movimiento', $concepto['id']);
                $sentence->bindParam(':observaciones', $observaciones_movimiento);
                $sentence->bindParam(':id_usuario', $id_usuario);
                $sentence->execute();

                foreach ($a_devolver as $item) {
                    $cantidad = round($item['cantidad'], 2);
                    if ($cantidad <= 0) {
                        continue;
                    }

                    $sentence = $db->prepare("
                        INSERT INTO movimientos_productos_detalle (
                            id,
                            id_tenant,
                            id_movimiento,
                            id_producto,
                            cantidad,
                            stock_anterior,
                            precio_unitario
                        ) VALUES (
                            :id,
                            :id_tenant,
                            :id_movimiento,
                            :id_producto,
                            :cantidad,
                            :stock_anterior,
                            :precio_unitario
                        )
                    ");
                    $sentence->bindValue(':id', Uuid::generar());
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $item['id_producto_limpieza']);
                    $sentence->bindParam(':cantidad', $cantidad);
                    $sentence->bindParam(':stock_anterior', $item['stock_actual']);
                    $sentence->bindParam(':precio_unitario', $item['precio_unitario']);
                    $sentence->execute();

                    // Devolver la cantidad al stock
                    $sentence = $db->prepare("
                        UPDATE productos
                        SET stock_anterior = stock_actual,
                            stock_actual = stock_actual + :cantidad,
                            id_ultimo_movimiento = :id_movimiento,
                            fecha_ultimo_movimiento = NOW()
                        WHERE id = :id_producto AND id_tenant = :id_tenant
                    ");
                    $sentence->bindParam(':cantidad', $cantidad);
                    $sentence->bindParam(':id_movimiento', $id_movimiento);
                    $sentence->bindParam(':id_producto', $item['id_producto_limpieza']);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();

                    $productos_devueltos[] = array(
                        'producto' => $item['nombre'],
                        'cantidad' => $cantidad
                    );
                }
            }

            // Borrar consumos, detalle y por último los registros
            $sentence = $db->prepare("
                DELETE FROM registros_limpieza_consumos
                WHERE id_tenant = :id_tenant AND id_registro_limpieza IN ($placeholders)
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($ids_valores as $marcador => $valor) {
                $sentence->bindValue($marcador, $valor);
            }
            $sentence->execute();

            $sentence = $db->prepare("
                DELETE FROM registros_limpieza_detalle
                WHERE id_tenant = :id_tenant AND id_registro_limpieza IN ($placeholders)
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($ids_valores as $marcador => $valor) {
                $sentence->bindValue($marcador, $valor);
            }
            $sentence->execute();

            $sentence = $db->prepare("
                DELETE FROM registros_limpieza
                WHERE id_tenant = :id_tenant AND id IN ($placeholders)
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            foreach ($ids_valores as $marcador => $valor) {
                $sentence->bindValue($marcador, $valor);
            }
            $sentence->execute();

            $total_eliminados = $sentence->rowCount();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'total_eliminados' => $total_eliminados,
                'productos_devueltos' => $productos_devueltos
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en RegistrosLimpieza::eliminarLote: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }
}