<?php
class ContratosCliente
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_cliente, cm.anio, cm.id_plan, cm.valor_implementacion, 
                   cm.valor_suscripcion, cm.numero_cuotas, cm.cuotas_implementacion, cm.valor_total,
                   cm.descuento_implementacion, cm.recargo_implementacion,
                   cm.descuento_suscripcion, cm.recargo_suscripcion,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_plan,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_cliente,
                   p.numero_identificacion AS documento_cliente
            FROM contratos_cliente cm
            INNER JOIN clientes e ON cm.id_cliente = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN planes g ON cm.id_plan = g.id
            WHERE cm.id_tenant = :id_tenant
            ORDER BY cm.fecha_generacion DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_cliente, cm.anio, cm.id_plan, cm.valor_implementacion, 
                   cm.valor_suscripcion, cm.numero_cuotas, cm.cuotas_implementacion, cm.valor_total,
                   cm.descuento_implementacion, cm.recargo_implementacion,
                   cm.descuento_suscripcion, cm.recargo_suscripcion,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_plan,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_cliente,
                   p.numero_identificacion AS documento_cliente
            FROM contratos_cliente cm
            INNER JOIN clientes e ON cm.id_cliente = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN planes g ON cm.id_plan = g.id
            WHERE cm.id = :id AND cm.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByCliente($idCliente)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_cliente, cm.anio, cm.id_plan, cm.valor_implementacion, 
                   cm.valor_suscripcion, cm.numero_cuotas, cm.cuotas_implementacion, cm.valor_total,
                   cm.descuento_implementacion, cm.recargo_implementacion,
                   cm.descuento_suscripcion, cm.recargo_suscripcion,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_plan,
                   CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS nombre_usuario_genera
            FROM contratos_cliente cm
            INNER JOIN planes g ON cm.id_plan = g.id
            LEFT JOIN usuarios u ON cm.id_usuario_genera = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            WHERE cm.id_cliente = :id_cliente AND cm.id_tenant = :id_tenant
            ORDER BY cm.anio DESC, cm.fecha_generacion DESC
        ");
        $sentence->bindParam(':id_cliente', $idCliente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_cliente, cm.anio, cm.id_plan, cm.valor_implementacion, 
                   cm.valor_suscripcion, cm.numero_cuotas, cm.valor_total,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_plan,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_cliente
            FROM contratos_cliente cm
            INNER JOIN clientes e ON cm.id_cliente = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN planes g ON cm.id_plan = g.id
            WHERE cm.anio = :anio AND cm.id_tenant = :id_tenant
            ORDER BY g.orden, p.primer_nombre
        ");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();
            
            $id_cliente = Flight::request()->data['id_cliente'];
            $anio = Flight::request()->data['anio'];
            $id_plan = Flight::request()->data['id_plan'];
            $valor_implementacion = Flight::request()->data['valor_implementacion'];
            $descuento_implementacion = isset(Flight::request()->data['descuento_implementacion']) ? Flight::request()->data['descuento_implementacion'] : 0;
            $recargo_implementacion = isset(Flight::request()->data['recargo_implementacion']) ? Flight::request()->data['recargo_implementacion'] : 0;
            $valor_suscripcion = Flight::request()->data['valor_suscripcion'];
            $descuento_suscripcion = isset(Flight::request()->data['descuento_suscripcion']) ? Flight::request()->data['descuento_suscripcion'] : 0;
            $recargo_suscripcion = isset(Flight::request()->data['recargo_suscripcion']) ? Flight::request()->data['recargo_suscripcion'] : 0;
            $razon_descuento = isset(Flight::request()->data['razon_descuento']) ? Flight::request()->data['razon_descuento'] : null;
            $razon_recargo = isset(Flight::request()->data['razon_recargo']) ? Flight::request()->data['razon_recargo'] : null;
            
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $cuotas_implementacion = isset(Flight::request()->data['cuotas_implementacion']) ? Flight::request()->data['cuotas_implementacion'] : 1;
            $valor_total = Flight::request()->data['valor_total'];
            $fecha_firma = Flight::request()->data['fecha_firma'];
            $fecha_inicio = isset(Flight::request()->data['fecha_inicio']) ? Flight::request()->data['fecha_inicio'] : null;
            $fecha_fin = isset(Flight::request()->data['fecha_fin']) ? Flight::request()->data['fecha_fin'] : null;
            $lugar_firma = isset(Flight::request()->data['lugar_firma']) ? Flight::request()->data['lugar_firma'] : 'Chía';
            $autoriza_imagenes = isset(Flight::request()->data['autoriza_imagenes']) ? Flight::request()->data['autoriza_imagenes'] : 0;
            $autoriza_pagare = isset(Flight::request()->data['autoriza_pagare']) ? Flight::request()->data['autoriza_pagare'] : 1;
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;
            $id_usuario_genera = isset(Flight::request()->data['id_usuario_genera']) ? Flight::request()->data['id_usuario_genera'] : null;
            $representantes = isset(Flight::request()->data['representantes']) ? Flight::request()->data['representantes'] : [];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO contratos_cliente 
                (id, id_tenant, id_cliente, anio, id_plan, valor_implementacion, descuento_implementacion, recargo_implementacion, 
                 valor_suscripcion, descuento_suscripcion, recargo_suscripcion, razon_descuento, razon_recargo,
                 numero_cuotas, cuotas_implementacion, valor_total, fecha_firma, fecha_inicio, fecha_fin, lugar_firma, 
                 autoriza_imagenes, autoriza_pagare, observaciones, id_usuario_genera) 
                VALUES 
                (:id, :id_tenant, :id_cliente, :anio, :id_plan, :valor_implementacion, :descuento_implementacion, :recargo_implementacion,
                 :valor_suscripcion, :descuento_suscripcion, :recargo_suscripcion, :razon_descuento, :razon_recargo,
                 :numero_cuotas, :cuotas_implementacion, :valor_total, :fecha_firma, :fecha_inicio, :fecha_fin, :lugar_firma, 
                 :autoriza_imagenes, :autoriza_pagare, :observaciones, :id_usuario_genera)");
            
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_cliente', $id_cliente);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':valor_implementacion', $valor_implementacion);
            $sentence->bindParam(':descuento_implementacion', $descuento_implementacion);
            $sentence->bindParam(':recargo_implementacion', $recargo_implementacion);
            $sentence->bindParam(':valor_suscripcion', $valor_suscripcion);
            $sentence->bindParam(':descuento_suscripcion', $descuento_suscripcion);
            $sentence->bindParam(':recargo_suscripcion', $recargo_suscripcion);
            $sentence->bindParam(':razon_descuento', $razon_descuento);
            $sentence->bindParam(':razon_recargo', $razon_recargo);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':cuotas_implementacion', $cuotas_implementacion);
            $sentence->bindParam(':valor_total', $valor_total);
            $sentence->bindParam(':fecha_firma', $fecha_firma);
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->bindParam(':lugar_firma', $lugar_firma);
            $sentence->bindParam(':autoriza_imagenes', $autoriza_imagenes);
            $sentence->bindParam(':autoriza_pagare', $autoriza_pagare);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':id_usuario_genera', $id_usuario_genera);
            
            $sentence->execute();
            $id_contrato = $idNew;

            if (!empty($representantes)) {
                $sentenceRepresentante = $db->prepare("INSERT INTO contratos_cliente_representantes 
                    (id_tenant, id_contrato, id_representante, orden) VALUES (:id_tenant, :id_contrato, :id_representante, :orden)");
                $sentenceRepresentante->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                
                $orden = 1;
                foreach ($representantes as $id_representante) {
                    $sentenceRepresentante->bindParam(':id_contrato', $id_contrato);
                    $sentenceRepresentante->bindParam(':id_representante', $id_representante);
                    $sentenceRepresentante->bindParam(':orden', $orden);
                    $sentenceRepresentante->execute();
                    $orden++;
                }
            }

            $db->commit();
            Flight::json(array('id' => $id_contrato));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosCliente::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $anio = Flight::request()->data['anio'];
            $id_plan = Flight::request()->data['id_plan'];
            $valor_implementacion = Flight::request()->data['valor_implementacion'];
            $descuento_implementacion = isset(Flight::request()->data['descuento_implementacion']) ? Flight::request()->data['descuento_implementacion'] : 0;
            $recargo_implementacion = isset(Flight::request()->data['recargo_implementacion']) ? Flight::request()->data['recargo_implementacion'] : 0;
            $valor_suscripcion = Flight::request()->data['valor_suscripcion'];
            $descuento_suscripcion = isset(Flight::request()->data['descuento_suscripcion']) ? Flight::request()->data['descuento_suscripcion'] : 0;
            $recargo_suscripcion = isset(Flight::request()->data['recargo_suscripcion']) ? Flight::request()->data['recargo_suscripcion'] : 0;
            $razon_descuento = isset(Flight::request()->data['razon_descuento']) ? Flight::request()->data['razon_descuento'] : null;
            $razon_recargo = isset(Flight::request()->data['razon_recargo']) ? Flight::request()->data['razon_recargo'] : null;
            
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $cuotas_implementacion = isset(Flight::request()->data['cuotas_implementacion']) ? Flight::request()->data['cuotas_implementacion'] : 1;
            $valor_total = Flight::request()->data['valor_total'];
            $fecha_firma = Flight::request()->data['fecha_firma'];
            $fecha_inicio = isset(Flight::request()->data['fecha_inicio']) ? Flight::request()->data['fecha_inicio'] : null;
            $fecha_fin = Flight::request()->data['fecha_fin'];
            $lugar_firma = Flight::request()->data['lugar_firma'];
            $autoriza_imagenes = isset(Flight::request()->data['autoriza_imagenes']) ? Flight::request()->data['autoriza_imagenes'] : 0;
            $autoriza_pagare = isset(Flight::request()->data['autoriza_pagare']) ? Flight::request()->data['autoriza_pagare'] : 1;
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;
            $firmado = isset(Flight::request()->data['firmado']) ? Flight::request()->data['firmado'] : 0;
            $ruta_documento_firmado = isset(Flight::request()->data['ruta_documento_firmado']) ? Flight::request()->data['ruta_documento_firmado'] : null;
            $representantes = isset(Flight::request()->data['representantes']) ? Flight::request()->data['representantes'] : [];

            $sentence = $db->prepare("UPDATE contratos_cliente SET 
                anio = :anio,
                id_plan = :id_plan,
                valor_implementacion = :valor_implementacion,
                descuento_implementacion = :descuento_implementacion,
                recargo_implementacion = :recargo_implementacion,
                valor_suscripcion = :valor_suscripcion,
                descuento_suscripcion = :descuento_suscripcion,
                recargo_suscripcion = :recargo_suscripcion,
                razon_descuento = :razon_descuento,
                razon_recargo = :razon_recargo,
                numero_cuotas = :numero_cuotas,
                cuotas_implementacion = :cuotas_implementacion,
                valor_total = :valor_total,
                fecha_firma = :fecha_firma,
                fecha_inicio = :fecha_inicio,
                fecha_fin = :fecha_fin,
                lugar_firma = :lugar_firma,
                autoriza_imagenes = :autoriza_imagenes,
                autoriza_pagare = :autoriza_pagare,
                observaciones = :observaciones,
                firmado = :firmado,
                ruta_documento_firmado = :ruta_documento_firmado
                WHERE id = :id AND id_tenant = :id_tenant");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_plan', $id_plan);
            $sentence->bindParam(':valor_implementacion', $valor_implementacion);
            $sentence->bindParam(':descuento_implementacion', $descuento_implementacion);
            $sentence->bindParam(':recargo_implementacion', $recargo_implementacion);
            $sentence->bindParam(':valor_suscripcion', $valor_suscripcion);
            $sentence->bindParam(':descuento_suscripcion', $descuento_suscripcion);
            $sentence->bindParam(':recargo_suscripcion', $recargo_suscripcion);
            $sentence->bindParam(':razon_descuento', $razon_descuento);
            $sentence->bindParam(':razon_recargo', $razon_recargo);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':cuotas_implementacion', $cuotas_implementacion);
            $sentence->bindParam(':valor_total', $valor_total);
            $sentence->bindParam(':fecha_firma', $fecha_firma);
            $sentence->bindParam(':fecha_inicio', $fecha_inicio);
            $sentence->bindParam(':fecha_fin', $fecha_fin);
            $sentence->bindParam(':lugar_firma', $lugar_firma);
            $sentence->bindParam(':autoriza_imagenes', $autoriza_imagenes);
            $sentence->bindParam(':autoriza_pagare', $autoriza_pagare);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':firmado', $firmado);
            $sentence->bindParam(':ruta_documento_firmado', $ruta_documento_firmado);
            
            $sentence->execute();

            if (!empty($representantes)) {
                $sentenceDelete = $db->prepare("DELETE FROM contratos_cliente_representantes WHERE id_contrato = :id AND id_tenant = :id_tenant");
                $sentenceDelete->bindParam(':id', $id);
                $sentenceDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceDelete->execute();

                $sentenceRepresentante = $db->prepare("INSERT INTO contratos_cliente_representantes 
                    (id_tenant, id_contrato, id_representante, orden) VALUES (:id_tenant, :id_contrato, :id_representante, :orden)");
                $sentenceRepresentante->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                
                $orden = 1;
                foreach ($representantes as $id_representante) {
                    $sentenceRepresentante->bindParam(':id_contrato', $id);
                    $sentenceRepresentante->bindParam(':id_representante', $id_representante);
                    $sentenceRepresentante->bindParam(':orden', $orden);
                    $sentenceRepresentante->execute();
                    $orden++;
                }
            }

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosCliente::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function marcarFirmado()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $firmado = isset(Flight::request()->data['firmado']) ? Flight::request()->data['firmado'] : 1;
            $ruta_documento_firmado = isset(Flight::request()->data['ruta_documento_firmado']) ? Flight::request()->data['ruta_documento_firmado'] : null;

            $sentence = $db->prepare("UPDATE contratos_cliente SET 
                firmado = :firmado,
                ruta_documento_firmado = :ruta_documento_firmado
                WHERE id = :id AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':firmado', $firmado);
            $sentence->bindParam(':ruta_documento_firmado', $ruta_documento_firmado);
            $sentence->execute();

            Flight::json(array('id' => $id, 'firmado' => $firmado));
        } catch (Exception $e) {
            error_log("Error en ContratosCliente::marcarFirmado: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE contratos_cliente SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'activo' => 0));
        } catch (Exception $e) {
            error_log("Error en ContratosCliente::anular: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM contratos_cliente_representantes WHERE id_contrato = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        $sentence = $db->prepare("DELETE FROM contratos_cliente WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getRepresentantesByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cma.id, cma.id_contrato, cma.id_representante, cma.orden,
                   a.id_tipo_representante, ta.nombre AS tipo_representante,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_representante,
                   p.numero_identificacion AS documento_representante,
                   ti.nombre AS tipo_identificacion
            FROM contratos_cliente_representantes cma
            INNER JOIN representantes a ON cma.id_representante = a.id
            INNER JOIN personas p ON a.id_persona = p.id
            INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            WHERE cma.id_contrato = :id_contrato AND cma.id_tenant = :id_tenant
            ORDER BY cma.orden
        ");
        $sentence->bindParam(':id_contrato', $idContrato);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDatosContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos');

        $db = Flight::db();
        
        $sentenceContrato = $db->prepare("
            SELECT cm.*, g.nombre AS nombre_plan
            FROM contratos_cliente cm
            INNER JOIN planes g ON cm.id_plan = g.id
            WHERE cm.id = :id AND cm.id_tenant = :id_tenant
        ");
        $sentenceContrato->bindParam(':id', $idContrato);
        $sentenceContrato->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceContrato->execute();
        $contrato = $sentenceContrato->fetch();

        if (!$contrato) {
            Flight::json(array('error' => 'Contrato no encontrado'), 404);
            return;
        }

        $sentenceCliente = $db->prepare("
            SELECT e.id, e.id_persona,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                   p.numero_identificacion, ti.nombre AS tipo_identificacion,
                   p.direccion, c.nombre AS ciudad
            FROM clientes e
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            LEFT JOIN ciudades c ON p.id_ciudad = c.id
            WHERE e.id = :id_cliente AND e.id_tenant = :id_tenant
        ");
        $sentenceCliente->bindParam(':id_cliente', $contrato['id_cliente']);
        $sentenceCliente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceCliente->execute();
        $cliente = $sentenceCliente->fetch();

        $sentenceRepresentantes = $db->prepare("
            SELECT cma.orden,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                   p.numero_identificacion, ti.nombre AS tipo_identificacion,
                   p.direccion, c.nombre AS ciudad,
                   ta.nombre AS tipo_representante,
                   a.es_responsable_pago
            FROM contratos_cliente_representantes cma
            INNER JOIN representantes a ON cma.id_representante = a.id
            INNER JOIN personas p ON a.id_persona = p.id
            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            INNER JOIN tipos_representante ta ON a.id_tipo_representante = ta.id
            LEFT JOIN ciudades c ON p.id_ciudad = c.id
            WHERE cma.id_contrato = :id_contrato AND cma.id_tenant = :id_tenant
            ORDER BY cma.orden
        ");
        $sentenceRepresentantes->bindParam(':id_contrato', $idContrato);
        $sentenceRepresentantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceRepresentantes->execute();
        $representantes = $sentenceRepresentantes->fetchAll();

        $sentenceConfig = $db->prepare("
            SELECT clave, valor_texto, valor_numero, valor_fecha
            FROM configuracion_global
            WHERE clave IN ('representante_legal_nombre', 'representante_legal_cedula', 
                           'representante_legal_cedula_lugar', 'institucion_nombre', 'institucion_nit',
                           'institucion_telefono', 'institucion_email', 'institucion_web', 'institucion_direccion',
                           'institucion_eslogan', 'institucion_razon_social',
                           'representante_legal_cargo', 'director_general_nombre',
                           'director_general_cedula', 'director_general_cedula_lugar',
                           'director_general_cargo', 'director_general_email')
            AND id_tenant = :id_tenant
        ");
        $sentenceConfig->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceConfig->execute();
        $configRows = $sentenceConfig->fetchAll();
        
        $configuracion = [];
        foreach ($configRows as $row) {
            $configuracion[$row['clave']] = $row['valor_texto'];
        }

        // Campos parametrizables diligenciados para este contrato. Viajan como
        // { llave => valor } para que el generador los resuelva como marcadores.
        $sentenceCampos = $db->prepare("
            SELECT llave, valor FROM contratos_campos
            WHERE id_contrato = :id_contrato AND id_tenant = :id_tenant
        ");
        $sentenceCampos->bindParam(':id_contrato', $idContrato);
        $sentenceCampos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceCampos->execute();

        $campos = [];
        foreach ($sentenceCampos->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $campos[$fila['llave']] = $fila['valor'];
        }

        Flight::json(array(
            'contrato' => $contrato,
            'cliente' => $cliente,
            'representantes' => $representantes,
            'configuracion' => $configuracion,
            'campos' => $campos
        ));
    }

    public static function verificarExistente()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        $db = Flight::db();
        $id_cliente = Flight::request()->data['id_cliente'];
        $anio = Flight::request()->data['anio'];

        $sentence = $db->prepare("
            SELECT id, fecha_firma, activo 
            FROM contratos_cliente 
            WHERE id_cliente = :id_cliente AND anio = :anio AND activo = 1 AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_cliente', $id_cliente);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':anio', $anio);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array(
            'existe' => $response ? true : false,
            'id_contrato' => $response ? $response['id'] : null,
            'contrato' => $response
        ));
    }

    public static function desmarcarFirmado()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'clientes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE contratos_cliente SET 
                firmado = 0,
                ruta_documento_firmado = NULL
                WHERE id = :id AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'firmado' => 0, 'mensaje' => 'Contrato desmarcado exitosamente'));
        } catch (Exception $e) {
            error_log("Error en ContratosCliente::desmarcarFirmado: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}