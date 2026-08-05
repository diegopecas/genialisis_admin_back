<?php
class ContratosMatricula
{
    public static function getAll()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_estudiante, cm.anio, cm.id_grupo, cm.valor_matricula, 
                   cm.valor_pension, cm.numero_cuotas, cm.cuotas_matricula, cm.valor_total,
                   cm.descuento_matricula, cm.recargo_matricula,
                   cm.descuento_pension, cm.recargo_pension,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_grupo,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                   p.numero_identificacion AS documento_estudiante
            FROM contratos_matricula cm
            INNER JOIN estudiantes e ON cm.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN grupos g ON cm.id_grupo = g.id
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
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_estudiante, cm.anio, cm.id_grupo, cm.valor_matricula, 
                   cm.valor_pension, cm.numero_cuotas, cm.cuotas_matricula, cm.valor_total,
                   cm.descuento_matricula, cm.recargo_matricula,
                   cm.descuento_pension, cm.recargo_pension,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_grupo,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante,
                   p.numero_identificacion AS documento_estudiante
            FROM contratos_matricula cm
            INNER JOIN estudiantes e ON cm.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN grupos g ON cm.id_grupo = g.id
            WHERE cm.id = :id AND cm.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByEstudiante($idEstudiante)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_estudiante, cm.anio, cm.id_grupo, cm.valor_matricula, 
                   cm.valor_pension, cm.numero_cuotas, cm.cuotas_matricula, cm.valor_total,
                   cm.descuento_matricula, cm.recargo_matricula,
                   cm.descuento_pension, cm.recargo_pension,
                   cm.razon_descuento, cm.razon_recargo,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.observaciones,
                   cm.id_usuario_genera, cm.fecha_generacion, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_grupo,
                   CONCAT_WS(' ', pu.primer_nombre, pu.primer_apellido) AS nombre_usuario_genera
            FROM contratos_matricula cm
            INNER JOIN grupos g ON cm.id_grupo = g.id
            LEFT JOIN usuarios u ON cm.id_usuario_genera = u.id
            LEFT JOIN personas pu ON u.id_persona = pu.id
            WHERE cm.id_estudiante = :id_estudiante AND cm.id_tenant = :id_tenant
            ORDER BY cm.anio DESC, cm.fecha_generacion DESC
        ");
        $sentence->bindParam(':id_estudiante', $idEstudiante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cm.id, cm.id_estudiante, cm.anio, cm.id_grupo, cm.valor_matricula, 
                   cm.valor_pension, cm.numero_cuotas, cm.valor_total,
                   cm.fecha_firma, cm.fecha_inicio, cm.fecha_fin, cm.lugar_firma, 
                   cm.autoriza_imagenes, cm.autoriza_pagare, cm.activo,
                   cm.firmado, cm.ruta_documento_firmado,
                   g.nombre AS nombre_grupo,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_estudiante
            FROM contratos_matricula cm
            INNER JOIN estudiantes e ON cm.id_estudiante = e.id
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN grupos g ON cm.id_grupo = g.id
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
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();
            
            $id_estudiante = Flight::request()->data['id_estudiante'];
            $anio = Flight::request()->data['anio'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $valor_matricula = Flight::request()->data['valor_matricula'];
            $descuento_matricula = isset(Flight::request()->data['descuento_matricula']) ? Flight::request()->data['descuento_matricula'] : 0;
            $recargo_matricula = isset(Flight::request()->data['recargo_matricula']) ? Flight::request()->data['recargo_matricula'] : 0;
            $valor_pension = Flight::request()->data['valor_pension'];
            $descuento_pension = isset(Flight::request()->data['descuento_pension']) ? Flight::request()->data['descuento_pension'] : 0;
            $recargo_pension = isset(Flight::request()->data['recargo_pension']) ? Flight::request()->data['recargo_pension'] : 0;
            $razon_descuento = isset(Flight::request()->data['razon_descuento']) ? Flight::request()->data['razon_descuento'] : null;
            $razon_recargo = isset(Flight::request()->data['razon_recargo']) ? Flight::request()->data['razon_recargo'] : null;
            
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $cuotas_matricula = isset(Flight::request()->data['cuotas_matricula']) ? Flight::request()->data['cuotas_matricula'] : 1;
            $valor_total = Flight::request()->data['valor_total'];
            $fecha_firma = Flight::request()->data['fecha_firma'];
            $fecha_inicio = isset(Flight::request()->data['fecha_inicio']) ? Flight::request()->data['fecha_inicio'] : null;
            $fecha_fin = isset(Flight::request()->data['fecha_fin']) ? Flight::request()->data['fecha_fin'] : null;
            $lugar_firma = isset(Flight::request()->data['lugar_firma']) ? Flight::request()->data['lugar_firma'] : 'Chía';
            $autoriza_imagenes = isset(Flight::request()->data['autoriza_imagenes']) ? Flight::request()->data['autoriza_imagenes'] : 0;
            $autoriza_pagare = isset(Flight::request()->data['autoriza_pagare']) ? Flight::request()->data['autoriza_pagare'] : 1;
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;
            $id_usuario_genera = isset(Flight::request()->data['id_usuario_genera']) ? Flight::request()->data['id_usuario_genera'] : null;
            $acudientes = isset(Flight::request()->data['acudientes']) ? Flight::request()->data['acudientes'] : [];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO contratos_matricula 
                (id, id_tenant, id_estudiante, anio, id_grupo, valor_matricula, descuento_matricula, recargo_matricula, 
                 valor_pension, descuento_pension, recargo_pension, razon_descuento, razon_recargo,
                 numero_cuotas, cuotas_matricula, valor_total, fecha_firma, fecha_inicio, fecha_fin, lugar_firma, 
                 autoriza_imagenes, autoriza_pagare, observaciones, id_usuario_genera) 
                VALUES 
                (:id, :id_tenant, :id_estudiante, :anio, :id_grupo, :valor_matricula, :descuento_matricula, :recargo_matricula,
                 :valor_pension, :descuento_pension, :recargo_pension, :razon_descuento, :razon_recargo,
                 :numero_cuotas, :cuotas_matricula, :valor_total, :fecha_firma, :fecha_inicio, :fecha_fin, :lugar_firma, 
                 :autoriza_imagenes, :autoriza_pagare, :observaciones, :id_usuario_genera)");
            
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_estudiante', $id_estudiante);
            $sentence->bindParam(':anio', $anio);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':valor_matricula', $valor_matricula);
            $sentence->bindParam(':descuento_matricula', $descuento_matricula);
            $sentence->bindParam(':recargo_matricula', $recargo_matricula);
            $sentence->bindParam(':valor_pension', $valor_pension);
            $sentence->bindParam(':descuento_pension', $descuento_pension);
            $sentence->bindParam(':recargo_pension', $recargo_pension);
            $sentence->bindParam(':razon_descuento', $razon_descuento);
            $sentence->bindParam(':razon_recargo', $razon_recargo);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':cuotas_matricula', $cuotas_matricula);
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

