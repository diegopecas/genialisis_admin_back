<?php
class Contabilizaciones
{
    public static function getAll()
    {
        $db = Flight::db();
        
        // Obtener parámetros de filtro
        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
        $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
        
        // Construir query con filtros
        $sql = "SELECT c.*, 
            tc.nombre as tipo_contabilizacion,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) as usuario_contabilizacion
            FROM contabilizaciones c
            INNER JOIN tipos_contabilizacion tc ON c.id_tipo_contabilizacion = tc.id
            INNER JOIN usuarios u ON c.id_usuario_contabilizacion = u.id
            INNER JOIN personas p ON u.id_persona = p.id
            WHERE 1=1 AND c.id_tenant = :id_tenant";
        
        $params = array(':id_tenant' => TenantContext::id());
        
        if ($fecha_inicio) {
            $sql .= " AND DATE(c.fecha_contabilizacion) >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if ($fecha_fin) {
            $sql .= " AND DATE(c.fecha_contabilizacion) <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        
        if ($tipo && $tipo !== '') {
            $sql .= " AND c.id_tipo_contabilizacion = :tipo";
            $params[':tipo'] = $tipo;
        }
        
        $sql .= " ORDER BY c.fecha_contabilizacion DESC";
        
        $sentence = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $sentence->bindValue($key, $value);
        }
        
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    // Nuevo método con filtros avanzados incluyendo colaborador
    public static function getAllConFiltrosAvanzados()
    {
        $db = Flight::db();
        
        // Obtener parámetros de filtro
        $fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
        $fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;
        $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
        $id_colaborador = isset($_GET['id_colaborador']) ? $_GET['id_colaborador'] : null;
        
        // Query base con JOIN a colaboradores para poder incluir nombre de colaborador
        $sql = "SELECT DISTINCT c.*, 
            tc.nombre as tipo_contabilizacion,
            CONCAT(IFNULL(pu.primer_nombre, ''), ' ', IFNULL(pu.primer_apellido, '')) as usuario_contabilizacion,
            GROUP_CONCAT(DISTINCT CONCAT(IFNULL(pc.primer_nombre, ''), ' ', IFNULL(pc.primer_apellido, '')) 
                ORDER BY pc.primer_apellido SEPARATOR ', ') as colaboradores
            FROM contabilizaciones c
            INNER JOIN tipos_contabilizacion tc ON c.id_tipo_contabilizacion = tc.id
            INNER JOIN usuarios u ON c.id_usuario_contabilizacion = u.id
            INNER JOIN personas pu ON u.id_persona = pu.id
            LEFT JOIN contabilizaciones_detalle cd ON c.id = cd.id_contabilizacion
            LEFT JOIN actividades_colaboradores ac ON cd.id_actividad_colaborador = ac.id
            LEFT JOIN colaboradores col ON ac.id_colaborador = col.id
            LEFT JOIN personas pc ON col.id_persona = pc.id
            WHERE 1=1 AND c.id_tenant = :id_tenant";
        
        $params = array(':id_tenant' => TenantContext::id());
        
        if ($fecha_inicio) {
            $sql .= " AND DATE(c.fecha_contabilizacion) >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if ($fecha_fin) {
            $sql .= " AND DATE(c.fecha_contabilizacion) <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin;
        }
        
        if ($tipo && $tipo !== '') {
            $sql .= " AND c.id_tipo_contabilizacion = :tipo";
            $params[':tipo'] = $tipo;
        }
        
        if ($id_colaborador && $id_colaborador !== '') {
            $sql .= " AND ac.id_colaborador = :id_colaborador";
            $params[':id_colaborador'] = $id_colaborador;
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.fecha_contabilizacion DESC";
        
        $sentence = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $sentence->bindValue($key, $value);
        }
        
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT c.*, 
            tc.nombre as tipo_contabilizacion,
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) as usuario_contabilizacion
            FROM contabilizaciones c
            INNER JOIN tipos_contabilizacion tc ON c.id_tipo_contabilizacion = tc.id
            INNER JOIN usuarios u ON c.id_usuario_contabilizacion = u.id
            INNER JOIN personas p ON u.id_persona = p.id
            WHERE c.id = :id AND c.id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDetalle($id_contabilizacion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT 
            -- Info del colaborador
            CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.primer_apellido, '')) as colaborador,
            col.id as id_colaborador,
            
            -- Info del cruce
            cdc.minutos_cruzados,
            
            -- Info del PERMISO (actividad izquierda)
            cd_p.id as id_detalle_permiso,
            ac_p.id as id_actividad_permiso,
            tac_p.nombre as tipo_actividad_permiso,
            ca_p.nombre as categoria_permiso,
            ac_p.fecha_hora_inicio as fecha_permiso,
            ac_p.observaciones as observacion_permiso,
            cd_p.minutos_aplicados as minutos_aplicados_permiso,
            ac_p.minutos_totales as minutos_totales_permiso,
            (ac_p.minutos_totales - cd_p.minutos_aplicados) as minutos_restantes_permiso,
            
            -- Info de la HORA EXTRA (actividad derecha)
            cd_h.id as id_detalle_hora,
            ac_h.id as id_actividad_hora,
            tac_h.nombre as tipo_actividad_hora,
            ca_h.nombre as categoria_hora,
            ac_h.fecha_hora_inicio as fecha_hora,
            ac_h.observaciones as observacion_hora,
            cd_h.minutos_aplicados as minutos_aplicados_hora,
            ac_h.minutos_totales as minutos_totales_hora,
            (ac_h.minutos_totales - cd_h.minutos_aplicados) as minutos_restantes_hora
            
        FROM contabilizaciones_detalle_cruces cdc
        
        -- JOIN con detalle del permiso
        INNER JOIN contabilizaciones_detalle cd_p ON cdc.id_detalle_permiso = cd_p.id
        INNER JOIN actividades_colaboradores ac_p ON cd_p.id_actividad_colaborador = ac_p.id
        INNER JOIN tipos_actividades_colaboradores tac_p ON ac_p.id_tipo_actividad = tac_p.id
        INNER JOIN categorias_actividades ca_p ON tac_p.id_categoria = ca_p.id
        
        -- JOIN con detalle de la hora
        INNER JOIN contabilizaciones_detalle cd_h ON cdc.id_detalle_hora = cd_h.id
        INNER JOIN actividades_colaboradores ac_h ON cd_h.id_actividad_colaborador = ac_h.id
        INNER JOIN tipos_actividades_colaboradores tac_h ON ac_h.id_tipo_actividad = tac_h.id
        INNER JOIN categorias_actividades ca_h ON tac_h.id_categoria = ca_h.id
        
        -- JOIN con colaborador
        INNER JOIN colaboradores col ON ac_p.id_colaborador = col.id
        INNER JOIN personas p ON col.id_persona = p.id
        
        WHERE cd_p.id_contabilizacion = :id_contabilizacion AND cdc.id_tenant = :id_tenant
        ORDER BY p.primer_apellido, p.primer_nombre, cdc.id");
        
        $sentence->bindParam(':id_contabilizacion', $id_contabilizacion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function cruzarActividadesColaboradores()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $ids_permisos = Flight::request()->data['ids_permisos'];
            $ids_horas_adicionales = Flight::request()->data['ids_horas_adicionales'];
            $id_usuario_contabilizacion = Flight::request()->data['id_usuario_contabilizacion'];
            $observaciones = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;

            // 1. Obtener actividades con minutos disponibles
            $permisos = self::obtenerActividadesConDisponibles($db, $ids_permisos);
            $horas = self::obtenerActividadesConDisponibles($db, $ids_horas_adicionales);
            
            // 2. Calcular totales disponibles
            $total_permisos_disponibles = array_sum(array_column($permisos, 'minutos_disponibles'));
            $total_horas_disponibles = array_sum(array_column($horas, 'minutos_disponibles'));
            
            $minutos_a_cruzar = min($total_permisos_disponibles, $total_horas_disponibles);

            if ($minutos_a_cruzar <= 0) {
                throw new Exception('No hay minutos disponibles para cruzar');
            }

            // 3. Crear contabilización (SIN id_colaborador)
            $fecha_contabilizacion = date('Y-m-d H:i:s');
            $id_tipo_contabilizacion = 1; // Cruce entre actividades
            
            $sentence = $db->prepare("INSERT INTO contabilizaciones 
                (id, id_tenant, id_tipo_contabilizacion, fecha_contabilizacion, 
                id_usuario_contabilizacion, observaciones, minutos_total, valor_total) 
                VALUES (:id, :id_tenant, :id_tipo, :fecha, :id_usuario, :observaciones, :minutos_total, NULL)");
            
            $idContab = Uuid::generar();
            $sentence->bindValue(':id', $idContab);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo', $id_tipo_contabilizacion);
            $sentence->bindParam(':fecha', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario', $id_usuario_contabilizacion);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':minutos_total', $minutos_a_cruzar);
            $sentence->execute();
            
            $id_contabilizacion = $idContab;

            // 4. Aplicar minutos a PERMISOS (FIFO) y guardar IDs
            $ids_detalles_permisos = array();
            $minutos_restantes = $minutos_a_cruzar;
            foreach ($permisos as $permiso) {
                if ($minutos_restantes <= 0) break;
                
                $minutos_a_aplicar = min($permiso['minutos_disponibles'], $minutos_restantes);
                
                $id_detalle = self::insertarDetalle($db, $id_contabilizacion, $permiso['id'], $minutos_a_aplicar);
                $ids_detalles_permisos[] = array(
                    'id_detalle' => $id_detalle,
                    'minutos' => $minutos_a_aplicar
                );
                
                self::actualizarEstadoActividad($db, $permiso['id'], $minutos_a_aplicar, 
                    $permiso['minutos_totales'], $permiso['minutos_ya_aplicados'],
                    $id_usuario_contabilizacion, $fecha_contabilizacion);
                
                $minutos_restantes -= $minutos_a_aplicar;
            }

            // 5. Aplicar minutos a HORAS (FIFO) y guardar IDs
            $ids_detalles_horas = array();
            $minutos_restantes = $minutos_a_cruzar;
            foreach ($horas as $hora) {
                if ($minutos_restantes <= 0) break;
                
                $minutos_a_aplicar = min($hora['minutos_disponibles'], $minutos_restantes);
                
                $id_detalle = self::insertarDetalle($db, $id_contabilizacion, $hora['id'], $minutos_a_aplicar);
                $ids_detalles_horas[] = array(
                    'id_detalle' => $id_detalle,
                    'minutos' => $minutos_a_aplicar
                );
                
                self::actualizarEstadoActividad($db, $hora['id'], $minutos_a_aplicar, 
                    $hora['minutos_totales'], $hora['minutos_ya_aplicados'],
                    $id_usuario_contabilizacion, $fecha_contabilizacion);
                
                $minutos_restantes -= $minutos_a_aplicar;
            }

            // 6. REGISTRAR CRUCES en la tabla intermedia (FIFO)
            $minutos_por_cruzar = $minutos_a_cruzar;
            
            foreach ($ids_detalles_permisos as $det_permiso) {
                if ($minutos_por_cruzar <= 0) break;
                
                $minutos_permiso_restantes = $det_permiso['minutos'];
                
                foreach ($ids_detalles_horas as $det_hora) {
                    if ($minutos_permiso_restantes <= 0) break;
                    if ($minutos_por_cruzar <= 0) break;
                    
                    $minutos_cruce = min($minutos_permiso_restantes, $det_hora['minutos'], $minutos_por_cruzar);
                    
                    self::insertarCruce($db, $det_permiso['id_detalle'], $det_hora['id_detalle'], $minutos_cruce);
                    
                    $minutos_permiso_restantes -= $minutos_cruce;
                    $det_hora['minutos'] -= $minutos_cruce;
                    $minutos_por_cruzar -= $minutos_cruce;
                }
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id' => $id_contabilizacion,
                'minutos_cruzados' => $minutos_a_cruzar
            ));
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en Contabilizaciones::cruzarActividadesColaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // NUEVO: Cruzar múltiples colaboradores en una sola transacción
    public static function cruzarMultiplesColaboradores()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $colaboradores = Flight::request()->data['colaboradores']; // Array de {id_colaborador, ids_permisos, ids_horas}
            $id_usuario_contabilizacion = Flight::request()->data['id_usuario_contabilizacion'];
            $observaciones = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;

            $fecha_contabilizacion = date('Y-m-d H:i:s');
            $id_tipo_contabilizacion = 1; // Cruce entre actividades
            
            // Calcular el total de minutos a cruzar de TODOS los colaboradores
            $minutos_total_general = 0;
            $colaboradores_con_datos = array();
            
            foreach ($colaboradores as $colaborador) {
                $ids_permisos = $colaborador['ids_permisos'];
                $ids_horas = $colaborador['ids_horas'];

                // Obtener actividades con minutos disponibles
                $permisos = self::obtenerActividadesConDisponibles($db, $ids_permisos);
                $horas = self::obtenerActividadesConDisponibles($db, $ids_horas);
                
                $total_permisos = array_sum(array_column($permisos, 'minutos_disponibles'));
                $total_horas = array_sum(array_column($horas, 'minutos_disponibles'));
                $minutos_a_cruzar = min($total_permisos, $total_horas);

                if ($minutos_a_cruzar > 0) {
                    $minutos_total_general += $minutos_a_cruzar;
                    $colaboradores_con_datos[] = array(
                        'id_colaborador' => $colaborador['id_colaborador'],
                        'permisos' => $permisos,
                        'horas' => $horas,
                        'minutos_a_cruzar' => $minutos_a_cruzar
                    );
                }
            }

            if ($minutos_total_general <= 0 || count($colaboradores_con_datos) === 0) {
                throw new Exception('No hay minutos disponibles para cruzar en ningún colaborador');
            }

            // ✅ CREAR UNA SOLA CONTABILIZACIÓN para TODOS los colaboradores
            $sentence = $db->prepare("INSERT INTO contabilizaciones 
                (id, id_tenant, id_tipo_contabilizacion, fecha_contabilizacion, 
                id_usuario_contabilizacion, observaciones, minutos_total, valor_total) 
                VALUES (:id, :id_tenant, :id_tipo, :fecha, :id_usuario, :observaciones, :minutos_total, NULL)");
            
            $idContab = Uuid::generar();
            $sentence->bindValue(':id', $idContab);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo', $id_tipo_contabilizacion);
            $sentence->bindParam(':fecha', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario', $id_usuario_contabilizacion);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':minutos_total', $minutos_total_general);
            $sentence->execute();
            
            $id_contabilizacion = $idContab;

            // ✅ AGREGAR DETALLES de TODOS los colaboradores a la MISMA contabilización
            $resultados = array();
            
            foreach ($colaboradores_con_datos as $col_data) {
                // Guardar IDs de los detalles insertados para registrar los cruces
                $ids_detalles_permisos = array();
                $ids_detalles_horas = array();
                
                // Aplicar minutos a permisos y guardar IDs
                $minutos_restantes = $col_data['minutos_a_cruzar'];
                foreach ($col_data['permisos'] as $permiso) {
                    if ($minutos_restantes <= 0) break;
                    $minutos_a_aplicar = min($permiso['minutos_disponibles'], $minutos_restantes);
                    
                    // Insertar detalle y obtener su ID
                    $id_detalle = self::insertarDetalle($db, $id_contabilizacion, $permiso['id'], $minutos_a_aplicar);
                    $ids_detalles_permisos[] = array(
                        'id_detalle' => $id_detalle,
                        'minutos' => $minutos_a_aplicar
                    );
                    
                    self::actualizarEstadoActividad($db, $permiso['id'], $minutos_a_aplicar, 
                        $permiso['minutos_totales'], $permiso['minutos_ya_aplicados'],
                        $id_usuario_contabilizacion, $fecha_contabilizacion);
                    $minutos_restantes -= $minutos_a_aplicar;
                }

                // Aplicar minutos a horas y guardar IDs
                $minutos_restantes = $col_data['minutos_a_cruzar'];
                foreach ($col_data['horas'] as $hora) {
                    if ($minutos_restantes <= 0) break;
                    $minutos_a_aplicar = min($hora['minutos_disponibles'], $minutos_restantes);
                    
                    // Insertar detalle y obtener su ID
                    $id_detalle = self::insertarDetalle($db, $id_contabilizacion, $hora['id'], $minutos_a_aplicar);
                    $ids_detalles_horas[] = array(
                        'id_detalle' => $id_detalle,
                        'minutos' => $minutos_a_aplicar
                    );
                    
                    self::actualizarEstadoActividad($db, $hora['id'], $minutos_a_aplicar, 
                        $hora['minutos_totales'], $hora['minutos_ya_aplicados'],
                        $id_usuario_contabilizacion, $fecha_contabilizacion);
                    $minutos_restantes -= $minutos_a_aplicar;
                }

                // ✅ REGISTRAR CRUCES en la tabla intermedia
                // Lógica FIFO: cada permiso cruza con cada hora proporcionalmente
                $minutos_por_cruzar = $col_data['minutos_a_cruzar'];
                
                foreach ($ids_detalles_permisos as $det_permiso) {
                    if ($minutos_por_cruzar <= 0) break;
                    
                    $minutos_permiso_restantes = $det_permiso['minutos'];
                    
                    foreach ($ids_detalles_horas as $det_hora) {
                        if ($minutos_permiso_restantes <= 0) break;
                        if ($minutos_por_cruzar <= 0) break;
                        
                        // Calcular cuántos minutos cruzan entre este permiso y esta hora
                        $minutos_cruce = min($minutos_permiso_restantes, $det_hora['minutos'], $minutos_por_cruzar);
                        
                        // Registrar el cruce
                        self::insertarCruce($db, $det_permiso['id_detalle'], $det_hora['id_detalle'], $minutos_cruce);
                        
                        $minutos_permiso_restantes -= $minutos_cruce;
                        $det_hora['minutos'] -= $minutos_cruce;
                        $minutos_por_cruzar -= $minutos_cruce;
                    }
                }

                $resultados[] = array(
                    'id_colaborador' => $col_data['id_colaborador'],
                    'minutos_cruzados' => $col_data['minutos_a_cruzar']
                );
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id_contabilizacion' => $id_contabilizacion,
                'colaboradores_procesados' => count($resultados),
                'minutos_total' => $minutos_total_general,
                'resultados' => $resultados
            ));
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en Contabilizaciones::cruzarMultiplesColaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    private static function obtenerActividadesConDisponibles($db, $ids)
    {
        if (empty($ids)) {
            return array();
        }
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $query = "SELECT 
            ac.id,
            ac.minutos_totales,
            COALESCE(SUM(cd.minutos_aplicados), 0) as minutos_ya_aplicados,
            (ac.minutos_totales - COALESCE(SUM(cd.minutos_aplicados), 0)) as minutos_disponibles
            FROM actividades_colaboradores ac
            LEFT JOIN contabilizaciones_detalle cd ON cd.id_actividad_colaborador = ac.id
            WHERE ac.id IN ($placeholders)
            AND ac.id_tenant = ?
            GROUP BY ac.id
            HAVING minutos_disponibles > 0
            ORDER BY ac.fecha_aprobacion ASC";
        
        $sentence = $db->prepare($query);
        $sentence->execute(array_merge($ids, [TenantContext::id()]));
        return $sentence->fetchAll();
    }

    private static function insertarDetalle($db, $id_contabilizacion, $id_actividad, $minutos_aplicados)
    {
        $sentenceDetalle = $db->prepare("INSERT INTO contabilizaciones_detalle 
            (id, id_tenant, id_contabilizacion, id_actividad_colaborador, minutos_aplicados) 
            VALUES (:id, :id_tenant, :id_contabilizacion, :id_actividad, :minutos_aplicados)");
        
        $idCD = Uuid::generar();
        $sentenceDetalle->bindValue(':id', $idCD);
        $sentenceDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentenceDetalle->bindParam(':id_contabilizacion', $id_contabilizacion);
        $sentenceDetalle->bindParam(':id_actividad', $id_actividad);
        $sentenceDetalle->bindParam(':minutos_aplicados', $minutos_aplicados);
        $sentenceDetalle->execute();
        
        // Retornar el ID del detalle insertado
        return $idCD;
    }

    private static function insertarCruce($db, $id_detalle_permiso, $id_detalle_hora, $minutos_cruzados)
    {
        $sentence = $db->prepare("INSERT INTO contabilizaciones_detalle_cruces 
            (id, id_tenant, id_detalle_permiso, id_detalle_hora, minutos_cruzados) 
            VALUES (:id, :id_tenant, :id_permiso, :id_hora, :minutos)");
        
        $idCruce = Uuid::generar();
        $sentence->bindValue(':id', $idCruce);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_permiso', $id_detalle_permiso);
        $sentence->bindParam(':id_hora', $id_detalle_hora);
        $sentence->bindParam(':minutos', $minutos_cruzados);
        $sentence->execute();
    }

    private static function actualizarEstadoActividad($db, $id_actividad, $minutos_aplicados, 
        $minutos_totales, $minutos_ya_aplicados, $id_usuario, $fecha)
    {
        $total_aplicado = $minutos_ya_aplicados + $minutos_aplicados;
        // 4 = Contabilizado (completo), 5 = Contabilizado Parcial
        $nuevo_estado = ($total_aplicado >= $minutos_totales) ? 4 : 5;
        
        $update = $db->prepare("UPDATE actividades_colaboradores SET 
            id_estado = :estado,
            id_usuario_contabilizacion = :id_usuario,
            fecha_contabilizacion = :fecha
            WHERE id = :id AND id_tenant = :id_tenant");
        
        $update->bindParam(':estado', $nuevo_estado);
        $update->bindParam(':id_usuario', $id_usuario);
        $update->bindParam(':fecha', $fecha);
        $update->bindParam(':id', $id_actividad);
        $update->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $update->execute();
    }

    public static function contabilizarParaNominaColaboradores()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $ids = Flight::request()->data['ids'];
            $id_tipo_contabilizacion = Flight::request()->data['id_tipo_contabilizacion']; // 2=Pago, 3=Descuento
            $id_usuario_contabilizacion = Flight::request()->data['id_usuario_contabilizacion'];
            $observaciones = isset(Flight::request()->data['observaciones']) ?
                Flight::request()->data['observaciones'] : null;

            $total_minutos = 0;
            $total_valor = 0;

            foreach ($ids as $id) {
                $s = $db->prepare("SELECT ac.minutos_totales, tac.valor_hora 
                    FROM actividades_colaboradores ac
                    INNER JOIN tipos_actividades_colaboradores tac ON ac.id_tipo_actividad = tac.id
                    WHERE ac.id = :id AND ac.id_tenant = :id_tenant");
                $s->bindParam(':id', $id);
                $s->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $s->execute();
                $r = $s->fetch();
                if ($r) {
                    $total_minutos += $r['minutos_totales'];
                    if ($r['valor_hora']) {
                        $total_valor += ($r['minutos_totales'] / 60) * $r['valor_hora'];
                    }
                }
            }

            $fecha_contabilizacion = date('Y-m-d H:i:s');

            // Crear contabilización
            $sentence = $db->prepare("INSERT INTO contabilizaciones 
                (id, id_tenant, id_tipo_contabilizacion, fecha_contabilizacion, id_usuario_contabilizacion, 
                observaciones, minutos_total, valor_total) 
                VALUES (:id, :id_tenant, :id_tipo, :fecha, :id_usuario, :observaciones, :minutos_total, :valor_total)");

            $idContabNom = Uuid::generar();
            $sentence->bindValue(':id', $idContabNom);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_tipo', $id_tipo_contabilizacion);
            $sentence->bindParam(':fecha', $fecha_contabilizacion);
            $sentence->bindParam(':id_usuario', $id_usuario_contabilizacion);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':minutos_total', $total_minutos);
            $sentence->bindParam(':valor_total', $total_valor);
            $sentence->execute();

            $id_contabilizacion = $idContabNom;

            // Agregar detalles
            foreach ($ids as $id) {
                $sentenceDetalle = $db->prepare("INSERT INTO contabilizaciones_detalle 
                    (id, id_tenant, id_contabilizacion, id_actividad_colaborador, minutos_aplicados) 
                    SELECT :id, :id_tenant, :id_contabilizacion, :id_actividad, minutos_totales 
                    FROM actividades_colaboradores WHERE id = :id_actividad2 AND id_tenant = :id_tenant_f");
                $idCDNom = Uuid::generar();
                $sentenceDetalle->bindValue(':id', $idCDNom);
                $sentenceDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentenceDetalle->bindValue(':id_tenant_f', TenantContext::id(), PDO::PARAM_INT);
                $sentenceDetalle->bindParam(':id_contabilizacion', $id_contabilizacion);
                $sentenceDetalle->bindParam(':id_actividad', $id);
                $sentenceDetalle->bindParam(':id_actividad2', $id);
                $sentenceDetalle->execute();

                // Actualizar estado a "Contabilizado"
                $updateEstado = $db->prepare("UPDATE actividades_colaboradores SET 
                    id_estado = 4, 
                    id_usuario_contabilizacion = :id_usuario,
                    fecha_contabilizacion = :fecha 
                    WHERE id = :id AND id_tenant = :id_tenant");
                $updateEstado->bindParam(':id_usuario', $id_usuario_contabilizacion);
                $updateEstado->bindParam(':fecha', $fecha_contabilizacion);
                $updateEstado->bindParam(':id', $id);
                $updateEstado->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $updateEstado->execute();
            }

            $db->commit();

            Flight::json(array(
                'success' => true,
                'id' => $id_contabilizacion,
                'total_minutos' => $total_minutos,
                'total_valor' => $total_valor
            ));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en Contabilizaciones::contabilizarParaNominaColaboradores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $db = Flight::db();
            $db->beginTransaction();

            $id = Flight::request()->data['id'];

            // Eliminar detalles primero
            $sentenceDetalle = $db->prepare("DELETE FROM contabilizaciones_detalle WHERE id_contabilizacion = :id AND id_tenant = :id_tenant");
            $sentenceDetalle->bindParam(':id', $id);
            $sentenceDetalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentenceDetalle->execute();

            // Eliminar contabilización
            $sentence = $db->prepare("DELETE FROM contabilizaciones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array('id' => $id, 'message' => 'Contabilización eliminada correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en Contabilizaciones::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}