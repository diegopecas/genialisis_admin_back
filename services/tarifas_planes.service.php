<?php
/**
 * Tarifas por plan. Una fila por producto: un plan puede tener implementación,
 * suscripción y todos los "otros" que cobre (por ejemplo el portal web).
 * El tipo de cobro NO se guarda aquí: sale de la clasificación del producto
 * (clasificacion_productos_servicios.codigo), ver sqlCodigoTipoCobro().
 */
class TarifasPlanes
{
    /**
     * Expresión SQL con el código del tipo de cobro de un producto a partir de
     * su clasificación: IMPLEMENTACION y SUSCRIPCION se respetan; cualquier otra
     * clasificación (o ninguna) es OTRO y se cobra según su periodicidad.
     * La usan también las líneas y los valores del contrato para clasificar igual.
     *
     * @param string $aliasClasificacion alias de clasificacion_productos_servicios en la consulta
     */
    public static function sqlCodigoTipoCobro($aliasClasificacion)
    {
        return "(CASE WHEN {$aliasClasificacion}.codigo IN ('IMPLEMENTACION', 'SUSCRIPCION') THEN {$aliasClasificacion}.codigo ELSE 'OTRO' END)";
    }

    /**
     * Nombre legible del tipo de cobro, con la misma regla de sqlCodigoTipoCobro().
     */
    public static function sqlNombreTipoCobro($aliasClasificacion)
    {
        return "(CASE WHEN {$aliasClasificacion}.codigo = 'IMPLEMENTACION' THEN 'Implementación' WHEN {$aliasClasificacion}.codigo = 'SUSCRIPCION' THEN 'Suscripción' ELSE 'Otro' END)";
    }

