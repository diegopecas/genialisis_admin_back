<?php

/**
 * CRUD de la configuración de consumo general de productos por área física y
 * proceso de limpieza (tabla areas_fisicas_x_procesos_limpieza_consumo).
 *
 * Este consumo se usa como respaldo del cálculo por elementos: si un área no
 * tiene elementos físicos configurados para el proceso, el registro rápido
 * toma las cantidades definidas aquí. La lógica de esa decisión vive en el
 * servicio de la tabla principal (RegistrosLimpieza), no aquí.
 */
class AreasFisicasXProcesosLimpiezaConsumo
{
    /**
     * Devuelve el consumo general configurado para un área y un proceso, con el
     * nombre y la unidad de cada producto resueltos para mostrarlos en pantalla.
     */
    public static function getPorAreaProceso()
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

        $sentence = $db->prepare("
            SELECT
                c.id,
                c.id_area_fisica,
                c.id_tipo_proceso_limpieza,
                c.id_producto_limpieza,
                c.cantidad,
                c.id_unidad_medida,
                p.nombre as nombre_producto,
                p.stock_actual,
                um.abreviatura
            FROM areas_fisicas_x_procesos_limpieza_consumo c
            INNER JOIN productos_limpieza pl ON c.id_producto_limpieza = pl.id
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON c.id_unidad_medida = um.id
            WHERE c.id_area_fisica = :id_area
                AND c.id_tipo_proceso_limpieza = :id_proceso
                AND c.activo = 1
                AND c.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
        $sentence->bindParam(':id_area', $id_area);
        $sentence->bindParam(':id_proceso', $id_proceso);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json($sentence->fetchAll());
    }

    /**
     * Catálogo de productos de limpieza disponibles, con su unidad de medida por
     * defecto, para poblar el selector del tab de consumo general.
     */
    public static function getProductosDisponibles()
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT
                pl.id as id_producto_limpieza,
                p.id as id_producto,
                p.nombre,
                p.stock_actual,
                um.id as id_unidad_medida,
                um.abreviatura
            FROM productos_limpieza pl
            INNER JOIN productos p ON pl.id_producto = p.id
            INNER JOIN unidades_medida um ON p.id_unidad_medida = um.id
            WHERE p.activo = 1
                AND pl.id_tenant = :id_tenant
            ORDER BY p.nombre
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json($sentence->fetchAll());
    }

    /**
     * Crea una fila de consumo para el trío área + proceso + producto.
     * La unique key evita duplicar el mismo producto en la misma área/proceso.
     */
    public static function new()
    {
        $db = Flight::db();

        try {
            $id_area = Flight::request()->data['id_area_fisica'] ?? null;
            $id_proceso = Flight::request()->data['id_tipo_proceso_limpieza'] ?? null;
            $id_producto_limpieza = Flight::request()->data['id_producto_limpieza'] ?? null;
            $cantidad = Flight::request()->data['cantidad'] ?? null;
            $id_unidad_medida = Flight::request()->data['id_unidad_medida'] ?? null;

            if (!$id_area || !$id_proceso || !$id_producto_limpieza) {
                throw new Exception('Faltan datos requeridos (área, proceso o producto)');
            }
            if (!is_numeric($cantidad) || $cantidad <= 0) {
                throw new Exception('La cantidad debe ser mayor que cero');
            }
            if (!$id_unidad_medida) {
                throw new Exception('Falta la unidad de medida');
            }

            $sentence = $db->prepare("
                INSERT INTO areas_fisicas_x_procesos_limpieza_consumo (
                    id, id_tenant,
                    id_area_fisica,
                    id_tipo_proceso_limpieza,
                    id_producto_limpieza,
                    cantidad,
                    id_unidad_medida,
                    activo
                ) VALUES (
                    :id, :id_tenant,
                    :id_area_fisica,
                    :id_tipo_proceso_limpieza,
                    :id_producto_limpieza,
                    :cantidad,
                    :id_unidad_medida,
                    1
                )
            ");

            $id = Uuid::generar();
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_area_fisica', $id_area);
            $sentence->bindParam(':id_tipo_proceso_limpieza', $id_proceso);
            $sentence->bindParam(':id_producto_limpieza', $id_producto_limpieza);
            $sentence->bindParam(':cantidad', $cantidad);
            $sentence->bindParam(':id_unidad_medida', $id_unidad_medida);
            $sentence->execute();

            Flight::json(array('success' => true, 'id' => $id));
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                Flight::json(array('error' => 'Este producto ya está configurado para esta área y proceso'), 400);
                return;
            }
            error_log("Error en AreasFisicasXProcesosLimpiezaConsumo::new: " . $e->getMessage());
            Flight::json(array('error' => 'No se pudo guardar el consumo'), 500);
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Actualiza la cantidad y la unidad de una fila de consumo existente.
     */
    public static function update()
    {
        $db = Flight::db();

        try {
            $id = Flight::request()->data['id'] ?? null;
            $cantidad = Flight::request()->data['cantidad'] ?? null;
            $id_unidad_medida = Flight::request()->data['id_unidad_medida'] ?? null;

            if (!$id) {
                throw new Exception('Falta el identificador del consumo');
            }
            if (!is_numeric($cantidad) || $cantidad <= 0) {
                throw new Exception('La cantidad debe ser mayor que cero');
            }
            if (!$id_unidad_medida) {
                throw new Exception('Falta la unidad de medida');
            }

            $sentence = $db->prepare("
                UPDATE areas_fisicas_x_procesos_limpieza_consumo
                SET cantidad = :cantidad,
                    id_unidad_medida = :id_unidad_medida
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':cantidad', $cantidad);
            $sentence->bindParam(':id_unidad_medida', $id_unidad_medida);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el consumo a actualizar'), 404);
                return;
            }

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en AreasFisicasXProcesosLimpiezaConsumo::update: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }

    /**
     * Elimina una fila de consumo.
     */
    public static function delete()
    {
        $db = Flight::db();

        try {
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                throw new Exception('Falta el identificador del consumo');
            }

            $sentence = $db->prepare("
                DELETE FROM areas_fisicas_x_procesos_limpieza_consumo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            if ($sentence->rowCount() == 0) {
                Flight::json(array('error' => 'No se encontró el consumo a eliminar'), 404);
                return;
            }

            Flight::json(array('success' => true));
        } catch (Exception $e) {
            error_log("Error en AreasFisicasXProcesosLimpiezaConsumo::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 400);
        }
    }
}