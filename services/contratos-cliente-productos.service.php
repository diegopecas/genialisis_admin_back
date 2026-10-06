<?php
/**
 * Líneas de producto de un contrato de cliente.
 * Guarda lo que efectivamente se escogió en el contrato (implementación,
 * suscripción y los otros productos, como el portal web), cada uno con su
 * descuento y recargo propios. De aquí salen los totales derivados de la
 * cabecera (valor_implementacion, valor_suscripcion, valor_otros).
 */
class ContratosClienteProductos
{
    /**
     * Condición para amarrar una cuota (contratos_cliente_valores) con su línea
     * del contrato. Un producto puede estar en varias líneas, así que la cuota
     * se amarra por el orden de la línea; las cuotas viejas que no tienen
     * orden_linea se amarran a la PRIMERA línea de su producto, para que una
     * cuota nunca se cruce con dos líneas y se cuente doble.
     */
    public static function sqlJoinLinea($aliasValor, $aliasLinea)
    {
        return "$aliasLinea.id_contrato = $aliasValor.id_contrato
                AND $aliasLinea.id_tenant = $aliasValor.id_tenant
                AND $aliasLinea.orden = COALESCE($aliasValor.orden_linea, (
                        SELECT MIN(xl.orden) FROM contratos_cliente_productos xl
                        WHERE xl.id_contrato = $aliasValor.id_contrato
                          AND xl.id_tenant = $aliasValor.id_tenant
                          AND xl.id_producto_servicio = $aliasValor.id_producto_servicio))";
    }

    /**
     * SELECT común de las líneas con los datos del producto y del tipo.
     */
    private static function selectBase()
    {
        return "
            SELECT cmp.id, cmp.id_contrato, cmp.id_producto_servicio, cmp.descripcion,
                   cmp.valor_base, cmp.cantidad, cmp.descuento, cmp.recargo,
                   cmp.valor_final, cmp.orden,
                   ps.nombre AS nombre_producto,
                   ps.id_periodicidad_cobro,
                   pc.nombre AS nombre_periodicidad,
                   COALESCE(cmp.codigo_tipo_cobro, " . TarifasPlanes::sqlCodigoTipoCobro('cl') . ") AS codigo_tipo_cobro,
                   CASE COALESCE(cmp.codigo_tipo_cobro, " . TarifasPlanes::sqlCodigoTipoCobro('cl') . ")
                        WHEN 'IMPLEMENTACION' THEN 'Implementación'
                        WHEN 'SUSCRIPCION' THEN 'Suscripción'
                        ELSE 'Otro' END AS nombre_tipo_cobro
            FROM contratos_cliente_productos cmp
            INNER JOIN productos_servicios ps ON cmp.id_producto_servicio = ps.id
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN periodicidad_cobro pc ON pc.id = ps.id_periodicidad_cobro
        ";
    }

    public static function getByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE cmp.id_contrato = :id_contrato AND cmp.id_tenant = :id_tenant
            ORDER BY cmp.orden
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare(self::selectBase() . "
            WHERE cmp.id = :id AND cmp.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll(PDO::FETCH_ASSOC);
        Flight::json($response);
    }