    /**
     * SELECT común de las filas de tarifa con sus datos de producto y tipo.
     * Se centraliza para que todos los getters devuelvan la misma forma.
     */
    private static function selectBase()
    {
        return "
            SELECT tg.id, tg.id_plan, tg.anio, tg.id_producto_servicio,
                   tg.valor, tg.obligatorio, tg.orden,
                   g.nombre AS nombre_plan, g.orden AS orden_plan,
                   ps.nombre AS nombre_producto,
                   ps.id_periodicidad_cobro,
                   ps.id_clasificacion_productos_servicios,
                   pc.nombre AS nombre_periodicidad,
                   " . self::sqlCodigoTipoCobro('cl') . " AS codigo_tipo_cobro,
                   " . self::sqlNombreTipoCobro('cl') . " AS nombre_tipo_cobro
            FROM tarifas_planes tg
            INNER JOIN planes g ON tg.id_plan = g.id
            INNER JOIN productos_servicios ps ON tg.id_producto_servicio = ps.id
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        ";
    }

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE tg.id_tenant = :id_tenant
            ORDER BY g.orden, tg.anio DESC, tg.orden
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE tg.id = :id AND tg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPlan($idPlan)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE tg.id_plan = :id_plan AND tg.id_tenant = :id_tenant
            ORDER BY tg.anio DESC, tg.orden
        ");
        $sentence->bindParam(':id_plan', $idPlan);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Filas de tarifa de un plan en un año.
     * OJO: antes devolvía un solo objeto (fetch); ahora devuelve el arreglo de
     * filas, una por producto.
     */
    public static function getByPlanAnio($idPlan, $anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE tg.id_plan = :id_plan AND tg.anio = :anio AND tg.id_tenant = :id_tenant
            ORDER BY tg.orden
        ");
        $sentence->bindParam(':id_plan', $idPlan);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE tg.anio = :anio AND tg.id_tenant = :id_tenant
            ORDER BY g.orden, tg.orden
        ");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_plan = Flight::request()->data['id_plan'];
            $anio = Flight::request()->data['anio'];
            $id_producto_servicio = Flight::request()->data['id_producto_servicio'];
            $valor = isset(Flight::request()->data['valor']) ? Flight::request()->data['valor'] : 0;
            $obligatorio = isset(Flight::request()->data['obligatorio']) ? (int)Flight::request()->data['obligatorio'] : 1;
            $orden = isset(Flight::request()->data['orden']) ? (int)Flight::request()->data['orden'] : 1;

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tarifas_planes
                                      (id, id_tenant, anio, id_plan, id_producto_servicio, valor, obligatorio, orden)
                                      VALUES (:id, :id_tenant, :anio, :id_plan, :id_producto_servicio, :valor, :obligatorio, :orden)");

            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':id_producto_servicio', $id_producto_servicio);
            $sentence->bindParam(':valor', $valor);
            $sentence->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);

            $sentence->execute();
            $id = $idNew;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TarifasPlanes::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();

            $id = Flight::request()->data['id'];
            $id_plan = Flight::request()->data['id_plan'];
            $anio = Flight::request()->data['anio'];
            $id_producto_servicio = Flight::request()->data['id_producto_servicio'];
            $valor = isset(Flight::request()->data['valor']) ? Flight::request()->data['valor'] : 0;
            $obligatorio = isset(Flight::request()->data['obligatorio']) ? (int)Flight::request()->data['obligatorio'] : 1;
            $orden = isset(Flight::request()->data['orden']) ? (int)Flight::request()->data['orden'] : 1;

            $sentence = $db->prepare("UPDATE tarifas_planes SET
                                      id_plan = :id_plan,
                                      anio = :anio,
                                      id_producto_servicio = :id_producto_servicio,
                                      valor = :valor,
                                      obligatorio = :obligatorio,
                                      orden = :orden
                                      WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_producto_servicio', $id_producto_servicio);
            $sentence->bindParam(':valor', $valor);
            $sentence->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
            $sentence->bindValue(':orden', $orden, PDO::PARAM_INT);

            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en TarifasPlanes::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Guarda de un golpe todas las filas de tarifa de un plan en un año.
     * Espera: id_plan, anio, tarifas[] con id (opcional, si ya existe),
     * id_producto_servicio, valor, obligatorio y orden;
     * y eliminar[] con los ids de las filas a borrar.
     * Valida que no se repita el mismo producto en el mismo plan y año.
     */
    public static function guardarLote()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'admin.planes');

        $db = Flight::db();
        try {
            $id_plan = Flight::request()->data['id_plan'];
            $anio = Flight::request()->data['anio'];
            $tarifas = Flight::request()->data['tarifas'];
            $eliminar = Flight::request()->data['eliminar'];

            $tarifas = is_array($tarifas) ? $tarifas : [];
            $eliminar = is_array($eliminar) ? $eliminar : [];

            if (empty($id_plan)) {
                Flight::json(array('error' => 'No se recibio el plan'), 400);
                return;
            }

            if (empty($anio)) {
                Flight::json(array('error' => 'No se recibio el año'), 400);
                return;
            }

            if (count($tarifas) === 0 && count($eliminar) === 0) {
                Flight::json(array('error' => 'No se recibieron cambios para guardar'), 400);
                return;
            }

            // Un mismo producto no puede venir dos veces en el envío
            $productosEnviados = [];
            foreach ($tarifas as $fila) {
                if (empty($fila['id_producto_servicio'])) {
                    Flight::json(array('error' => 'Hay una fila sin producto seleccionado'), 400);
                    return;
                }
                if (in_array($fila['id_producto_servicio'], $productosEnviados)) {
                    Flight::json(array('error' => 'El producto ' . $fila['id_producto_servicio'] . ' esta repetido en la tarifa'), 400);
                    return;
                }
                $productosEnviados[] = $fila['id_producto_servicio'];
            }

            $db->beginTransaction();

            // Primero se borra, para liberar los productos que se reemplazan
            $eliminados = 0;
            if (count($eliminar) > 0) {
                $borrar = $db->prepare("DELETE FROM tarifas_planes
                                        WHERE id = :id AND id_plan = :id_plan AND id_tenant = :id_tenant");
                foreach ($eliminar as $idTarifa) {
                    $borrar->bindParam(':id', $idTarifa);
                    $borrar->bindParam(':id_plan', $id_plan);
                    $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $borrar->execute();
                    $eliminados += $borrar->rowCount();
                }
            }

            $insertar = $db->prepare("INSERT INTO tarifas_planes
                (id, id_tenant, anio, id_plan, id_producto_servicio, valor, obligatorio, orden)
                VALUES (:id, :id_tenant, :anio, :id_plan, :id_producto_servicio, :valor, :obligatorio, :orden)");

            $actualizar = $db->prepare("UPDATE tarifas_planes SET
                id_producto_servicio = :id_producto_servicio,
                valor = :valor,
                obligatorio = :obligatorio,
                orden = :orden
                WHERE id = :id AND id_plan = :id_plan AND anio = :anio AND id_tenant = :id_tenant");

            $ids = [];
            $creados = 0;
            $actualizados = 0;

            foreach ($tarifas as $fila) {
                $id_producto_servicio = $fila['id_producto_servicio'];
                $valor = isset($fila['valor']) ? $fila['valor'] : 0;
                $obligatorio = isset($fila['obligatorio']) ? (int)$fila['obligatorio'] : 1;
                $orden = isset($fila['orden']) ? (int)$fila['orden'] : 1;

                if (!empty($fila['id'])) {
                    $idFila = $fila['id'];
                    $actualizar->bindParam(':id', $idFila);
                    $actualizar->bindParam(':id_plan', $id_plan);
                    $actualizar->bindParam(':anio', $anio);
                    $actualizar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $actualizar->bindParam(':id_producto_servicio', $id_producto_servicio);
                    $actualizar->bindParam(':valor', $valor);
                    $actualizar->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
                    $actualizar->bindValue(':orden', $orden, PDO::PARAM_INT);
                    $actualizar->execute();
                    $actualizados++;
                } else {
                    $idFila = Uuid::generar();
                    $insertar->bindValue(':id', $idFila);
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindParam(':anio', $anio);
                    $insertar->bindParam(':id_plan', $id_plan);
                    $insertar->bindParam(':id_producto_servicio', $id_producto_servicio);
                    $insertar->bindParam(':valor', $valor);
                    $insertar->bindValue(':obligatorio', $obligatorio, PDO::PARAM_INT);
                    $insertar->bindValue(':orden', $orden, PDO::PARAM_INT);
                    $insertar->execute();
                    $creados++;
                }

                $ids[] = $idFila;
            }

            $db->commit();
            Flight::json(array(
                'ids' => $ids,
                'total' => count($ids),
                'creados' => $creados,
                'actualizados' => $actualizados,
                'eliminados' => $eliminados
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Error en TarifasPlanes::guardarLote: ' . $e->getMessage());
            Flight::json(array('error' => 'Hubo un problema al guardar las tarifas del plan'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM tarifas_planes WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}
