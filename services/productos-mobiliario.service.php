<?php
class ProductosMobiliario
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pm.*, 
            p.nombre nombre_producto,
            tpm.nombre as tipo_mobiliario
            FROM productos_mobiliario pm
            INNER JOIN productos p ON pm.id_producto = p.id
            LEFT JOIN tipos_producto_mobiliario tpm ON pm.id_tipo_producto_mobiliario = tpm.id
            WHERE pm.id_tenant = :id_tenant
            ORDER BY pm.id DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pm.*, 
            p.nombre nombre_producto,
            tpm.nombre as tipo_mobiliario
            FROM productos_mobiliario pm
            INNER JOIN productos p ON pm.id_producto = p.id
            LEFT JOIN tipos_producto_mobiliario tpm ON pm.id_tipo_producto_mobiliario = tpm.id
            WHERE pm.id = :id
            AND pm.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_mobiliario = Flight::request()->data['id_tipo_producto_mobiliario'];
        $requiere_limpieza = Flight::request()->data['requiere_limpieza'] ?? 1;
        $requiere_desinfeccion = Flight::request()->data['requiere_desinfeccion'] ?? 1;
        $fecha_adquisicion = Flight::request()->data['fecha_adquisicion'];
        $numero_serie = Flight::request()->data['numero_serie'];

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO productos_mobiliario(
            id,
            id_tenant,
            id_producto,
            id_tipo_producto_mobiliario,
            requiere_limpieza,
            requiere_desinfeccion,
            fecha_adquisicion,
            numero_serie
        ) VALUES (
            :id,
            :id_tenant,
            :id_producto,
            :id_tipo_producto_mobiliario,
            :requiere_limpieza,
            :requiere_desinfeccion,
            :fecha_adquisicion,
            :numero_serie
        )");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_mobiliario', $id_tipo_producto_mobiliario);
        $sentence->bindParam(':requiere_limpieza', $requiere_limpieza);
        $sentence->bindParam(':requiere_desinfeccion', $requiere_desinfeccion);
        $sentence->bindParam(':fecha_adquisicion', $fecha_adquisicion);
        $sentence->bindParam(':numero_serie', $numero_serie);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $id_producto = Flight::request()->data['id_producto'];
        $id_tipo_producto_mobiliario = Flight::request()->data['id_tipo_producto_mobiliario'];
        $requiere_limpieza = Flight::request()->data['requiere_limpieza'];
        $requiere_desinfeccion = Flight::request()->data['requiere_desinfeccion'];
        $fecha_adquisicion = Flight::request()->data['fecha_adquisicion'];
        $numero_serie = Flight::request()->data['numero_serie'];

        $sentence = $db->prepare("UPDATE productos_mobiliario SET 
            id_producto = :id_producto,
            id_tipo_producto_mobiliario = :id_tipo_producto_mobiliario,
            requiere_limpieza = :requiere_limpieza,
            requiere_desinfeccion = :requiere_desinfeccion,
            fecha_adquisicion = :fecha_adquisicion,
            numero_serie = :numero_serie
            WHERE id = :id
            AND id_tenant = :id_tenant");

        $sentence->bindParam(':id_producto', $id_producto);
        $sentence->bindParam(':id_tipo_producto_mobiliario', $id_tipo_producto_mobiliario);
        $sentence->bindParam(':requiere_limpieza', $requiere_limpieza);
        $sentence->bindParam(':requiere_desinfeccion', $requiere_desinfeccion);
        $sentence->bindParam(':fecha_adquisicion', $fecha_adquisicion);
        $sentence->bindParam(':numero_serie', $numero_serie);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM productos_mobiliario WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getProductosDisponiblesParaMobiliario()
    {
        error_log("getProductosDisponiblesParaMobiliario");
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT p.*, u.abreviatura abreviatura_unidad, tp.nombre tipo_producto_nombre
            FROM productos p
            LEFT JOIN unidades_medida u ON p.id_unidad_medida = u.id
            LEFT JOIN tipos_producto tp ON p.id_tipo_producto = tp.id AND tp.id_tenant = p.id_tenant
            WHERE tp.codigo = 'MOBILIARIO' 
            AND p.activo = 1
            AND p.id_tenant = :id_tenant
            AND p.id NOT IN (
                SELECT id_producto FROM productos_mobiliario WHERE id_tenant = :id_tenant_sub
            )
            ORDER BY p.nombre ASC
        ");
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getMobiliarioConStock()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                pm.id as id_producto_mobiliario,
                pm.numero_serie,
                pm.fecha_adquisicion,
                p.id as id_producto,
                p.nombre as nombre_producto,
                p.stock_actual,
                p.stock_minimo,
                tpm.nombre as tipo_mobiliario,
                um.abreviatura as unidad_medida
            FROM productos_mobiliario pm
            INNER JOIN productos p ON pm.id_producto = p.id
            LEFT JOIN tipos_producto_mobiliario tpm ON pm.id_tipo_producto_mobiliario = tpm.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE p.stock_actual > 0 
            AND p.activo = 1
            AND pm.id_tenant = :id_tenant
            ORDER BY p.nombre ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function guardarAsignacionArea()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $requestData = Flight::request()->data->getData();

            $id_area = $requestData['id_area'];
            $id_movimiento = $requestData['id_movimiento'];
            $productos = $requestData['productos'] ?? [];

            if (empty($productos)) {
                throw new Exception("No hay productos para asignar");
            }

            // Guardar las asignaciones en productos_mobiliario_x_areas_fisicas
            // NOTA: Removido el campo 'activo' del INSERT
            $stmtAsignacion = $db->prepare("
                INSERT INTO productos_mobiliario_x_areas_fisicas (
                    id,
                    id_tenant,
                    id_producto_mobiliario,
                    id_area,
                    cantidad,
                    orden_limpieza,
                    id_movimiento
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_producto_mobiliario,
                    :id_area,
                    :cantidad,
                    :orden_limpieza,
                    :id_movimiento
                ) ON DUPLICATE KEY UPDATE
                    cantidad = cantidad + VALUES(cantidad),
                    orden_limpieza = VALUES(orden_limpieza),
                    id_movimiento = VALUES(id_movimiento)
            ");

            foreach ($productos as $item) {
                $idPmaf = Uuid::generar();
                $stmtAsignacion->bindValue(':id', $idPmaf);
                $stmtAsignacion->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmtAsignacion->bindParam(':id_producto_mobiliario', $item['id_producto_mobiliario']);
                $stmtAsignacion->bindParam(':id_area', $id_area);
                $stmtAsignacion->bindParam(':cantidad', $item['cantidad']);
                $orden = $item['orden_limpieza'] ?? null;
                $stmtAsignacion->bindParam(':orden_limpieza', $orden);
                $stmtAsignacion->bindParam(':id_movimiento', $id_movimiento);
                $stmtAsignacion->execute();
            }

            $db->commit();

            Flight::json([
                'success' => true,
                'message' => 'Asignación guardada correctamente'
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al guardar asignación: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    public static function procesarDevolucionAsignacion()
    {
        $db = Flight::db();

        try {
            $requestData = Flight::request()->data->getData();

            $id_asignacion = $requestData['id_asignacion'];
            $id_movimiento_devolucion = $requestData['id_movimiento_devolucion'];

            // ELIMINAR completamente la asignación en lugar de marcarla como inactiva
            $stmt = $db->prepare("
                DELETE FROM productos_mobiliario_x_areas_fisicas 
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $stmt->bindParam(':id', $id_asignacion);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json([
                'success' => true,
                'message' => 'Asignación eliminada correctamente'
            ]);
        } catch (Exception $e) {
            error_log("Error al procesar devolución de asignación: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    // Obtener conceptos de devolución
    public static function getConceptosDevolucion()
    {
        $db = Flight::db();
        $stmt = $db->prepare("
            SELECT id, nombre 
            FROM conceptos_movimiento 
            WHERE tipo = 'E' 
            AND (nombre LIKE '%Devolución%' OR nombre LIKE '%devolución%')
            AND id_tenant = :id_tenant
            ORDER BY nombre
        ");
        $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmt->execute();
        $response = $stmt->fetchAll();
        Flight::json($response);
    }
    // ===== MÉTODOS SIMPLIFICADOS PARA PRODUCTOS DE LIMPIEZA =====

    // Obtener productos de limpieza asignados (simplificado)
    public static function getProductosLimpiezaAsignados($id_mobiliario)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                                SELECT 
                                    pmpl.id,
                                    pl.id as id_producto_limpieza,
                                    pl.componentes,
                                    pl.modo_uso,
                                    p.nombre as nombre_producto,
                                    p.stock_actual,
                                    tpl.nombre as tipo_limpieza,
                                    um.abreviatura as unidad_medida
                                FROM productos_mobiliario_x_productos_limpieza pmpl
                                INNER JOIN productos_limpieza pl ON pmpl.id_producto_limpieza = pl.id
                                INNER JOIN productos p ON pl.id_producto = p.id
                                LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
                                LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                                WHERE pmpl.id_producto_mobiliario = :id_mobiliario
                                AND pmpl.id_tenant = :id_tenant
                                ORDER BY p.nombre
                            ");
        $sentence->bindParam(':id_mobiliario', $id_mobiliario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    // Obtener productos de limpieza disponibles (no asignados aún)
    public static function getProductosLimpiezaDisponibles($id_mobiliario)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            pl.id as id_producto_limpieza,
            pl.componentes,
            pl.modo_uso,
            p.id as id_producto,
            p.nombre as nombre_producto,
            p.stock_actual,
            p.precio_unitario,
            tpl.nombre as tipo_limpieza,
            um.abreviatura as unidad_medida
        FROM productos_limpieza pl
        INNER JOIN productos p ON pl.id_producto = p.id
        LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
        LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
        WHERE p.activo = 1
        AND pl.id_tenant = :id_tenant
        AND pl.id NOT IN (
            SELECT id_producto_limpieza 
            FROM productos_mobiliario_x_productos_limpieza 
            WHERE id_producto_mobiliario = :id_mobiliario
            AND id_tenant = :id_tenant_sub
        )
        ORDER BY p.nombre
    ");
        $sentence->bindParam(':id_mobiliario', $id_mobiliario);
        $sentence->bindValue(':id_tenant_sub', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    // Asignar múltiples productos de limpieza
    public static function asignarProductosLimpieza()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $requestData = Flight::request()->data->getData();
            $id_producto_mobiliario = $requestData['id_producto_mobiliario'];
            $productos_ids = $requestData['productos_ids'] ?? [];

            if (empty($productos_ids)) {
                Flight::json(['error' => 'No se seleccionaron productos'], 400);
                return;
            }

            // Preparar statement para insertar
            $stmt = $db->prepare("
            INSERT IGNORE INTO productos_mobiliario_x_productos_limpieza 
            (id, id_tenant, id_producto_mobiliario, id_producto_limpieza) 
            VALUES (:id, :id_tenant, :id_producto_mobiliario, :id_producto_limpieza)
        ");

            $insertados = 0;
            foreach ($productos_ids as $id_producto_limpieza) {
                $idPmpl = Uuid::generar();
                $stmt->bindValue(':id', $idPmpl);
                $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $stmt->bindParam(':id_producto_mobiliario', $id_producto_mobiliario);
                $stmt->bindParam(':id_producto_limpieza', $id_producto_limpieza);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    $insertados++;
                }
            }

            $db->commit();

            Flight::json([
                'mensaje' => "$insertados producto(s) asociado(s) correctamente",
                'insertados' => $insertados
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => 'Error al asignar productos: ' . $e->getMessage()], 500);
        }
    }


    // Eliminar asociación de producto de limpieza
    public static function eliminarAsignacionLimpieza($id)
    {
        $db = Flight::db();

        try {
            // Primero verificar si hay procesos usando este producto
            $checkStmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM productos_mobiliario_x_procesos_limpieza_productos 
            WHERE id_productos_mobiliario_x_productos_limpieza = :id
            AND id_tenant = :id_tenant
        ");
            $checkStmt->bindParam(':id', $id);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $result = $checkStmt->fetch();

            if ($result['total'] > 0) {
                // Enviar un error más amigable
                Flight::json([
                    'error' => 'No se puede eliminar este producto porque está siendo usado en procesos de limpieza',
                    'tipo' => 'producto_en_uso',
                    'procesos_count' => $result['total']
                ], 400);
                return;
            }

            // Si no hay procesos usándolo, eliminar
            $stmt = $db->prepare("
            DELETE FROM productos_mobiliario_x_productos_limpieza 
            WHERE id = :id
            AND id_tenant = :id_tenant
        ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['mensaje' => 'Producto desasociado correctamente']);
        } catch (Exception $e) {
            error_log("Error SQL al eliminar asociación: " . $e->getMessage());
            Flight::json([
                'error' => 'Error al procesar la solicitud',
                'tipo' => 'error_servidor'
            ], 500);
        }
    }

    // ===== MÉTODOS PARA PROCESOS DE LIMPIEZA =====

    // Obtener procesos de limpieza del mobiliario 
    public static function getProcesosLimpiezaAsignados($id_mobiliario)
    {
        $db = Flight::db();

        // Primero obtener los procesos
        $sentence = $db->prepare("
            SELECT 
                pmpl.id,
                pmpl.id_tipo_proceso_limpieza,
                tpl.nombre as nombre_proceso,
                tpl.descripcion as descripcion_proceso
            FROM productos_mobiliario_x_procesos_limpieza pmpl
            INNER JOIN tipos_proceso_limpieza tpl ON pmpl.id_tipo_proceso_limpieza = tpl.id
            WHERE pmpl.id_producto_mobiliario = :id_mobiliario
            AND pmpl.id_tenant = :id_tenant
            ORDER BY tpl.nombre
        ");
        $sentence->bindParam(':id_mobiliario', $id_mobiliario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $procesos = $sentence->fetchAll();

        // Para cada proceso, obtener sus productos
        foreach ($procesos as &$proceso) {
            $stmtProductos = $db->prepare("
                SELECT 
                    pmlpp.id as id_relacion,
                    pmlpp.id_productos_mobiliario_x_productos_limpieza as id_asignacion,
                    pmlpp.cantidad_sugerida,
                    pmlpp.instrucciones,
                    pl.id as id_producto_limpieza,
                    p.nombre as nombre_producto,
                    pl.componentes,
                    um.abreviatura as unidad_medida
                FROM productos_mobiliario_x_procesos_limpieza_productos pmlpp
                INNER JOIN productos_mobiliario_x_productos_limpieza pml 
                    ON pmlpp.id_productos_mobiliario_x_productos_limpieza = pml.id
                INNER JOIN productos_limpieza pl ON pml.id_producto_limpieza = pl.id
                INNER JOIN productos p ON pl.id_producto = p.id
                LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                WHERE pmlpp.id_proceso_limpieza = :id_proceso
                AND pmlpp.id_tenant = :id_tenant
            ");
            $stmtProductos->bindParam(':id_proceso', $proceso['id']);
            $stmtProductos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtProductos->execute();
            $proceso['productos'] = $stmtProductos->fetchAll();
        }

        Flight::json($procesos);
    }

    // Obtener productos de limpieza disponibles para procesos (solo los ya asociados al mobiliario)
    public static function getProductosLimpiezaParaProceso($id_mobiliario)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT 
            pml.id as id_asignacion,
            pl.id as id_producto_limpieza,
            p.nombre as nombre_producto,
            pl.componentes,
            pl.modo_uso,  -- Asegurarse de incluir este campo
            um.abreviatura as unidad_medida,
            tpl.nombre as tipo_limpieza
        FROM productos_mobiliario_x_productos_limpieza pml
        INNER JOIN productos_limpieza pl ON pml.id_producto_limpieza = pl.id
        INNER JOIN productos p ON pl.id_producto = p.id
        LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
        LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
        WHERE pml.id_producto_mobiliario = :id_mobiliario
        AND pml.id_tenant = :id_tenant
        ORDER BY p.nombre
    ");
        $sentence->bindParam(':id_mobiliario', $id_mobiliario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Asignar proceso de limpieza
    public static function asignarProcesoLimpieza()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $requestData = Flight::request()->data->getData();

            // Verificar si ya existe este tipo de proceso
            $checkStmt = $db->prepare("
                    SELECT COUNT(*) as total 
                    FROM productos_mobiliario_x_procesos_limpieza 
                    WHERE id_producto_mobiliario = :id_producto_mobiliario 
                    AND id_tipo_proceso_limpieza = :id_tipo_proceso_limpieza
                    AND id_tenant = :id_tenant
            ");

            $checkStmt->bindParam(':id_producto_mobiliario', $requestData['id_producto_mobiliario']);
            $checkStmt->bindParam(':id_tipo_proceso_limpieza', $requestData['id_tipo_proceso_limpieza']);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $result = $checkStmt->fetch();

            if ($result['total'] > 0) {
                throw new Exception("Este tipo de proceso ya está asignado al mobiliario");
            }

            $id_proceso = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO productos_mobiliario_x_procesos_limpieza (
                    id,
                    id_tenant,
                    id_producto_mobiliario,
                    id_tipo_proceso_limpieza
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_producto_mobiliario,
                    :id_tipo_proceso_limpieza
                )
            ");

            $stmt->bindValue(':id', $id_proceso);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_producto_mobiliario', $requestData['id_producto_mobiliario']);
            $stmt->bindParam(':id_tipo_proceso_limpieza', $requestData['id_tipo_proceso_limpieza']);
            $stmt->execute();

            // Si se enviaron productos, agregarlos
            if (isset($requestData['productos']) && is_array($requestData['productos'])) {
                $stmtProducto = $db->prepare("
                    INSERT INTO productos_mobiliario_x_procesos_limpieza_productos (
                        id,
                        id_tenant,
                        id_proceso_limpieza,
                        id_productos_mobiliario_x_productos_limpieza,
                        cantidad_sugerida,
                        instrucciones
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :id_proceso_limpieza,
                        :id_productos_mobiliario_x_productos_limpieza,
                        :cantidad_sugerida,
                        :instrucciones
                    )
                ");

                foreach ($requestData['productos'] as $producto) {
                    $idPmplp = Uuid::generar();
                    $stmtProducto->bindValue(':id', $idPmplp);
                    $stmtProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtProducto->bindParam(':id_proceso_limpieza', $id_proceso);
                    $stmtProducto->bindParam(':id_productos_mobiliario_x_productos_limpieza', $producto['id_asignacion']);
                    $stmtProducto->bindParam(':cantidad_sugerida', $producto['cantidad_sugerida']);
                    $stmtProducto->bindParam(':instrucciones', $producto['instrucciones']);
                    $stmtProducto->execute();
                }
            }

            $db->commit();
            Flight::json(['id' => $id_proceso, 'mensaje' => 'Proceso asignado correctamente']);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => 'Error al asignar proceso: ' . $e->getMessage()], 500);
        }
    }

    // Actualizar proceso de limpieza
    public static function actualizarProcesoLimpieza()
    {
        $db = Flight::db();

        try {
            $db->beginTransaction();

            $requestData = Flight::request()->data->getData();
            $id_proceso = $requestData['id'];

            // Eliminar productos existentes
            $stmtDelete = $db->prepare("
                DELETE FROM productos_mobiliario_x_procesos_limpieza_productos 
                WHERE id_proceso_limpieza = :id_proceso
                AND id_tenant = :id_tenant
            ");
            $stmtDelete->bindParam(':id_proceso', $id_proceso);
            $stmtDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDelete->execute();

            // Insertar nuevos productos
            if (isset($requestData['productos']) && is_array($requestData['productos'])) {
                $stmtInsert = $db->prepare("
                    INSERT INTO productos_mobiliario_x_procesos_limpieza_productos (
                        id,
                        id_tenant,
                        id_proceso_limpieza,
                        id_productos_mobiliario_x_productos_limpieza,
                        cantidad_sugerida,
                        instrucciones
                    ) VALUES (
                        :id,
                        :id_tenant,
                        :id_proceso_limpieza,
                        :id_productos_mobiliario_x_productos_limpieza,
                        :cantidad_sugerida,
                        :instrucciones
                    )
                ");

                foreach ($requestData['productos'] as $producto) {
                    $idPmplp = Uuid::generar();
                    $stmtInsert->bindValue(':id', $idPmplp);
                    $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsert->bindParam(':id_proceso_limpieza', $id_proceso);
                    $stmtInsert->bindParam(':id_productos_mobiliario_x_productos_limpieza', $producto['id_asignacion']);
                    $stmtInsert->bindParam(':cantidad_sugerida', $producto['cantidad_sugerida']);
                    $stmtInsert->bindParam(':instrucciones', $producto['instrucciones']);
                    $stmtInsert->execute();
                }
            }

            $db->commit();
            Flight::json(['mensaje' => 'Proceso actualizado correctamente']);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => 'Error al actualizar: ' . $e->getMessage()], 500);
        }
    }

    // Eliminar proceso de limpieza
    public static function eliminarProcesoLimpieza($id)
    {
        $db = Flight::db();

        try {
            $stmt = $db->prepare("
                DELETE FROM productos_mobiliario_x_procesos_limpieza 
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['mensaje' => 'Proceso eliminado correctamente']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
    //Agregar producto a proceso existente
    public static function agregarProductoAProceso()
    {
        $db = Flight::db();

        try {
            $requestData = Flight::request()->data->getData();

            $id = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO productos_mobiliario_x_procesos_limpieza_productos (
                    id,
                    id_tenant,
                    id_proceso_limpieza,
                    id_productos_mobiliario_x_productos_limpieza,
                    cantidad_sugerida,
                    instrucciones
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_proceso_limpieza,
                    :id_productos_mobiliario_x_productos_limpieza,
                    :cantidad_sugerida,
                    :instrucciones
                )
            ");

            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_proceso_limpieza', $requestData['id_proceso_limpieza']);
            $stmt->bindParam(':id_productos_mobiliario_x_productos_limpieza', $requestData['id_productos_mobiliario_x_productos_limpieza']);
            $stmt->bindParam(':cantidad_sugerida', $requestData['cantidad_sugerida']);
            $stmt->bindParam(':instrucciones', $requestData['instrucciones']);
            $stmt->execute();

            Flight::json(['id' => $id, 'mensaje' => 'Producto agregado al proceso']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al agregar producto: ' . $e->getMessage()], 500);
        }
    }

    // Eliminar producto de proceso
    public static function eliminarProductoDeProceso($id)
    {
        $db = Flight::db();

        try {
            $stmt = $db->prepare("
                DELETE FROM productos_mobiliario_x_procesos_limpieza_productos 
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id', $id);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();

            Flight::json(['mensaje' => 'Producto eliminado del proceso']);
        } catch (Exception $e) {
            Flight::json(['error' => 'Error al eliminar: ' . $e->getMessage()], 500);
        }
    }
}