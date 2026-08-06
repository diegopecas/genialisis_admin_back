<?php
class ElementosFisicos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM elementos_fisicos WHERE id_tenant = :id_tenant ORDER BY id DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
                SELECT ef.*, 
                    um.nombre as unidad_nombre,
                    um.abreviatura as unidad_abreviatura
                FROM elementos_fisicos ef
                LEFT JOIN unidades_medida um ON ef.id_unidad_medida = um.id
                WHERE ef.id = :id
                AND ef.id_tenant = :id_tenant
            ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();

        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $material = Flight::request()->data['material'];
        $id_unidad_medida = Flight::request()->data['id_unidad_medida']; // AGREGAR ESTA LÍNEA

        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO elementos_fisicos(
        id,
        id_tenant,
        nombre,
        descripcion,
        material,
        id_unidad_medida
    ) VALUES (
        :id,
        :id_tenant,
        :nombre,
        :descripcion,
        :material,
        :id_unidad_medida
    )");

        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':material', $material);
        $sentence->bindParam(':id_unidad_medida', $id_unidad_medida); // AGREGAR ESTA LÍNEA
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();

        $id = Flight::request()->data['id'];
        $nombre = Flight::request()->data['nombre'];
        $descripcion = Flight::request()->data['descripcion'];
        $material = Flight::request()->data['material'];
        $id_unidad_medida = Flight::request()->data['id_unidad_medida']; // AGREGAR ESTA LÍNEA

        $sentence = $db->prepare("UPDATE elementos_fisicos SET 
        nombre = :nombre,
        descripcion = :descripcion,
        material = :material,
        id_unidad_medida = :id_unidad_medida
        WHERE id = :id
        AND id_tenant = :id_tenant");

        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':descripcion', $descripcion);
        $sentence->bindParam(':material', $material);
        $sentence->bindParam(':id_unidad_medida', $id_unidad_medida); // AGREGAR ESTA LÍNEA
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM elementos_fisicos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    // ===== MÉTODOS PARA PROCESOS DE LIMPIEZA =====

    // Obtener procesos de limpieza del elemento físico
    public static function getProcesosLimpiezaAsignados($id_elemento)
    {
        $db = Flight::db();

        // Primero obtener los procesos
        $sentence = $db->prepare("
            SELECT 
                efpl.id,
                efpl.id_tipo_proceso_limpieza,
                tpl.nombre as nombre_proceso,
                tpl.descripcion as descripcion_proceso
            FROM elementos_fisicos_x_procesos_limpieza efpl
            INNER JOIN tipos_proceso_limpieza tpl ON efpl.id_tipo_proceso_limpieza = tpl.id
            WHERE efpl.id_elemento_fisico = :id_elemento
            AND efpl.id_tenant = :id_tenant
            ORDER BY tpl.nombre
        ");
        $sentence->bindParam(':id_elemento', $id_elemento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $procesos = $sentence->fetchAll();

        // Para cada proceso, obtener sus productos
        foreach ($procesos as &$proceso) {
            $stmtProductos = $db->prepare("
                SELECT 
                    efplp.id as id_relacion,
                    efplp.id_producto_limpieza,
                    efplp.cantidad_sugerida,
                    efplp.instrucciones,
                    pl.id as id_producto_limpieza,
                    p.nombre as nombre_producto,
                    pl.componentes,
                    pl.modo_uso,
                    um.abreviatura as unidad_medida
                FROM elementos_fisicos_x_procesos_limpieza_productos efplp
                INNER JOIN productos_limpieza pl ON efplp.id_producto_limpieza = pl.id
                INNER JOIN productos p ON pl.id_producto = p.id
                LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
                WHERE efplp.id_elementos_fisicos_x_procesos_limpieza = :id_proceso
                AND efplp.id_tenant = :id_tenant
            ");
            $stmtProductos->bindParam(':id_proceso', $proceso['id']);
            $stmtProductos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtProductos->execute();
            $proceso['productos'] = $stmtProductos->fetchAll();
        }

        Flight::json($procesos);
    }

    // Obtener todos los productos de limpieza disponibles
    public static function getProductosLimpiezaDisponibles()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                pl.id as id_producto_limpieza,
                p.nombre as nombre_producto,
                pl.componentes,
                pl.modo_uso,
                p.stock_actual,
                um.abreviatura as unidad_medida,
                tpl.nombre as tipo_limpieza
            FROM productos_limpieza pl
            INNER JOIN productos p ON pl.id_producto = p.id
            LEFT JOIN unidades_medida um ON p.id_unidad_medida = um.id
            LEFT JOIN tipos_producto_limpieza tpl ON pl.id_tipo_producto_limpieza = tpl.id
            WHERE p.activo = 1
            AND pl.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
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
                FROM elementos_fisicos_x_procesos_limpieza 
                WHERE id_elemento_fisico = :id_elemento_fisico 
                AND id_tipo_proceso_limpieza = :id_tipo_proceso_limpieza
                AND id_tenant = :id_tenant
            ");

            $checkStmt->bindParam(':id_elemento_fisico', $requestData['id_elemento_fisico']);
            $checkStmt->bindParam(':id_tipo_proceso_limpieza', $requestData['id_tipo_proceso_limpieza']);
            $checkStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $checkStmt->execute();
            $result = $checkStmt->fetch();

            if ($result['total'] > 0) {
                throw new Exception("Este tipo de proceso ya está asignado al elemento físico");
            }

            $id_proceso = Uuid::generar();
            $stmt = $db->prepare("
                INSERT INTO elementos_fisicos_x_procesos_limpieza (
                    id,
                    id_tenant,
                    id_elemento_fisico,
                    id_tipo_proceso_limpieza
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_elemento_fisico,
                    :id_tipo_proceso_limpieza
                )
            ");

            $stmt->bindValue(':id', $id_proceso);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->bindParam(':id_elemento_fisico', $requestData['id_elemento_fisico']);
            $stmt->bindParam(':id_tipo_proceso_limpieza', $requestData['id_tipo_proceso_limpieza']);
            $stmt->execute();

            // Si se enviaron productos, agregarlos
            if (isset($requestData['productos']) && is_array($requestData['productos'])) {
                $stmtProducto = $db->prepare("
                                            INSERT INTO elementos_fisicos_x_procesos_limpieza_productos (id, id_tenant, 
                                                id_elementos_fisicos_x_procesos_limpieza,  
                                                id_producto_limpieza,
                                                cantidad_sugerida,
                                                instrucciones
                                            ) VALUES (
                                                :id, :id_tenant, :id_elementos_fisicos_x_procesos_limpieza,
                                                :id_producto_limpieza,
                                                :cantidad_sugerida,
                                                :instrucciones
                                            )
                                        ");

                foreach ($requestData['productos'] as $producto) {
                    $idEfplp = Uuid::generar();
                    $stmtProducto->bindValue(':id', $idEfplp);
                    $stmtProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtProducto->bindParam(':id_elementos_fisicos_x_procesos_limpieza', $id_proceso);  // Cambiado
                    $stmtProducto->bindParam(':id_producto_limpieza', $producto['id_producto_limpieza']);
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
                DELETE FROM elementos_fisicos_x_procesos_limpieza_productos 
                WHERE id_elementos_fisicos_x_procesos_limpieza = :id_proceso
                AND id_tenant = :id_tenant
            ");
            $stmtDelete->bindParam(':id_proceso', $id_proceso);
            $stmtDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtDelete->execute();

            // Insertar nuevos productos
            if (isset($requestData['productos']) && is_array($requestData['productos'])) {
                $stmtInsert = $db->prepare("
                    INSERT INTO elementos_fisicos_x_procesos_limpieza_productos (id, id_tenant, 
                        id_elementos_fisicos_x_procesos_limpieza,
                        id_producto_limpieza,
                        cantidad_sugerida,
                        instrucciones
                    ) VALUES (
                        :id, :id_tenant, :id_elementos_fisicos_x_procesos_limpieza,
                        :id_producto_limpieza,
                        :cantidad_sugerida,
                        :instrucciones
                    )
                ");

                foreach ($requestData['productos'] as $producto) {
                    $idEfplp = Uuid::generar();
                    $stmtInsert->bindValue(':id', $idEfplp);
                    $stmtInsert->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsert->bindParam(':id_elementos_fisicos_x_procesos_limpieza', $id_proceso);
                    $stmtInsert->bindParam(':id_producto_limpieza', $producto['id_producto_limpieza']);
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
                DELETE FROM elementos_fisicos_x_procesos_limpieza 
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

    // Obtener unidades de medida disponibles para elementos físicos
    public static function getUnidadesMedida()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, nombre, abreviatura 
            FROM unidades_medida 
            WHERE activo = 1 
            AND (id_tipo_unidad IN (1, 5))
            ORDER BY nombre
        ");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}
