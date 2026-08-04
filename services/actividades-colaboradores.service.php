<?php
class ActividadesColaboradores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            tac.nombre as nombre_tipo_actividad,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            ur.nombre as nombre_usuario_registro,
            ua.nombre as nombre_usuario_aprobacion,
            uc.nombre as nombre_usuario_contabilizacion
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
            LEFT JOIN usuarios ua ON ac.id_usuario_aprobacion = ua.id
            LEFT JOIN usuarios uc ON ac.id_usuario_contabilizacion = uc.id
            WHERE ac.id_tenant = :id_tenant
            ORDER BY ac.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            tac.nombre as nombre_tipo_actividad,
            tac.valor_hora,
            ca.nombre as nombre_categoria,
            ca.id as id_categoria,
            ca.codigo as categoria_codigo,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            ur.nombre as nombre_usuario_registro,
            ua.nombre as nombre_usuario_aprobacion,
            uc.nombre as nombre_usuario_contabilizacion
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
            LEFT JOIN usuarios ua ON ac.id_usuario_aprobacion = ua.id
            LEFT JOIN usuarios uc ON ac.id_usuario_contabilizacion = uc.id
            WHERE ac.id = :id AND ac.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        if (!empty($response)) {
            foreach ($response as &$row) {
                if (isset($row['nombre_colaborador'])) {
                    $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
                }
            }
        }

        Flight::json($response);
    }

    public static function getByColaborador($id_colaborador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            tac.nombre as nombre_tipo_actividad,
            tac.valor_hora,
            ca.nombre as nombre_categoria,
            ea.nombre as nombre_estado,
            ea.color as color_estado
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
            WHERE ac.id_colaborador = :id_colaborador
            AND ac.id_tenant = :id_tenant
            ORDER BY ac.fecha_registro DESC");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getBalanceColaborador($id_colaborador)
    {
        $db = Flight::db();

        $sentencePermisos = $db->prepare("SELECT COALESCE(SUM(ac.minutos_totales), 0) as total_permisos
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            WHERE ac.id_colaborador = :id_colaborador 
            AND ca.codigo = 'PERMISO'
            AND ac.id_estado = 2
            AND ac.id_tenant = :id_tenant
            AND ac.id NOT IN (SELECT id_actividad_colaborador FROM contabilizaciones_detalle WHERE id_actividad_colaborador IS NOT NULL)");
        $sentencePermisos->bindParam(':id_colaborador', $id_colaborador);
        $sentencePermisos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentencePermisos->execute();
        $permisos = $sentencePermisos->fetch();

        $sentenceHoras = $db->prepare("SELECT COALESCE(SUM(ac.minutos_totales), 0) as total_horas,
            COALESCE(SUM(ac.minutos_totales * tac.valor_hora / 60), 0) as valor_total_horas
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            WHERE ac.id_colaborador = :id_colaborador 
            AND ca.codigo = 'HORA_ADICIONAL'
            AND ac.id_estado = 2
            AND ac.id_tenant = :id_tenant
            AND ac.id NOT IN (SELECT id_actividad_colaborador FROM contabilizaciones_detalle WHERE id_actividad_colaborador IS NOT NULL)");
        $sentenceHoras->bindParam(':id_colaborador', $id_colaborador);
        $sentenceHoras->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceHoras->execute();
        $horas = $sentenceHoras->fetch();

        $balance = array(
            'total_permisos' => $permisos['total_permisos'],
            'total_horas_adicionales' => $horas['total_horas'],
            'valor_horas_adicionales' => $horas['valor_total_horas'],
            'balance_minutos' => $horas['total_horas'] - $permisos['total_permisos']
        );

        Flight::json($balance);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            $id_colaborador = Flight::request()->data['id_colaborador'];
            $id_tipo_actividad = Flight::request()->data['id_tipo_actividad'];
            $fecha_hora_inicio = Flight::request()->data['fecha_hora_inicio'];
            $fecha_hora_fin = Flight::request()->data['fecha_hora_fin'];
            $observaciones = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;
            $ruta_documento = isset(Flight::request()->data['ruta_documento']) ?
                Flight::request()->data['ruta_documento'] : null;
            $id_usuario_registro = Flight::request()->data['id_usuario_registro'];

            $inicio = new DateTime($fecha_hora_inicio);
            $fin = new DateTime($fecha_hora_fin);
            $diferencia = $inicio->diff($fin);
            $minutos_totales = ($diferencia->days * 24 * 60) + ($diferencia->h * 60) + $diferencia->i;

            $id_estado = 1;

            $sentence = $db->prepare("INSERT INTO actividades_colaboradores 
                (id, id_tenant, id_colaborador, id_tipo_actividad, id_estado, fecha_hora_inicio, fecha_hora_fin, 
                minutos_totales, observaciones, ruta_documento, id_usuario_registro) 
                VALUES (:id, :id_tenant, :id_colaborador, :id_tipo_actividad, :id_estado, :fecha_hora_inicio, 
                :fecha_hora_fin, :minutos_totales, :observaciones, :ruta_documento, :id_usuario_registro)");

            $idAct = Uuid::generar();
            $sentence->bindValue(':id', $idAct);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':id_tipo_actividad', $id_tipo_actividad);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':fecha_hora_inicio', $fecha_hora_inicio);
            $sentence->bindParam(':fecha_hora_fin', $fecha_hora_fin);
            $sentence->bindParam(':minutos_totales', $minutos_totales);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':ruta_documento', $ruta_documento);
            $sentence->bindParam(':id_usuario_registro', $id_usuario_registro);
            $sentence->execute();

            $id = $idAct;
            Flight::json(array('id' => $id, 'message' => 'Actividad creada correctamente'));
        } catch (Exception $e) {
            error_log("Error en ActividadesColaboradores::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $check = $db->prepare("SELECT COUNT(*) as total FROM contabilizaciones_detalle 
                WHERE id_actividad_colaborador = :id AND id_tenant = :id_tenant");
            $check->bindParam(':id', $id);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();
            $result = $check->fetch();

            if ($result['total'] > 0) {
                Flight::json(array('error' => 'No se puede eliminar una actividad ya contabilizada'), 400);
                return;
            }

            $sentence = $db->prepare("DELETE FROM actividades_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'message' => 'Actividad eliminada correctamente'));
        } catch (Exception $e) {
            error_log("Error en ActividadesColaboradores::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getPendientesAprobar()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            tac.nombre as nombre_tipo_actividad,
            tac.valor_hora,
            ca.nombre as nombre_categoria,
            ca.id as id_categoria,
            ca.codigo as categoria_codigo,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            CONCAT(IFNULL(pr.primer_nombre, ''), ' ', IFNULL(pr.segundo_nombre, ''), ' ', 
                   IFNULL(pr.primer_apellido, ''), ' ', IFNULL(pr.segundo_apellido, '')) as nombre_usuario_registro
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN usuarios ur ON ac.id_usuario_registro = ur.id
            INNER JOIN personas pr ON ur.id_persona = pr.id
            WHERE ac.id_estado = 1
            AND ac.id_tenant = :id_tenant
            ORDER BY ac.fecha_registro ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
            if (isset($row['nombre_usuario_registro'])) {
                $row['nombre_usuario_registro'] = trim(preg_replace('/\s+/', ' ', $row['nombre_usuario_registro']));
            }
        }

        Flight::json($response);
    }

    public static function aprobarMultiple()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $ids = Flight::request()->data['ids'];
            $id_usuario_aprobacion = Flight::request()->data['id_usuario_aprobacion'];
            $observaciones_aprobacion = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;

            $fecha_aprobacion = date('Y-m-d H:i:s');
            $aprobados = 0;
            $errores = [];

            foreach ($ids as $id) {
                try {
                    $check = $db->prepare("SELECT id_estado FROM actividades_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
                    $check->bindParam(':id', $id);
                    $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $check->execute();
                    $actividad = $check->fetch();

                    if (!$actividad) {
                        $errores[] = "Actividad con ID $id no encontrada";
                        continue;
                    }

                    if ($actividad['id_estado'] != 1) {
                        $errores[] = "Actividad con ID $id no está en estado 'Registrado'";
                        continue;
                    }

                    $sentence = $db->prepare("UPDATE actividades_colaboradores SET 
                        id_estado = 2,
                        id_usuario_aprobacion = :id_usuario_aprobacion,
                        fecha_aprobacion = :fecha_aprobacion,
                        observaciones_aprobacion = :observaciones_aprobacion
                        WHERE id = :id AND id_tenant = :id_tenant");

                    $sentence->bindParam(':id_usuario_aprobacion', $id_usuario_aprobacion);
                    $sentence->bindParam(':fecha_aprobacion', $fecha_aprobacion);
                    $sentence->bindParam(':observaciones_aprobacion', $observaciones_aprobacion);
                    $sentence->bindParam(':id', $id);
                    $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $sentence->execute();

                    $aprobados++;
                } catch (Exception $e) {
                    $errores[] = "Error al aprobar actividad $id: " . $e->getMessage();
                }
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'aprobados' => $aprobados,
                'total' => count($ids),
                'errores' => $errores
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en ActividadesColaboradores::aprobarMultiple: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getAprobadas()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT ac.*, 
            tac.nombre as nombre_tipo_actividad,
            tac.valor_hora,
            ca.nombre as nombre_categoria,
            ca.id as id_categoria,
            ca.codigo as categoria_codigo,
            ca.es_cruzable,
            ea.nombre as nombre_estado,
            ea.color as color_estado,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            COALESCE((
                SELECT SUM(cd.minutos_aplicados) 
                FROM contabilizaciones_detalle cd
                WHERE cd.id_actividad_colaborador = ac.id
            ), 0) as minutos_ya_aplicados,
            (ac.minutos_totales - COALESCE((
                SELECT SUM(cd.minutos_aplicados) 
                FROM contabilizaciones_detalle cd
                WHERE cd.id_actividad_colaborador = ac.id
            ), 0)) as minutos_disponibles
            FROM actividades_colaboradores ac
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
            INNER JOIN colaboradores c ON ac.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            WHERE (ac.id_estado = 2 OR ac.id_estado = 5)
            AND ac.id_tenant = :id_tenant
            AND ca.es_cruzable = 1
            AND (ac.minutos_totales - COALESCE((
                SELECT SUM(cd.minutos_aplicados) 
                FROM contabilizaciones_detalle cd
                WHERE cd.id_actividad_colaborador = ac.id
            ), 0)) > 0
            ORDER BY ac.id_colaborador, ca.id, ac.fecha_aprobacion ASC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getResumenColaboradoresPendientes()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            c.id as id_colaborador,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                   IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
            SUM(CASE 
                WHEN ca.codigo = 'PERMISO' THEN (ac.minutos_totales - COALESCE(aplicados.total, 0))
                ELSE 0 
            END) as minutos_permisos,
            SUM(CASE 
                WHEN ca.codigo = 'HORA_ADICIONAL' THEN (ac.minutos_totales - COALESCE(aplicados.total, 0))
                ELSE 0 
            END) as minutos_horas,
            SUM(CASE 
                WHEN ca.codigo = 'HORA_ADICIONAL' THEN ((ac.minutos_totales - COALESCE(aplicados.total, 0)) / 60.0 * tac.valor_hora)
                ELSE 0 
            END) as valor_horas,
            COUNT(DISTINCT CASE WHEN ca.codigo = 'PERMISO' THEN ac.id END) as cantidad_permisos,
            COUNT(DISTINCT CASE WHEN ca.codigo = 'HORA_ADICIONAL' THEN ac.id END) as cantidad_horas
            FROM colaboradores c
            INNER JOIN personas p ON c.id_persona = p.id
            INNER JOIN actividades_colaboradores ac ON ac.id_colaborador = c.id
            INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
            INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
            LEFT JOIN (
                SELECT id_actividad_colaborador, SUM(minutos_aplicados) as total
                FROM contabilizaciones_detalle
                GROUP BY id_actividad_colaborador
            ) aplicados ON aplicados.id_actividad_colaborador = ac.id
            WHERE ac.id_estado IN (2, 5)
            AND ca.es_cruzable = 1
            AND c.id_tenant = :id_tenant
            AND c.activo = 1
            GROUP BY c.id, nombre_colaborador
            HAVING minutos_permisos > 0 AND minutos_horas > 0
            ORDER BY nombre_colaborador");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getHistorialPorColaborador()
    {
        $db = Flight::db();

        $id_colaborador = isset($_GET['id_colaborador']) ? $_GET['id_colaborador'] : null;
        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
        $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

        $sql = "SELECT 
        col.id as id_colaborador,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) as colaborador,
        
        ac.id as id_actividad,
        ac.id_tipo_actividad,
        tac.nombre as tipo_actividad,
        tac.valor_hora,
        ca.nombre as categoria,
        ca.registro_x_horas,
        ca.es_cruzable,
        ac.fecha_hora_inicio as fecha,
        ac.fecha_hora_fin as fecha_fin,
        ac.observaciones as observacion,
        ac.minutos_totales,
        
        COALESCE(SUM(cd.minutos_aplicados), 0) as minutos_aplicados,
        
        (ac.minutos_totales - COALESCE(SUM(cd.minutos_aplicados), 0)) as minutos_restantes,
        
        ea.nombre as estado,
        ea.color as color_estado,
        
        MAX(cd.id_contabilizacion) as id_contabilizacion,
        MAX(c.fecha_contabilizacion) as fecha_contabilizacion,
        MAX(tc.nombre) as tipo_contabilizacion
        
    FROM actividades_colaboradores ac
    INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
    INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
    INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
    INNER JOIN colaboradores col ON ac.id_colaborador = col.id
    INNER JOIN personas p ON col.id_persona = p.id
    
    LEFT JOIN contabilizaciones_detalle cd ON cd.id_actividad_colaborador = ac.id
    LEFT JOIN contabilizaciones c ON cd.id_contabilizacion = c.id
    LEFT JOIN tipos_contabilizacion tc ON c.id_tipo_contabilizacion = tc.id
    
    WHERE 1=1
        AND col.activo = 1
        AND ac.id_tenant = :id_tenant";

        $params = array(':id_tenant' => TenantContext::id());

        if ($id_colaborador && $id_colaborador !== '') {
            $sql .= " AND col.id = :id_colaborador";
            $params[':id_colaborador'] = $id_colaborador;
        }

        if ($fecha_inicio) {
            $sql .= " AND DATE(ac.fecha_hora_inicio) >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }

        if ($fecha_fin) {
            $sql .= " AND DATE(ac.fecha_hora_inicio) <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }

        $sql .= " GROUP BY 
        ac.id,
        col.id,
        p.primer_nombre,
        p.primer_apellido,
        ac.id_tipo_actividad,
        tac.nombre,
        tac.valor_hora,
        ca.nombre,
        ca.registro_x_horas,
        ca.es_cruzable,
        ac.fecha_hora_inicio,
        ac.fecha_hora_fin,
        ac.observaciones,
        ac.minutos_totales,
        ea.nombre,
        ea.color";

        $sql .= " ORDER BY colaborador, ac.fecha_hora_inicio DESC";

        $sentence = $db->prepare($sql);

        foreach ($params as $key => $value) {
            $sentence->bindValue($key, $value);
        }

        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['colaborador'])) {
                $row['colaborador'] = trim(preg_replace('/\s+/', ' ', $row['colaborador']));
            }
        }

        Flight::json($response);
    }


    public static function getColaboradoresActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
        c.id,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) as nombre
        FROM colaboradores c
        INNER JOIN personas p ON c.id_persona = p.id
        WHERE c.activo = 1
        AND c.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre'])) {
                $row['nombre'] = trim(preg_replace('/\s+/', ' ', $row['nombre']));
            }
        }

        Flight::json($response);
    }


    public static function getActividadesPorMes()
    {
        $db = Flight::db();

        $mes = isset($_GET['mes']) ? $_GET['mes'] : date('m');
        $anio = isset($_GET['anio']) ? $_GET['anio'] : date('Y');

        $fecha_inicio = "$anio-$mes-01";
        $fecha_fin = date("Y-m-t", strtotime($fecha_inicio));

        $sentence = $db->prepare("SELECT 
        ac.id,
        ac.id_colaborador,
        ac.id_tipo_actividad,
        ac.id_estado,
        ac.fecha_hora_inicio,
        ac.fecha_hora_fin,
        ac.minutos_totales,
        ac.observaciones,
        ac.ruta_documento,
        
        tac.nombre as nombre_tipo_actividad,
        tac.valor_hora,
        tac.id_categoria,
        
        ca.nombre as nombre_categoria,
        ca.color as color_categoria,
        ca.icono as icono_categoria,
        ca.registro_x_horas,
        ca.es_cruzable,
        
        ea.nombre as nombre_estado,
        ea.color as color_estado,
        
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
               IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_colaborador,
        c.sobrenombre as sobrenombre_colaborador
        
        FROM actividades_colaboradores ac
        INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
        INNER JOIN categorias_actividades ca ON tac.id_categoria = ca.id
        INNER JOIN estados_actividades ea ON ac.id_estado = ea.id
        INNER JOIN colaboradores c ON ac.id_colaborador = c.id
        INNER JOIN personas p ON c.id_persona = p.id
        
        WHERE DATE(ac.fecha_hora_inicio) BETWEEN :fecha_inicio AND :fecha_fin
        AND ac.id_tenant = :id_tenant
        AND c.activo = 1
        
        ORDER BY ac.fecha_hora_inicio ASC, nombre_colaborador ASC");

        $sentence->bindParam(':fecha_inicio', $fecha_inicio);
        $sentence->bindParam(':fecha_fin', $fecha_fin);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_colaborador'])) {
                $row['nombre_colaborador'] = trim(preg_replace('/\s+/', ' ', $row['nombre_colaborador']));
            }
        }

        Flight::json($response);
    }

    public static function getColaboradoresParaCalendario()
    {
        $db = Flight::db();

        $sentence = $db->prepare("SELECT 
        c.id,
        CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
               IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, '')) as nombre_completo,
        c.sobrenombre
        
        FROM colaboradores c
        INNER JOIN personas p ON c.id_persona = p.id
        
        WHERE c.activo = 1
        AND c.id_tenant = :id_tenant
        
        ORDER BY p.primer_apellido, p.primer_nombre");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        foreach ($response as &$row) {
            if (isset($row['nombre_completo'])) {
                $row['nombre_completo'] = trim(preg_replace('/\s+/', ' ', $row['nombre_completo']));
            }
        }

        Flight::json($response);
    }

    public static function getGruposParaCalendario()
    {
        $db = Flight::db();

        $sentence = $db->prepare("SELECT 
        id,
        nombre,
        icono,
        color,
        orden
        
        FROM grupos
        
        WHERE id_tenant = :id_tenant
        ORDER BY orden, nombre");

        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();

        Flight::json($response);
    }
}