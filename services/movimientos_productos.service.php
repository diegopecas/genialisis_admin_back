<?php
class MovimientosProductos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            mp.id,
            mp.fecha_movimiento,
            mp.id_concepto_movimiento,
            cm.nombre AS concepto,
            cm.tipo,
            CASE cm.tipo 
                WHEN 'E' THEN 'Entrada'
                WHEN 'S' THEN 'Salida'
                WHEN 'I' THEN 'Inicial'
            END AS tipo_descripcion,
            mp.id_proveedor,
            CASE 
                WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
            END AS proveedor,
            mp.id_estado,
            emp.nombre AS estado,
            mp.observaciones,
            mp.id_usuario_registro,
            -- Obtener nombre del usuario que registró
            CONCAT(IFNULL(per_reg.primer_nombre, ''), ' ', IFNULL(per_reg.primer_apellido, '')) AS usuario_registro,
            mp.fecha_registro,
            mp.id_usuario_aprobado,
            -- Obtener nombre del usuario que aprobó
            CONCAT(IFNULL(per_apr.primer_nombre, ''), ' ', IFNULL(per_apr.primer_apellido, '')) AS usuario_aprobado,
            mp.fecha_aprobado,
            mp.id_usuario_anulado,
            -- Obtener nombre del usuario que anuló
            CONCAT(IFNULL(per_anu.primer_nombre, ''), ' ', IFNULL(per_anu.primer_apellido, '')) AS usuario_anulado,
            mp.fecha_anulado,
            COUNT(mpd.id) AS total_items,
            SUM(mpd.cantidad) AS total_unidades,
            SUM(mpd.cantidad * mpd.precio_unitario) AS total_valor
        FROM movimientos_productos mp
        INNER JOIN conceptos_movimiento cm ON mp.id_concepto_movimiento = cm.id
        LEFT JOIN proveedores pr ON mp.id_proveedor = pr.id
        LEFT JOIN personas per ON pr.id_persona = per.id
        INNER JOIN estados_movimientos_productos emp ON mp.id_estado = emp.id
        LEFT JOIN movimientos_productos_detalle mpd ON mp.id = mpd.id_movimiento
        -- Joins para obtener los nombres de usuarios
        LEFT JOIN usuarios u_reg ON mp.id_usuario_registro = u_reg.id
        LEFT JOIN personas per_reg ON u_reg.id_persona = per_reg.id
        LEFT JOIN usuarios u_apr ON mp.id_usuario_aprobado = u_apr.id
        LEFT JOIN personas per_apr ON u_apr.id_persona = per_apr.id
        LEFT JOIN usuarios u_anu ON mp.id_usuario_anulado = u_anu.id
        LEFT JOIN personas per_anu ON u_anu.id_persona = per_anu.id
        WHERE mp.id_tenant = :id_tenant
        GROUP BY mp.id
        ORDER BY mp.fecha_movimiento DESC, mp.id DESC
    ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();

        // Obtener movimiento principal CON LOS NOMBRES DE USUARIOS
        $sentence = $db->prepare("
                                SELECT 
                                    mp.*,
                                    cm.nombre AS concepto,
                                    cm.tipo,
                                    CASE 
                                        WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                                        ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                                    END AS proveedor,
                                    emp.nombre AS estado,
                                    
                                    -- Agregar nombres de usuarios
                                    CONCAT(IFNULL(per_reg.primer_nombre, ''), ' ', IFNULL(per_reg.primer_apellido, '')) AS nombre_usuario_registro,
                                    CONCAT(IFNULL(per_apr.primer_nombre, ''), ' ', IFNULL(per_apr.primer_apellido, '')) AS nombre_usuario_aprobado,
                                    CONCAT(IFNULL(per_anu.primer_nombre, ''), ' ', IFNULL(per_anu.primer_apellido, '')) AS nombre_usuario_anulado
                                    
                                FROM movimientos_productos mp
                                INNER JOIN conceptos_movimiento cm ON mp.id_concepto_movimiento = cm.id
                                LEFT JOIN proveedores pr ON mp.id_proveedor = pr.id
                                LEFT JOIN personas per ON pr.id_persona = per.id
                                INNER JOIN estados_movimientos_productos emp ON mp.id_estado = emp.id
                                
                                -- Agregar JOINs para obtener nombres de usuarios
                                LEFT JOIN usuarios u_reg ON mp.id_usuario_registro = u_reg.id
                                LEFT JOIN personas per_reg ON u_reg.id_persona = per_reg.id
                                
                                LEFT JOIN usuarios u_apr ON mp.id_usuario_aprobado = u_apr.id
                                LEFT JOIN personas per_apr ON u_apr.id_persona = per_apr.id
                                
                                LEFT JOIN usuarios u_anu ON mp.id_usuario_anulado = u_anu.id
                                LEFT JOIN personas per_anu ON u_anu.id_persona = per_anu.id
                                
                                WHERE mp.id = :id AND mp.id_tenant = :id_tenant
                            ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $movimiento = $sentence->fetch();

        // Obtener detalle
        $sentence = $db->prepare("
                                SELECT 
                                    mpd.*,
                                    p.nombre AS producto_nombre,
                                    p.stock_actual,
                                    um.abreviatura
                                FROM movimientos_productos_detalle mpd
                                INNER JOIN productos p ON mpd.id_producto = p.id
                                LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                WHERE mpd.id_movimiento = :id AND mpd.id_tenant = :id_tenant
                            ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $detalle = $sentence->fetchAll();

        $movimiento['detalle'] = $detalle;

        Flight::json($movimiento);
    }

    public static function new()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            // IMPORTANTE: Obtener los datos como array, no como Collection
            $requestData = Flight::request()->data->getData();

            // Validar datos requeridos
            if (!isset($requestData['id_concepto_movimiento'])) {
                throw new Exception('El concepto de movimiento es requerido');
            }

            // Preparar datos con valores por defecto
            $fechaMovimiento = $requestData['fecha_movimiento'] ?? date('Y-m-d H:i:s');
            $idConceptoMovimiento = $requestData['id_concepto_movimiento'];
            $idProveedor = $requestData['id_proveedor'] ?? null;
            $observaciones = $requestData['observaciones'] ?? '';
            $idUsuarioRegistro = $requestData['id_usuario_registro'] ?? 1;
            $detalle = $requestData['detalle'] ?? [];

            // Insertar movimiento principal
            $stmt = $db->prepare("
                INSERT INTO movimientos_productos (
                    id,
                    id_tenant,
                    fecha_movimiento,
                    id_concepto_movimiento,
                    id_proveedor,
                    observaciones,
                    id_usuario_registro,
                    fecha_registro,
                    id_estado
                ) VALUES (
                    :id,
                    :id_tenant,
                    :fecha_movimiento,
                    :id_concepto_movimiento,
                    :id_proveedor,
                    :observaciones,
                    :id_usuario_registro,
                    NOW(),
                    1  -- Estado: EN PROCESO DE REGISTRO
                )
            ");

            $idMov = Uuid::generar();
            $stmt->bindValue(':id', $idMov);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':fecha_movimiento', $fechaMovimiento);
            $stmt->bindParam(':id_concepto_movimiento', $idConceptoMovimiento);
            $stmt->bindParam(':id_proveedor', $idProveedor);
            $stmt->bindParam(':observaciones', $observaciones);
            $stmt->bindParam(':id_usuario_registro', $idUsuarioRegistro);
            $stmt->execute();

            $idMovimiento = $idMov;

            // Obtener tipo de movimiento
            $stmtTipo = $db->prepare("
                SELECT tipo FROM conceptos_movimiento 
                WHERE id = :id_concepto AND id_tenant = :id_tenant
            ");
            $stmtTipo->bindParam(':id_concepto', $idConceptoMovimiento);
            $stmtTipo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtTipo->execute();
            $concepto = $stmtTipo->fetch(PDO::FETCH_ASSOC);

            if (!$concepto) {
                throw new Exception('Concepto de movimiento no válido');
            }

            $tipoMovimiento = $concepto['tipo'];

            // Insertar detalle del movimiento si existe
            if (!empty($detalle) && is_array($detalle)) {
                $stmtDetalle = $db->prepare("
                    INSERT INTO movimientos_productos_detalle (
                        id,
                        id_tenant,
                        id_movimiento,
                        id_producto,
                        cantidad,
                        precio_unitario,
                        fecha_vencimiento
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :id_movimiento,
                        :id_producto,
                        :cantidad,
                        :precio_unitario,
                        :fecha_vencimiento
                    )
                ");

                foreach ($detalle as $item) {
                    // Validar que el item tenga los campos necesarios
                    if (!isset($item['id_producto']) || !isset($item['cantidad'])) {
                        error_log("Item de detalle incompleto, omitiendo: " . json_encode($item));
                        continue;
                    }

                    $idProducto = $item['id_producto'];

                    // Preparar valores del detalle
                    $cantidad = floatval($item['cantidad']);
                    $precioUnitario = floatval($item['precio_unitario'] ?? 0);
                    $fechaVencimiento = !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null;

                    // Insertar detalle SIN actualizar el stock del producto
                    $idMovDet = Uuid::generar();
                    $stmtDetalle->bindValue(':id', $idMovDet);
                    $stmtDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtDetalle->bindParam(':id_movimiento', $idMovimiento);
                    $stmtDetalle->bindParam(':id_producto', $idProducto);
                    $stmtDetalle->bindParam(':cantidad', $cantidad);
                    $stmtDetalle->bindParam(':precio_unitario', $precioUnitario);
                    $stmtDetalle->bindParam(':fecha_vencimiento', $fechaVencimiento);
                    $stmtDetalle->execute();

                    // NO ACTUALIZAR STOCK - Se hará cuando se registre el movimiento
                }
            }

            $db->commit();

            Flight::json([
                'success' => true,
                'id' => $idMovimiento,
                'message' => 'Movimiento creado como borrador. No afecta el inventario hasta ser registrado.'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en crear movimiento: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            Flight::json(['error' => 'Error al crear movimiento: ' . $e->getMessage()], 500);
        }
    }

    // MÉTODO registrar - También corregido
    public static function registrar()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            // Obtener datos como array
            $requestData = Flight::request()->data->getData();
            $idMovimiento = $requestData['id'] ?? null;

            if (!$idMovimiento) {
                throw new Exception('ID de movimiento requerido');
            }

            // Verificar estado actual
            $stmtVerificar = $db->prepare("
            SELECT mp.id_estado, mp.id_concepto_movimiento, cm.tipo
            FROM movimientos_productos mp
            INNER JOIN conceptos_movimiento cm ON cm.id = mp.id_concepto_movimiento
            WHERE mp.id = :id AND mp.id_tenant = :id_tenant
        ");
            $stmtVerificar->bindParam(':id', $idMovimiento);
            $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtVerificar->execute();
            $movimiento = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$movimiento) {
                throw new Exception('Movimiento no encontrado');
            }

            if ($movimiento['id_estado'] != 1) {
                throw new Exception('Solo se pueden registrar movimientos en estado EN PROCESO DE REGISTRO');
            }

            $tipoMovimiento = $movimiento['tipo'];

            // Obtener detalle del movimiento
            $stmtDetalle = $db->prepare("
            SELECT id_producto, cantidad, precio_unitario
            FROM movimientos_productos_detalle
            WHERE id_movimiento = :id_movimiento AND id_tenant = :id_tenant
        ");
            $stmtDetalle->bindParam(':id_movimiento', $idMovimiento);
            $stmtDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDetalle->execute();
            $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

            if (empty($detalles)) {
                throw new Exception('El movimiento no tiene productos');
            }

            // Actualizar stock de cada producto
            foreach ($detalles as $detalle) {
                $idProducto = $detalle['id_producto'];
                $cantidad = floatval($detalle['cantidad']);
                $precioUnitario = floatval($detalle['precio_unitario']);

                // Actualizar stock según tipo de movimiento
                if ($tipoMovimiento == 'E') {
                    // Entrada: sumar al stock
                    $stmtUpdateStock = $db->prepare("
                    UPDATE productos 
                    SET stock_actual = stock_actual + :cantidad,
                        precio_unitario = :precio,
                        fecha_ultimo_movimiento = NOW(),
                        id_ultimo_movimiento = :id_movimiento
                    WHERE id = :id_producto AND id_tenant = :id_tenant
                ");
                    $stmtUpdateStock->bindParam(':cantidad', $cantidad);
                    $stmtUpdateStock->bindParam(':precio', $precioUnitario);
                    $stmtUpdateStock->bindParam(':id_movimiento', $idMovimiento);
                    $stmtUpdateStock->bindParam(':id_producto', $idProducto);
                } elseif ($tipoMovimiento == 'S') {
                    // Salida: restar del stock (NO actualizar precio en salidas)
                    $stmtUpdateStock = $db->prepare("
                    UPDATE productos 
                    SET stock_actual = stock_actual - :cantidad,
                        fecha_ultimo_movimiento = NOW(),
                        id_ultimo_movimiento = :id_movimiento
                    WHERE id = :id_producto AND id_tenant = :id_tenant
                ");
                    $stmtUpdateStock->bindParam(':cantidad', $cantidad);
                    $stmtUpdateStock->bindParam(':id_movimiento', $idMovimiento);
                    $stmtUpdateStock->bindParam(':id_producto', $idProducto);
                } elseif ($tipoMovimiento == 'I') {
                    // Inventario inicial: establecer stock
                    $stmtUpdateStock = $db->prepare("
                    UPDATE productos 
                    SET stock_actual = :cantidad,
                        precio_unitario = :precio,
                        fecha_ultimo_movimiento = NOW(),
                        id_ultimo_movimiento = :id_movimiento
                    WHERE id = :id_producto AND id_tenant = :id_tenant
                ");
                    $stmtUpdateStock->bindParam(':cantidad', $cantidad);
                    $stmtUpdateStock->bindParam(':precio', $precioUnitario);
                    $stmtUpdateStock->bindParam(':id_movimiento', $idMovimiento);
                    $stmtUpdateStock->bindParam(':id_producto', $idProducto);
                } else {
                    throw new Exception("Tipo de movimiento no válido: " . $tipoMovimiento);
                }

                $stmtUpdateStock->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtUpdateStock->execute();

                // Validar stock negativo en salidas
                if ($tipoMovimiento == 'S') {
                    $stmtCheckStock = $db->prepare("
                    SELECT stock_actual, nombre FROM productos WHERE id = :id AND id_tenant = :id_tenant
                ");
                    $stmtCheckStock->bindParam(':id', $idProducto);
                    $stmtCheckStock->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtCheckStock->execute();
                    $producto = $stmtCheckStock->fetch(PDO::FETCH_ASSOC);

                    if ($producto && $producto['stock_actual'] < 0) {
                        throw new Exception("Stock insuficiente para el producto: {$producto['nombre']}");
                    }
                }
            }

            // Actualizar estado del movimiento a REGISTRADO
            $stmtUpdate = $db->prepare("
            UPDATE movimientos_productos 
            SET id_estado = 2,
                fecha_registro = NOW()
            WHERE id = :id AND id_tenant = :id_tenant
        ");
            $stmtUpdate->bindParam(':id', $idMovimiento);
            $stmtUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtUpdate->execute();

            $db->commit();

            Flight::json([
                'success' => true,
                'message' => 'Movimiento registrado correctamente. El inventario ha sido actualizado.'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en registrar movimiento: ' . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function anular()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $data = Flight::request()->data;
            $idMovimiento = $data['id'];
            $usuarioAnulado = $data['usuario_anulado']; // Este viene como ID numérico desde el frontend

            // Verificar estado actual y tipo de movimiento
            $stmtVerificar = $db->prepare("
            SELECT mp.id_estado, mp.id_concepto_movimiento, cm.tipo
            FROM movimientos_productos mp
            INNER JOIN conceptos_movimiento cm ON cm.id = mp.id_concepto_movimiento
            WHERE mp.id = :id AND mp.id_tenant = :id_tenant
        ");
            $stmtVerificar->bindParam(':id', $idMovimiento);
            $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtVerificar->execute();
            $movimiento = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$movimiento) {
                throw new Exception('Movimiento no encontrado');
            }

            // Si el movimiento está en estado 1 (EN PROCESO), solo cambiar estado sin afectar inventario
            if ($movimiento['id_estado'] == 1) {
                $stmt = $db->prepare("
                UPDATE movimientos_productos 
                SET id_estado = 4,
                    id_usuario_anulado = :usuario,
                    fecha_anulado = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");
                $stmt->bindParam(':id', $idMovimiento);
                $stmt->bindParam(':usuario', $usuarioAnulado); // usuarios.id es UUID (CHAR(36)): se envia como string
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->execute();

                $db->commit();

                Flight::json([
                    'success' => true,
                    'message' => 'Movimiento anulado correctamente (no afectó inventario)'
                ]);
                return;
            }

            // Si está en estado 2 o 3 (REGISTRADO o APROBADO), revertir inventario
            if ($movimiento['id_estado'] == 2 || $movimiento['id_estado'] == 3) {
                $tipoMovimiento = $movimiento['tipo'];

                // Obtener detalle del movimiento
                $stmtDetalle = $db->prepare("
                SELECT id_producto, cantidad, precio_unitario
                FROM movimientos_productos_detalle
                WHERE id_movimiento = :id_movimiento AND id_tenant = :id_tenant
            ");
                $stmtDetalle->bindParam(':id_movimiento', $idMovimiento);
                $stmtDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtDetalle->execute();
                $detalles = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC);

                // Revertir stock de cada producto
                foreach ($detalles as $detalle) {
                    $idProducto = $detalle['id_producto'];
                    $cantidad = $detalle['cantidad'];

                    // Verificar que sea el último movimiento del producto
                    $stmtCheckUltimo = $db->prepare("
                    SELECT id_ultimo_movimiento FROM productos WHERE id = :id AND id_tenant = :id_tenant
                ");
                    $stmtCheckUltimo->bindParam(':id', $idProducto);
                    $stmtCheckUltimo->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtCheckUltimo->execute();
                    $producto = $stmtCheckUltimo->fetch(PDO::FETCH_ASSOC);

                    if ($producto['id_ultimo_movimiento'] != $idMovimiento) {
                        throw new Exception("No se puede anular porque hay movimientos posteriores para algunos productos");
                    }

                    // Revertir el stock según el tipo de movimiento
                    if ($tipoMovimiento == 'E') {
                        // Si fue entrada, restar la cantidad
                        $stmtRevertir = $db->prepare("
                        UPDATE productos 
                        SET stock_actual = stock_actual - :cantidad,
                            fecha_ultimo_movimiento = (
                                SELECT MAX(fecha_movimiento) 
                                FROM movimientos_productos mp
                                INNER JOIN movimientos_productos_detalle mpd ON mpd.id_movimiento = mp.id
                                WHERE mpd.id_producto = :id_producto 
                                AND mp.id < :id_movimiento
                                AND mp.id_estado IN (2,3)
                            ),
                            id_ultimo_movimiento = (
                                SELECT MAX(mp.id) 
                                FROM movimientos_productos mp
                                INNER JOIN movimientos_productos_detalle mpd ON mpd.id_movimiento = mp.id
                                WHERE mpd.id_producto = :id_producto2
                                AND mp.id < :id_movimiento2
                                AND mp.id_estado IN (2,3)
                            )
                        WHERE id = :id_producto3 AND id_tenant = :id_tenant
                    ");
                    } elseif ($tipoMovimiento == 'S') {
                        // Si fue salida, sumar la cantidad
                        $stmtRevertir = $db->prepare("
                        UPDATE productos 
                        SET stock_actual = stock_actual + :cantidad,
                            fecha_ultimo_movimiento = (
                                SELECT MAX(fecha_movimiento) 
                                FROM movimientos_productos mp
                                INNER JOIN movimientos_productos_detalle mpd ON mpd.id_movimiento = mp.id
                                WHERE mpd.id_producto = :id_producto 
                                AND mp.id < :id_movimiento
                                AND mp.id_estado IN (2,3)
                            ),
                            id_ultimo_movimiento = (
                                SELECT MAX(mp.id) 
                                FROM movimientos_productos mp
                                INNER JOIN movimientos_productos_detalle mpd ON mpd.id_movimiento = mp.id
                                WHERE mpd.id_producto = :id_producto2
                                AND mp.id < :id_movimiento2
                                AND mp.id_estado IN (2,3)
                            )
                        WHERE id = :id_producto3 AND id_tenant = :id_tenant
                    ");
                    } elseif ($tipoMovimiento == 'I') {
                        // Si fue inventario inicial, restaurar stock anterior
                        $stmtRevertir = $db->prepare("
                        UPDATE productos 
                        SET stock_actual = stock_anterior,
                            fecha_ultimo_movimiento = NULL,
                            id_ultimo_movimiento = NULL
                        WHERE id = :id_producto3 AND id_tenant = :id_tenant
                    ");
                    }

                    // Bind parameters según el tipo
                    if ($tipoMovimiento != 'I') {
                        $stmtRevertir->bindParam(':cantidad', $cantidad);
                        $stmtRevertir->bindParam(':id_producto', $idProducto);
                        $stmtRevertir->bindParam(':id_movimiento', $idMovimiento);
                        $stmtRevertir->bindParam(':id_producto2', $idProducto);
                        $stmtRevertir->bindParam(':id_movimiento2', $idMovimiento);
                    }
                    $stmtRevertir->bindParam(':id_producto3', $idProducto);
                    $stmtRevertir->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtRevertir->execute();
                }

                // Actualizar estado del movimiento a ANULADO
                $stmtUpdate = $db->prepare("
                UPDATE movimientos_productos 
                SET id_estado = 4,
                    id_usuario_anulado = :usuario,
                    fecha_anulado = NOW()
                WHERE id = :id AND id_tenant = :id_tenant
            ");
                $stmtUpdate->bindParam(':id', $idMovimiento);
                $stmtUpdate->bindParam(':usuario', $usuarioAnulado);
                $stmtUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtUpdate->execute();

                $db->commit();

                Flight::json([
                    'success' => true,
                    'message' => 'Movimiento anulado y stock revertido correctamente'
                ]);
            } else {
                throw new Exception('No se puede anular un movimiento en este estado');
            }
        } catch (Exception $e) {
            $db->rollBack();
            error_log('Error en anular movimiento: ' . $e->getMessage());
            Flight::json(['error' => 'Error al anular movimiento: ' . $e->getMessage()], 500);
        }
    }

    public static function aprobar()
    {
        $db = Flight::db();

        try {
            $data = Flight::request()->data;
            $idMovimiento = $data['id'];
            $usuarioAprobado = $data['usuario_aprobado']; // Este viene como ID numérico desde el frontend

            // Verificar estado actual
            $stmtVerificar = $db->prepare("
            SELECT id_estado FROM movimientos_productos WHERE id = :id AND id_tenant = :id_tenant
        ");
            $stmtVerificar->bindParam(':id', $idMovimiento);
            $stmtVerificar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtVerificar->execute();
            $movimiento = $stmtVerificar->fetch(PDO::FETCH_ASSOC);

            if (!$movimiento) {
                throw new Exception('Movimiento no encontrado');
            }

            // Solo se puede aprobar si está en estado REGISTRADO (2)
            if ($movimiento['id_estado'] != 2) {
                throw new Exception('Solo se pueden aprobar movimientos en estado REGISTRADO');
            }

            // Actualizar estado a APROBADO (no tocar inventario)
            $stmt = $db->prepare("
            UPDATE movimientos_productos 
            SET id_estado = 3,
                id_usuario_aprobado = :usuario,
                fecha_aprobado = NOW()
            WHERE id = :id AND id_tenant = :id_tenant
        ");
            $stmt->bindParam(':id', $idMovimiento);
            $stmt->bindParam(':usuario', $usuarioAprobado); // usuarios.id es UUID (CHAR(36)): se envia como string
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json([
                'success' => true,
                'message' => 'Movimiento aprobado correctamente'
            ]);
        } catch (Exception $e) {
            error_log('Error en aprobar movimiento: ' . $e->getMessage());
            Flight::json(['error' => 'Error al aprobar movimiento: ' . $e->getMessage()], 500);
        }
    }




    public static function getByProducto($id_producto)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                mp.id,
                mp.fecha_movimiento,
                cm.nombre AS concepto,
                cm.tipo,
                mpd.cantidad,
                mpd.stock_anterior,
                mpd.precio_unitario,
                emp.nombre AS estado,
                mp.id_usuario_registro
            FROM movimientos_productos mp
            INNER JOIN movimientos_productos_detalle mpd ON mp.id = mpd.id_movimiento
            INNER JOIN conceptos_movimiento cm ON mp.id_concepto_movimiento = cm.id
            INNER JOIN estados_movimientos_productos emp ON mp.id_estado = emp.id
            WHERE mpd.id_producto = :id_producto AND mp.id_tenant = :id_tenant
            ORDER BY mp.fecha_movimiento DESC, mp.id DESC
        ");
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function actualizar()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $id_proveedor = Flight::request()->data['id_proveedor'] ?? null;

            // Verificar que el movimiento existe y está en estado 1
            $sentence = $db->prepare("SELECT id_estado FROM movimientos_productos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $movimiento = $sentence->fetch();

            if (!$movimiento) {
                throw new Exception("Movimiento no encontrado");
            }

            if ($movimiento['id_estado'] != 1) {
                throw new Exception("Solo se pueden actualizar movimientos en estado EN PROCESO DE REGISTRO");
            }

            // Actualizar solo observaciones y proveedor (no se debe cambiar fecha ni concepto)
            $sentence = $db->prepare("
                UPDATE movimientos_productos SET 
                    observaciones = :observaciones,
                    id_proveedor = :id_proveedor
                WHERE id = :id AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_proveedor', $id_proveedor);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'message' => 'Movimiento actualizado correctamente'));
        } catch (Exception $e) {
            error_log("Error al actualizar movimiento: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function agregarProductos()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            // Obtener datos como array
            $requestData = Flight::request()->data->getData();
            $id_movimiento = $requestData['id_movimiento'] ?? null;
            $productos = $requestData['productos'] ?? [];

            if (!$id_movimiento) {
                throw new Exception("ID de movimiento requerido");
            }

            if (empty($productos) || !is_array($productos)) {
                throw new Exception("Debe incluir al menos un producto");
            }

            // Verificar que el movimiento existe y obtener su estado
            $sentence = $db->prepare("
            SELECT mp.id_estado, mp.id_concepto_movimiento, cm.tipo 
            FROM movimientos_productos mp
            INNER JOIN conceptos_movimiento cm ON mp.id_concepto_movimiento = cm.id
            WHERE mp.id = :id AND mp.id_tenant = :id_tenant
        ");
            $sentence->bindParam(':id', $id_movimiento);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $movimiento = $sentence->fetch();

            if (!$movimiento) {
                throw new Exception("Movimiento no encontrado");
            }

            // Solo se pueden modificar movimientos en estado 1 (EN PROCESO DE REGISTRO)
            if ($movimiento['id_estado'] != 1) {
                throw new Exception("Solo se pueden modificar movimientos en estado EN PROCESO DE REGISTRO");
            }

            $tipo_movimiento = $movimiento['tipo'];

            // IMPORTANTE: En estado 1 (borrador), eliminar TODOS los productos existentes
            // para reemplazarlos con la nueva lista completa
            $deleteStmt = $db->prepare("
            DELETE FROM movimientos_productos_detalle 
            WHERE id_movimiento = :id_movimiento AND id_tenant = :id_tenant
        ");
            $deleteStmt->bindParam(':id_movimiento', $id_movimiento);
            $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $deleteStmt->execute();

            error_log("Productos eliminados del movimiento $id_movimiento");

            // Insertar todos los productos (la lista completa viene del frontend)
            $sentenceDetalle = $db->prepare("
            INSERT INTO movimientos_productos_detalle(
                id,
                id_tenant,
                id_movimiento,
                id_producto,
                cantidad,
                stock_anterior,
                precio_unitario,
                fecha_vencimiento
            ) VALUES (
                :id,
                :id_tenant,
                :id_movimiento,
                :id_producto,
                :cantidad,
                :stock_anterior,
                :precio_unitario,
                :fecha_vencimiento
            )
        ");

            $productosInsertados = 0;

            foreach ($productos as $item) {
                // Validar datos del producto
                if (!isset($item['id_producto']) || !isset($item['cantidad'])) {
                    error_log("Producto sin datos completos, omitiendo: " . json_encode($item));
                    continue;
                }

                $id_producto = $item['id_producto'];
                $cantidad = floatval($item['cantidad']);
                $precio_unitario = floatval($item['precio_unitario'] ?? 0);
                $fecha_vencimiento = !empty($item['fecha_vencimiento']) ? $item['fecha_vencimiento'] : null;

                // Validar cantidad
                if ($cantidad <= 0) {
                    error_log("Cantidad inválida para producto $id_producto: $cantidad");
                    continue;
                }

                // Obtener stock actual del producto (será el stock_anterior)
                $sentenceStock = $db->prepare("SELECT stock_actual FROM productos WHERE id = :id AND id_tenant = :id_tenant");
                $sentenceStock->bindParam(':id', $id_producto);
                $sentenceStock->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceStock->execute();
                $producto = $sentenceStock->fetch();

                if (!$producto) {
                    error_log("Producto no encontrado: $id_producto");
                    continue;
                }

                $stock_anterior = floatval($producto['stock_actual']);

                // Para movimientos de SALIDA en borrador, validar stock disponible
                if ($tipo_movimiento == 'S' && $cantidad > $stock_anterior) {
                    throw new Exception("Stock insuficiente para el producto ID: $id_producto. Disponible: $stock_anterior, Solicitado: $cantidad");
                }

                // Insertar detalle (sin actualizar stock del producto porque es borrador)
                $idMovDet2 = Uuid::generar();
                $sentenceDetalle->bindValue(':id', $idMovDet2);
                $sentenceDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceDetalle->bindParam(':id_movimiento', $id_movimiento);
                $sentenceDetalle->bindParam(':id_producto', $id_producto);
                $sentenceDetalle->bindParam(':cantidad', $cantidad);
                $sentenceDetalle->bindParam(':stock_anterior', $stock_anterior);
                $sentenceDetalle->bindParam(':precio_unitario', $precio_unitario);
                $sentenceDetalle->bindParam(':fecha_vencimiento', $fecha_vencimiento);
                $sentenceDetalle->execute();

                $productosInsertados++;

                error_log("Producto insertado - ID: $id_producto, Cantidad: $cantidad, Precio: $precio_unitario");
            }

            if ($productosInsertados == 0) {
                throw new Exception("No se pudo insertar ningún producto válido");
            }

            // NO ACTUALIZAR STOCK porque el movimiento está en estado 1 (borrador)
            // El stock solo se actualizará cuando se registre el movimiento

            $db->commit();

            error_log("Movimiento $id_movimiento actualizado con $productosInsertados productos");

            Flight::json([
                'success' => true,
                'message' => "Movimiento actualizado correctamente con $productosInsertados productos",
                'productos_actualizados' => $productosInsertados
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al agregar/actualizar productos: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
    public static function getComprobante($id)
    {
        $db = Flight::db();

        try {
            // Obtener movimiento principal con toda la información necesaria
            $sentence = $db->prepare("
            SELECT 
                mp.*,
                cm.nombre AS concepto,
                cm.tipo,
                CASE cm.tipo 
                    WHEN 'E' THEN 'ENTRADA'
                    WHEN 'S' THEN 'SALIDA'
                    WHEN 'I' THEN 'INVENTARIO INICIAL'
                END AS tipo_descripcion,
                CASE 
                    WHEN per.razon_social IS NOT NULL AND per.razon_social != '' THEN per.razon_social
                    ELSE CONCAT(IFNULL(per.primer_nombre, ''), ' ', IFNULL(per.primer_apellido, ''))
                END AS proveedor,
                per.numero_identificacion AS proveedor_documento,
                per.direccion AS proveedor_direccion,
                per.telefono AS proveedor_telefono,
                emp.nombre AS estado,
                -- Información adicional para el comprobante
                (SELECT COUNT(*) FROM movimientos_productos_detalle WHERE id_movimiento = mp.id) AS total_items,
                (SELECT SUM(cantidad) FROM movimientos_productos_detalle WHERE id_movimiento = mp.id) AS total_unidades,
                (SELECT SUM(cantidad * precio_unitario) FROM movimientos_productos_detalle WHERE id_movimiento = mp.id) AS total_valor
            FROM movimientos_productos mp
            INNER JOIN conceptos_movimiento cm ON mp.id_concepto_movimiento = cm.id
            LEFT JOIN proveedores pr ON mp.id_proveedor = pr.id
            LEFT JOIN personas per ON pr.id_persona = per.id
            INNER JOIN estados_movimientos_productos emp ON mp.id_estado = emp.id
            WHERE mp.id = :id AND mp.id_tenant = :id_tenant
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $movimiento = $sentence->fetch();

            if (!$movimiento) {
                Flight::json(array('error' => 'Movimiento no encontrado'), 404);
                return;
            }

            // Obtener detalle con información completa del producto
            $sentence = $db->prepare("
            SELECT 
                mpd.*,
                p.id AS producto_codigo,
                p.nombre AS producto_nombre,
                p.descripcion AS producto_descripcion,
                p.stock_actual,
                p.stock_minimo,
                um.nombre AS unidad_nombre,
                um.abreviatura,
                tp.nombre AS tipo_producto,
                -- Calcular stock final después del movimiento
                CASE :tipo
                    WHEN 'E' THEN (mpd.stock_anterior + mpd.cantidad)
                    WHEN 'S' THEN (mpd.stock_anterior - mpd.cantidad)
                    WHEN 'I' THEN mpd.cantidad
                END AS stock_final,
                (mpd.cantidad * mpd.precio_unitario) AS subtotal
            FROM movimientos_productos_detalle mpd
            INNER JOIN productos p ON mpd.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id
            WHERE mpd.id_movimiento = :id AND mpd.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':tipo', $movimiento['tipo']);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $detalle = $sentence->fetchAll();

            // Agregar el detalle al movimiento
            $movimiento['detalle'] = $detalle;

            // Agregar información adicional útil para el comprobante
            $movimiento['fecha_impresion'] = date('Y-m-d H:i:s');
            // Obtener nombre del usuario que registró
            if ($movimiento['id_usuario_registro']) {
                $sentenceUsuario = $db->prepare("
                    SELECT CONCAT(IFNULL(primer_nombre, ''), ' ', IFNULL(primer_apellido, '')) AS nombre_completo
                    FROM usuarios u
                    LEFT JOIN personas p ON u.id_persona = p.id
                    WHERE u.usuario = :username AND u.id_tenant = :id_tenant
                ");
                $sentenceUsuario->bindParam(':username', $movimiento['id_usuario_registro']);
                $sentenceUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceUsuario->execute();
                $usuario = $sentenceUsuario->fetch();
                if ($usuario) {
                    $movimiento['nombre_id_usuario_registro'] = $usuario['nombre_completo'];
                }
            }

            // Obtener nombre del usuario que aprobó
            if ($movimiento['id_usuario_aprobado']) {
                $sentenceUsuario = $db->prepare("
                    SELECT CONCAT(IFNULL(primer_nombre, ''), ' ', IFNULL(primer_apellido, '')) AS nombre_completo
                    FROM usuarios u
                    LEFT JOIN personas p ON u.id_persona = p.id
                    WHERE u.usuario = :username AND u.id_tenant = :id_tenant
                ");
                $sentenceUsuario->bindParam(':username', $movimiento['id_usuario_aprobado']);
                $sentenceUsuario->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceUsuario->execute();
                $usuario = $sentenceUsuario->fetch();
                if ($usuario) {
                    $movimiento['nombre_id_usuario_aprobado'] = $usuario['nombre_completo'];
                }
            }
            Flight::json($movimiento);
        } catch (Exception $e) {
            error_log("Error obteniendo comprobante: " . $e->getMessage());
            Flight::json(array('error' => 'Error al obtener el comprobante: ' . $e->getMessage()), 500);
        }
    }
}