    /**
     * Guarda de un golpe las líneas de un contrato (reemplaza las existentes)
     * y recalcula los totales derivados de la cabecera.
     * Espera: id_contrato y lineas[] con id_producto_servicio, descripcion,
     * valor_base, cantidad, descuento, recargo, valor_final y orden. Un mismo
     * producto puede venir en varias líneas; lo que no se puede repetir es el
     * orden, porque con él se amarran las cuotas del calendario. El valor
     * final ya viene calculado como valor_base x cantidad - descuento + recargo.
     * El código del tipo
     * de cobro se toma de la clasificación del producto y queda como foto del
     * momento de la firma (codigo_tipo_cobro).
     */
    public static function guardarLineas()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        $db = Flight::db();
        try {
            $id_contrato = Flight::request()->data['id_contrato'];
            $lineas = Flight::request()->data['lineas'];
            $lineas = is_array($lineas) ? $lineas : [];

            if (empty($id_contrato)) {
                Flight::json(array('error' => 'No se recibio el contrato'), 400);
                return;
            }

            $ordenesEnviados = [];
            $posicion = 1;
            foreach ($lineas as $linea) {
                if (empty($linea['id_producto_servicio'])) {
                    Flight::json(array('error' => 'Hay una linea sin producto'), 400);
                    return;
                }
                if (isset($linea['cantidad']) && (int)$linea['cantidad'] < 1) {
                    Flight::json(array('error' => 'La cantidad de cada producto debe ser mayor o igual a 1'), 400);
                    return;
                }
                $ordenLinea = isset($linea['orden']) ? (int)$linea['orden'] : $posicion;
                if (in_array($ordenLinea, $ordenesEnviados)) {
                    Flight::json(array('error' => 'Hay dos lineas del contrato con el mismo orden (' . $ordenLinea . ')'), 400);
                    return;
                }
                $ordenesEnviados[] = $ordenLinea;
                $posicion++;
            }

            $db->beginTransaction();

            $borrar = $db->prepare("DELETE FROM contratos_cliente_productos
                                    WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_contrato', $id_contrato);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            // El tipo se toma de la clasificación del producto en el momento de guardar
            $tipoDelProducto = $db->prepare("SELECT " . TarifasPlanes::sqlCodigoTipoCobro('cl') . " AS codigo_tipo_cobro
                                             FROM productos_servicios ps
                                             LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
                                             WHERE ps.id = :id_producto AND ps.id_tenant = :id_tenant");

            $insertar = $db->prepare("INSERT INTO contratos_cliente_productos
                (id, id_tenant, id_contrato, id_producto_servicio, descripcion, codigo_tipo_cobro,
                 valor_base, cantidad, descuento, recargo, valor_final, orden)
                VALUES (:id, :id_tenant, :id_contrato, :id_producto_servicio, :descripcion, :codigo_tipo_cobro,
                 :valor_base, :cantidad, :descuento, :recargo, :valor_final, :orden)");

            $ids = [];
            $orden = 1;
            foreach ($lineas as $linea) {
                $id_producto_servicio = $linea['id_producto_servicio'];

                $tipoDelProducto->bindParam(':id_producto', $id_producto_servicio);
                $tipoDelProducto->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $tipoDelProducto->execute();
                $filaTipo = $tipoDelProducto->fetch(PDO::FETCH_ASSOC);
                $codigo_tipo_cobro = $filaTipo ? $filaTipo['codigo_tipo_cobro'] : 'OTRO';

                $valor_base = isset($linea['valor_base']) ? $linea['valor_base'] : 0;
                // Las lineas guardadas antes de existir la cantidad no la traen
                $cantidad = isset($linea['cantidad']) ? max(1, (int)$linea['cantidad']) : 1;
                $descripcion = isset($linea['descripcion']) ? trim((string)$linea['descripcion']) : '';
                $descripcion = $descripcion !== '' ? mb_substr($descripcion, 0, 200) : null;
                $descuento = isset($linea['descuento']) ? $linea['descuento'] : 0;
                $recargo = isset($linea['recargo']) ? $linea['recargo'] : 0;
                $valor_final = isset($linea['valor_final']) ? $linea['valor_final'] : 0;
                $ordenLinea = isset($linea['orden']) ? (int)$linea['orden'] : $orden;

                $idNew = Uuid::generar();
                $insertar->bindValue(':id', $idNew);
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindParam(':id_contrato', $id_contrato);
                $insertar->bindParam(':id_producto_servicio', $id_producto_servicio);
                $insertar->bindValue(':descripcion', $descripcion);
                $insertar->bindValue(':codigo_tipo_cobro', $codigo_tipo_cobro);
                $insertar->bindParam(':valor_base', $valor_base);
                $insertar->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
                $insertar->bindParam(':descuento', $descuento);
                $insertar->bindParam(':recargo', $recargo);
                $insertar->bindParam(':valor_final', $valor_final);
                $insertar->bindValue(':orden', $ordenLinea, PDO::PARAM_INT);
                $insertar->execute();

                $ids[] = $idNew;
                $orden++;
            }

            $totales = self::recalcularTotalesContrato($db, $id_contrato);

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contrato' => $id_contrato,
                'ids' => $ids,
                'total' => count($ids),
                'totales' => $totales
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en ContratosClienteProductos::guardarLineas: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function eliminarByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();

            $sentence = $db->prepare("DELETE FROM contratos_cliente_productos
                                      WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant");
            $sentence->bindParam(':id_contrato', $idContrato);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('success' => true, 'id_contrato' => $idContrato));
        } catch (Exception $e) {
            error_log("Error en ContratosClienteProductos::eliminarByContrato: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Recalcula los totales derivados de la cabecera del contrato a partir del
     * calendario de contratos_cliente_valores, clasificando cada cuota por el
     * tipo de cobro de la línea a la que pertenece la cuota. El número de
     * cuotas cuenta los meses con suscripción, no las cuotas: dos líneas de
     * suscripción en el mismo mes son una sola cuota del contrato.
     * Si la línea no tiene tipo se usa el del producto, y si el producto
     * tampoco lo tiene se cae a la periodicidad (1 Anual = implementación,
     * 2 Mensual = suscripción), que es como se hacía antes.
     * Devuelve el arreglo de totales para que quien lo llame pueda responderlos.
     */
    public static function recalcularTotalesContrato($db, $idContrato)
    {
        // Codigo del tipo de cobro de la cuota: primero la foto de la linea,
        // despues la clasificacion del producto, y de ultimas la periodicidad
        $codigoTipo = "COALESCE(cmp.codigo_tipo_cobro, CASE WHEN cl.codigo IN ('IMPLEMENTACION', 'SUSCRIPCION') THEN cl.codigo END, CASE WHEN ps.id_periodicidad_cobro = 1 THEN 'IMPLEMENTACION' WHEN ps.id_periodicidad_cobro = 2 THEN 'SUSCRIPCION' ELSE 'OTRO' END)";

        $sentence = $db->prepare("
            SELECT
                SUM(CASE WHEN $codigoTipo = 'IMPLEMENTACION' THEN cmv.valor ELSE 0 END) AS total_implementacion,
                SUM(CASE WHEN $codigoTipo = 'SUSCRIPCION' THEN cmv.valor ELSE 0 END) AS total_suscripcion,
                SUM(CASE WHEN $codigoTipo = 'OTRO' THEN cmv.valor ELSE 0 END) AS total_otros,
                COUNT(DISTINCT CASE WHEN $codigoTipo = 'SUSCRIPCION' THEN cmv.fecha END) AS numero_cuotas
            FROM contratos_cliente_valores cmv
            INNER JOIN productos_servicios ps ON cmv.id_producto_servicio = ps.id
            LEFT JOIN clasificacion_productos_servicios cl ON cl.id = ps.id_clasificacion_productos_servicios
            LEFT JOIN contratos_cliente_productos cmp
                   ON " . self::sqlJoinLinea('cmv', 'cmp') . "
            WHERE cmv.id_contrato = :id_contrato AND cmv.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch(PDO::FETCH_ASSOC);

        $totalImplementacion = $fila ? (float)($fila['total_implementacion'] ?? 0) : 0;
        $totalSuscripcion = $fila ? (float)($fila['total_suscripcion'] ?? 0) : 0;
        $totalOtros = $fila ? (float)($fila['total_otros'] ?? 0) : 0;
        $numeroCuotas = $fila ? (int)($fila['numero_cuotas'] ?? 0) : 0;
        $valorTotal = $totalImplementacion + $totalSuscripcion + $totalOtros;

        // Solo se tocan los totales, NO las fechas (esas las maneja el usuario)
        $sentenceUpdate = $db->prepare("
            UPDATE contratos_cliente SET
                valor_implementacion = :valor_implementacion,
                valor_suscripcion = :valor_suscripcion,
                valor_otros = :valor_otros,
                numero_cuotas = :numero_cuotas,
                valor_total = :valor_total
            WHERE id = :id AND id_tenant = :id_tenant
        ");
        $sentenceUpdate->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceUpdate->bindValue(':valor_implementacion', $totalImplementacion);
        $sentenceUpdate->bindValue(':valor_suscripcion', $totalSuscripcion);
        $sentenceUpdate->bindValue(':valor_otros', $totalOtros);
        $sentenceUpdate->bindValue(':numero_cuotas', $numeroCuotas, PDO::PARAM_INT);
        $sentenceUpdate->bindValue(':valor_total', $valorTotal);
        $sentenceUpdate->bindParam(':id', $idContrato);
        $sentenceUpdate->execute();

        return array(
            'total_implementacion' => $totalImplementacion,
            'total_suscripcion' => $totalSuscripcion,
            'total_otros' => $totalOtros,
            'numero_cuotas' => $numeroCuotas,
            'valor_total' => $valorTotal
        );
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];

        $sentence = $db->prepare("DELETE FROM contratos_cliente_productos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}