            if (!empty($acudientes)) {
                $sentenceAcudiente = $db->prepare("INSERT INTO contratos_matricula_acudientes 
                    (id_tenant, id_contrato, id_acudiente, orden) VALUES (:id_tenant, :id_contrato, :id_acudiente, :orden)");
                $sentenceAcudiente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                
                $orden = 1;
                foreach ($acudientes as $id_acudiente) {
                    $sentenceAcudiente->bindParam(':id_contrato', $id_contrato);
                    $sentenceAcudiente->bindParam(':id_acudiente', $id_acudiente);
                    $sentenceAcudiente->bindParam(':orden', $orden);
                    $sentenceAcudiente->execute();
                    $orden++;
                }
            }

            $db->commit();
            Flight::json(array('id' => $id_contrato));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosMatricula::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];
            $anio = Flight::request()->data['anio'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $valor_matricula = Flight::request()->data['valor_matricula'];
            $descuento_matricula = isset(Flight::request()->data['descuento_matricula']) ? Flight::request()->data['descuento_matricula'] : 0;
            $recargo_matricula = isset(Flight::request()->data['recargo_matricula']) ? Flight::request()->data['recargo_matricula'] : 0;
            $valor_pension = Flight::request()->data['valor_pension'];
            $descuento_pension = isset(Flight::request()->data['descuento_pension']) ? Flight::request()->data['descuento_pension'] : 0;
            $recargo_pension = isset(Flight::request()->data['recargo_pension']) ? Flight::request()->data['recargo_pension'] : 0;
            $razon_descuento = isset(Flight::request()->data['razon_descuento']) ? Flight::request()->data['razon_descuento'] : null;
            $razon_recargo = isset(Flight::request()->data['razon_recargo']) ? Flight::request()->data['razon_recargo'] : null;
            
            $numero_cuotas = Flight::request()->data['numero_cuotas'];
            $cuotas_matricula = isset(Flight::request()->data['cuotas_matricula']) ? Flight::request()->data['cuotas_matricula'] : 1;
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
            $acudientes = isset(Flight::request()->data['acudientes']) ? Flight::request()->data['acudientes'] : [];

            $sentence = $db->prepare("UPDATE contratos_matricula SET 
                anio = :anio,
                id_grupo = :id_grupo,
                valor_matricula = :valor_matricula,
                descuento_matricula = :descuento_matricula,
                recargo_matricula = :recargo_matricula,
                valor_pension = :valor_pension,
                descuento_pension = :descuento_pension,
                recargo_pension = :recargo_pension,
                razon_descuento = :razon_descuento,
                razon_recargo = :razon_recargo,
                numero_cuotas = :numero_cuotas,
                cuotas_matricula = :cuotas_matricula,
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
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':valor_matricula', $valor_matricula);
            $sentence->bindParam(':descuento_matricula', $descuento_matricula);
            $sentence->bindParam(':recargo_matricula', $recargo_matricula);
            $sentence->bindParam(':valor_pension', $valor_pension);
            $sentence->bindParam(':descuento_pension', $descuento_pension);
            $sentence->bindParam(':recargo_pension', $recargo_pension);
            $sentence->bindParam(':razon_descuento', $razon_descuento);
            $sentence->bindParam(':razon_recargo', $razon_recargo);
            $sentence->bindParam(':numero_cuotas', $numero_cuotas);
            $sentence->bindParam(':cuotas_matricula', $cuotas_matricula);
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

            if (!empty($acudientes)) {
                $sentenceDelete = $db->prepare("DELETE FROM contratos_matricula_acudientes WHERE id_contrato = :id AND id_tenant = :id_tenant");
                $sentenceDelete->bindParam(':id', $id);
                $sentenceDelete->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceDelete->execute();

                $sentenceAcudiente = $db->prepare("INSERT INTO contratos_matricula_acudientes 
                    (id_tenant, id_contrato, id_acudiente, orden) VALUES (:id_tenant, :id_contrato, :id_acudiente, :orden)");
                $sentenceAcudiente->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                
                $orden = 1;
                foreach ($acudientes as $id_acudiente) {
                    $sentenceAcudiente->bindParam(':id_contrato', $id);
                    $sentenceAcudiente->bindParam(':id_acudiente', $id_acudiente);
                    $sentenceAcudiente->bindParam(':orden', $orden);
                    $sentenceAcudiente->execute();
                    $orden++;
                }
            }

            $db->commit();
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ContratosMatricula::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function marcarFirmado()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $firmado = isset(Flight::request()->data['firmado']) ? Flight::request()->data['firmado'] : 1;
            $ruta_documento_firmado = isset(Flight::request()->data['ruta_documento_firmado']) ? Flight::request()->data['ruta_documento_firmado'] : null;

            $sentence = $db->prepare("UPDATE contratos_matricula SET 
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
            error_log("Error en ContratosMatricula::marcarFirmado: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function anular()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE contratos_matricula SET activo = 0 WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'activo' => 0));
        } catch (Exception $e) {
            error_log("Error en ContratosMatricula::anular: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM contratos_matricula_acudientes WHERE id_contrato = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        
        $sentence = $db->prepare("DELETE FROM contratos_matricula WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function getAcudientesByContrato($idContrato)
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT cma.id, cma.id_contrato, cma.id_acudiente, cma.orden,
                   a.id_tipo_acudiente, ta.nombre AS tipo_acudiente,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_acudiente,
                   p.numero_identificacion AS documento_acudiente,
                   ti.nombre AS tipo_identificacion
            FROM contratos_matricula_acudientes cma
            INNER JOIN acudientes a ON cma.id_acudiente = a.id
            INNER JOIN personas p ON a.id_persona = p.id
            INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
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
        PermisosService::validar($userData, 'estudiantes.contratos');

        $db = Flight::db();
        
        $sentenceContrato = $db->prepare("
            SELECT cm.*, g.nombre AS nombre_grupo
            FROM contratos_matricula cm
            INNER JOIN grupos g ON cm.id_grupo = g.id
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

        $sentenceEstudiante = $db->prepare("
            SELECT e.id, e.id_persona,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                   p.numero_identificacion, ti.nombre AS tipo_identificacion,
                   p.direccion, c.nombre AS ciudad
            FROM estudiantes e
            INNER JOIN personas p ON e.id_persona = p.id
            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            LEFT JOIN ciudades c ON p.id_ciudad = c.id
            WHERE e.id = :id_estudiante AND e.id_tenant = :id_tenant
        ");
        $sentenceEstudiante->bindParam(':id_estudiante', $contrato['id_estudiante']);
        $sentenceEstudiante->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceEstudiante->execute();
        $estudiante = $sentenceEstudiante->fetch();

        $sentenceAcudientes = $db->prepare("
            SELECT cma.orden,
                   CONCAT(p.primer_nombre, ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                          p.primer_apellido, ' ', IFNULL(p.segundo_apellido, '')) AS nombre_completo,
                   p.numero_identificacion, ti.nombre AS tipo_identificacion,
                   p.direccion, c.nombre AS ciudad,
                   ta.nombre AS tipo_acudiente,
                   a.es_responsable_pago
            FROM contratos_matricula_acudientes cma
            INNER JOIN acudientes a ON cma.id_acudiente = a.id
            INNER JOIN personas p ON a.id_persona = p.id
            INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
            INNER JOIN tipos_acudiente ta ON a.id_tipo_acudiente = ta.id
            LEFT JOIN ciudades c ON p.id_ciudad = c.id
            WHERE cma.id_contrato = :id_contrato AND cma.id_tenant = :id_tenant
            ORDER BY cma.orden
        ");
        $sentenceAcudientes->bindParam(':id_contrato', $idContrato);
        $sentenceAcudientes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceAcudientes->execute();
        $acudientes = $sentenceAcudientes->fetchAll();

        $sentenceConfig = $db->prepare("
            SELECT clave, valor_texto, valor_numero, valor_fecha
            FROM configuracion_global
            WHERE clave IN ('representante_legal_nombre', 'representante_legal_cedula', 
                           'representante_legal_cedula_lugar', 'institucion_nombre', 'institucion_nit',
                           'institucion_telefono', 'institucion_email', 'institucion_web', 'institucion_direccion',
                           'institucion_eslogan', 'institucion_razon_social')
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
            'estudiante' => $estudiante,
            'acudientes' => $acudientes,
            'configuracion' => $configuracion,
            'campos' => $campos
        ));
    }

    public static function verificarExistente()
    {
        $userData = JWTService::requerirAutenticacion();
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        $db = Flight::db();
        $id_estudiante = Flight::request()->data['id_estudiante'];
        $anio = Flight::request()->data['anio'];

        $sentence = $db->prepare("
            SELECT id, fecha_firma, activo 
            FROM contratos_matricula 
            WHERE id_estudiante = :id_estudiante AND anio = :anio AND activo = 1 AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_estudiante', $id_estudiante);
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
        PermisosService::validar($userData, 'estudiantes.contratos.administrar');

        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("UPDATE contratos_matricula SET 
                firmado = 0,
                ruta_documento_firmado = NULL
                WHERE id = :id AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'firmado' => 0, 'mensaje' => 'Contrato desmarcado exitosamente'));
        } catch (Exception $e) {
            error_log("Error en ContratosMatricula::desmarcarFirmado: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}