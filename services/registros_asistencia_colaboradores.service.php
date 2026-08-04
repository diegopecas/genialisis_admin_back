<?php
class RegistrosAsistenciaColaboradores
{
    public static function getByColaborador($id_colaborador)
    {
        $db = Flight::db();
        $fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
        $fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

        $sentence = $db->prepare("SELECT ra.id, ra.id_colaborador, ra.fecha, ra.id_tipo_registro, tra.nombre nombre_tipo, tra.codigo codigo_tipo,
            ra.hora_registro, ra.fecha_registro_real, ra.latitud, ra.longitud, ra.distancia_metros, ra.dentro_rango,
            ra.id_estado, era.nombre nombre_estado, era.codigo codigo_estado,
            ra.registro_manual, ra.id_autorizado_por,
            CONCAT(IFNULL(pa.primer_nombre,''),' ',IFNULL(pa.primer_apellido,'')) AS nombre_autorizado_por,
            ra.fecha_autorizacion, ra.observaciones, ra.created_at
            FROM registros_asistencia_colaboradores ra
            INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            LEFT JOIN colaboradores ca ON ra.id_autorizado_por = ca.id
            LEFT JOIN personas pa ON ca.id_persona = pa.id
            WHERE ra.id_colaborador = :id_colaborador
            AND ra.fecha BETWEEN :fecha_desde AND :fecha_hasta
            AND ra.id_tenant = :id_tenant
            ORDER BY ra.fecha DESC, ra.hora_registro DESC");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->bindParam(':fecha_desde', $fecha_desde);
        $sentence->bindParam(':fecha_hasta', $fecha_hasta);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getRegistrosHoy($id_colaborador)
    {
        $db = Flight::db();
        $hoy = date('Y-m-d');

        $sentence = $db->prepare("SELECT ra.id, ra.id_tipo_registro, tra.nombre nombre_tipo, tra.codigo codigo_tipo, tra.orden,
            ra.hora_registro, ra.fecha_registro_real, ra.latitud, ra.longitud, ra.distancia_metros, ra.dentro_rango,
            ra.id_estado, era.nombre nombre_estado, era.codigo codigo_estado,
            ra.registro_manual, ra.observaciones
            FROM registros_asistencia_colaboradores ra
            INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            WHERE ra.id_colaborador = :id_colaborador AND ra.fecha = :hoy
            AND ra.id_tenant = :id_tenant
            ORDER BY ra.hora_registro ASC");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->bindParam(':hoy', $hoy);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getTiposRegistro()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, codigo, orden FROM tipos_registro_asistencia WHERE activo = 1 AND id_tenant = :id_tenant ORDER BY orden");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getEstadosRegistro()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre, codigo, orden FROM estados_registro_asistencia WHERE activo = 1 ORDER BY orden");
        $sentence->execute();
        Flight::json($sentence->fetchAll());
    }

    public static function getConfiguracionGeofence()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT clave, valor_texto, valor_numero FROM configuracion_global WHERE clave IN ('asistencia_tolerancia_minutos','asistencia_geofence_poligono') AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $rows = $sentence->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            if ($row['valor_texto']) {
                $config[$row['clave']] = $row['valor_texto'];
            } else {
                $config[$row['clave']] = floatval($row['valor_numero']);
            }
        }
        Flight::json($config);
    }

    public static function getReporte()
    {
        $db = Flight::db();
        $fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-01');
        $fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');

        $sentence = $db->prepare("SELECT ra.id, ra.id_colaborador, ra.fecha,
            CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.segundo_nombre,''),' ',IFNULL(p.primer_apellido,''),' ',IFNULL(p.segundo_apellido,'')) AS nombre_colaborador,
            tra.nombre nombre_tipo, tra.codigo codigo_tipo, tra.orden,
            ra.hora_registro, ra.fecha_registro_real,
            ra.id_estado, era.nombre nombre_estado, era.codigo codigo_estado,
            ra.registro_manual, ra.observaciones,
            DAYNAME(ra.fecha) AS nombre_dia,
            ra.huella_dispositivo, ra.user_agent
            FROM registros_asistencia_colaboradores ra
            INNER JOIN tipos_registro_asistencia tra ON ra.id_tipo_registro = tra.id
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            INNER JOIN colaboradores c ON ra.id_colaborador = c.id
            INNER JOIN personas p ON c.id_persona = p.id
            WHERE ra.fecha BETWEEN :fecha_desde AND :fecha_hasta
            AND ra.id_tenant = :id_tenant
            ORDER BY ra.fecha DESC, nombre_colaborador, ra.hora_registro ASC");
        $sentence->bindParam(':fecha_desde', $fecha_desde);
        $sentence->bindParam(':fecha_hasta', $fecha_hasta);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $registros = $sentence->fetchAll();

        // Detectar cambio de dispositivo comparando con el registro anterior de cada colaborador
        $huellaAnterior = [];
        foreach ($registros as &$reg) {
            $idCol = $reg['id_colaborador'];
            if (isset($huellaAnterior[$idCol]) && $reg['huella_dispositivo']) {
                $reg['cambio_dispositivo'] = ($huellaAnterior[$idCol] !== $reg['huella_dispositivo']) ? 1 : 0;
            } else {
                $reg['cambio_dispositivo'] = 0;
            }
            if ($reg['huella_dispositivo']) {
                $huellaAnterior[$idCol] = $reg['huella_dispositivo'];
            }
            $reg['dispositivo_de'] = null;
        }
        unset($reg);

        // Detectar dispositivo prestado: buscar si la huella pertenece a otro colaborador
        $huellas = [];
        foreach ($registros as $reg) {
            if ($reg['huella_dispositivo']) {
                $huellas[] = $reg['huella_dispositivo'];
            }
        }
        $huellas = array_unique($huellas);

        if (count($huellas) > 0) {
            // Obtener el dueño habitual de cada huella (el colaborador con más registros con esa huella)
            $placeholders = implode(',', array_fill(0, count($huellas), '?'));
            $duenos = $db->prepare("SELECT ra.huella_dispositivo,
                ra.id_colaborador,
                CONCAT(IFNULL(p.primer_nombre,''),' ',IFNULL(p.primer_apellido,'')) AS nombre_dueno,
                COUNT(*) AS total
                FROM registros_asistencia_colaboradores ra
                INNER JOIN colaboradores c ON ra.id_colaborador = c.id
                INNER JOIN personas p ON c.id_persona = p.id
                WHERE ra.huella_dispositivo IN ($placeholders)
                AND ra.huella_dispositivo IS NOT NULL
                AND ra.id_tenant = ?
                GROUP BY ra.huella_dispositivo, ra.id_colaborador, p.primer_nombre, p.primer_apellido
                ORDER BY ra.huella_dispositivo, total DESC");
            $duenos->execute(array_merge(array_values($huellas), [TenantContext::id()]));
            $resultDuenos = $duenos->fetchAll();

            // Mapear: huella -> colaborador con más registros (dueño habitual)
            $mapaDuenos = [];
            foreach ($resultDuenos as $d) {
                $h = $d['huella_dispositivo'];
                if (!isset($mapaDuenos[$h])) {
                    $mapaDuenos[$h] = ['id_colaborador' => $d['id_colaborador'], 'nombre' => $d['nombre_dueno']];
                }
            }

            // Marcar registros donde la huella pertenece a otro colaborador
            foreach ($registros as &$reg) {
                if ($reg['huella_dispositivo'] && isset($mapaDuenos[$reg['huella_dispositivo']])) {
                    $dueno = $mapaDuenos[$reg['huella_dispositivo']];
                    if ($dueno['id_colaborador'] != $reg['id_colaborador']) {
                        $reg['dispositivo_de'] = $dueno['nombre'];
                    }
                }
            }
            unset($reg);
        }

        // Estadísticas
        $stats = $db->prepare("SELECT
            COUNT(DISTINCT CONCAT(ra.id_colaborador, '-', ra.fecha)) AS total_registros_dia,
            COUNT(CASE WHEN era.codigo = 'a_tiempo' THEN 1 END) AS a_tiempo,
            COUNT(CASE WHEN era.codigo = 'tarde' THEN 1 END) AS tarde,
            COUNT(CASE WHEN era.codigo = 'entrada_anticipada' THEN 1 END) AS entrada_anticipada,
            COUNT(CASE WHEN ra.registro_manual = 1 THEN 1 END) AS manuales
            FROM registros_asistencia_colaboradores ra
            LEFT JOIN estados_registro_asistencia era ON ra.id_estado = era.id
            WHERE ra.fecha BETWEEN :fecha_desde AND :fecha_hasta
            AND ra.id_tenant = :id_tenant");
        $stats->bindParam(':fecha_desde', $fecha_desde);
        $stats->bindParam(':fecha_hasta', $fecha_hasta);
        $stats->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stats->execute();
        $estadisticas = $stats->fetch();

        Flight::json(array('registros' => $registros, 'estadisticas' => $estadisticas));
    }

    public static function registrar()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $id_colaborador = Flight::request()->data['id_colaborador'];
            $id_tipo_registro = Flight::request()->data['id_tipo_registro'];
            $latitud = isset(Flight::request()->data['latitud']) ? Flight::request()->data['latitud'] : null;
            $longitud = isset(Flight::request()->data['longitud']) ? Flight::request()->data['longitud'] : null;
            $distancia_metros = isset(Flight::request()->data['distancia_metros']) ? Flight::request()->data['distancia_metros'] : null;
            $dentro_rango = isset(Flight::request()->data['dentro_rango']) ? Flight::request()->data['dentro_rango'] : 0;
            $id_estado = isset(Flight::request()->data['id_estado']) ? Flight::request()->data['id_estado'] : null;
            $registro_manual = isset(Flight::request()->data['registro_manual']) ? Flight::request()->data['registro_manual'] : 0;
            $fecha_registro_real = isset(Flight::request()->data['fecha_registro_real']) && Flight::request()->data['fecha_registro_real'] ? Flight::request()->data['fecha_registro_real'] : null;
            $id_autorizado_por = isset(Flight::request()->data['id_autorizado_por']) ? Flight::request()->data['id_autorizado_por'] : null;
            $fecha_autorizacion = isset(Flight::request()->data['fecha_autorizacion']) ? Flight::request()->data['fecha_autorizacion'] : null;
            $observaciones = isset(Flight::request()->data['observaciones']) ? Flight::request()->data['observaciones'] : null;

            // Huella del dispositivo
            $user_agent = isset(Flight::request()->data['user_agent']) ? Flight::request()->data['user_agent'] : null;
            $huella_dispositivo = isset(Flight::request()->data['huella_dispositivo']) ? Flight::request()->data['huella_dispositivo'] : null;
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
            $id_usuario = isset(Flight::request()->data['id_usuario']) ? Flight::request()->data['id_usuario'] : null;

            $fecha = date('Y-m-d');
            $hora_registro = date('H:i:s');

            // Validar hora en registros manuales: no puede ser anterior al último registro del día
            if ($registro_manual == 1 && $fecha_registro_real) {
                $horaManual = date('H:i:s', strtotime($fecha_registro_real));
                $fechaManual = date('Y-m-d', strtotime($fecha_registro_real));

                $ultimoReg = $db->prepare("SELECT hora_registro, fecha_registro_real FROM registros_asistencia_colaboradores WHERE id_colaborador = :id_colaborador AND fecha = :fecha AND id_tenant = :id_tenant ORDER BY hora_registro DESC LIMIT 1");
                $ultimoReg->bindParam(':id_colaborador', $id_colaborador);
                $ultimoReg->bindParam(':fecha', $fechaManual);
                $ultimoReg->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $ultimoReg->execute();
                $ultimo = $ultimoReg->fetch();

                if ($ultimo) {
                    $horaUltimo = $ultimo['fecha_registro_real'] ? date('H:i:s', strtotime($ultimo['fecha_registro_real'])) : $ultimo['hora_registro'];
                    if ($horaManual < $horaUltimo) {
                        $db->rollBack();
                        Flight::json(array('error' => 'La hora del registro manual (' . substr($horaManual, 0, 5) . ') no puede ser anterior al último registro del día (' . substr($horaUltimo, 0, 5) . ')'), 400);
                        return;
                    }
                }

                $fecha = $fechaManual;
            }

            $idReg = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO registros_asistencia_colaboradores (id, id_tenant, id_colaborador, fecha, id_tipo_registro, hora_registro, fecha_registro_real, latitud, longitud, distancia_metros, dentro_rango, id_estado, registro_manual, id_autorizado_por, fecha_autorizacion, observaciones, ip_address, user_agent, huella_dispositivo, id_usuario) VALUES (:id, :id_tenant, :id_colaborador, :fecha, :id_tipo_registro, :hora_registro, :fecha_registro_real, :latitud, :longitud, :distancia_metros, :dentro_rango, :id_estado, :registro_manual, :id_autorizado_por, :fecha_autorizacion, :observaciones, :ip_address, :user_agent, :huella_dispositivo, :id_usuario)");
            $sentence->bindValue(':id', $idReg);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_colaborador', $id_colaborador);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindParam(':id_tipo_registro', $id_tipo_registro);
            $sentence->bindParam(':hora_registro', $hora_registro);
            $sentence->bindParam(':fecha_registro_real', $fecha_registro_real);
            $sentence->bindParam(':latitud', $latitud);
            $sentence->bindParam(':longitud', $longitud);
            $sentence->bindParam(':distancia_metros', $distancia_metros);
            $sentence->bindParam(':dentro_rango', $dentro_rango);
            $sentence->bindParam(':id_estado', $id_estado);
            $sentence->bindParam(':registro_manual', $registro_manual);
            $sentence->bindParam(':id_autorizado_por', $id_autorizado_por);
            $sentence->bindParam(':fecha_autorizacion', $fecha_autorizacion);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':ip_address', $ip_address);
            $sentence->bindParam(':user_agent', $user_agent);
            $sentence->bindParam(':huella_dispositivo', $huella_dispositivo);
            $sentence->bindParam(':id_usuario', $id_usuario);
            $sentence->execute();

            $db->commit();
            Flight::json(array('id' => $idReg, 'message' => 'Registro guardado correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en registrar asistencia: " . $e->getMessage());
            Flight::json(array('error' => 'Error al guardar el registro'), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        try {
            $id = Flight::request()->data['id'];

            $check = $db->prepare("SELECT id FROM registros_asistencia_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $check->bindParam(':id', $id);
            $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $check->execute();
            if (!$check->fetch()) {
                Flight::json(array('error' => 'Registro no encontrado'), 404);
                return;
            }

            $sentence = $db->prepare("DELETE FROM registros_asistencia_colaboradores WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id, 'message' => 'Registro eliminado correctamente'));
        } catch (Exception $e) {
            error_log("Error en eliminar registro asistencia: " . $e->getMessage());
            Flight::json(array('error' => 'Error al eliminar el registro'), 500);
        }
    }
}