<?php 
class Calendarios
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, fecha, id_tipo_dia, dia, mes, anio, id_dia_semana, dia_habil from calendarios");
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, fecha, id_tipo_dia, dia, mes, anio, id_dia_semana, dia_habil from calendarios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getDiasHabiles($fecha_inicial, $fecha_final)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana,
                td.nombre as tipo_dia_nombre,
                ds.nombre as dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.fecha BETWEEN :fecha_inicial AND :fecha_final
                AND c.id_tipo_dia = 1
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicial', $fecha_inicial);
        $sentence->bindParam(':fecha_final', $fecha_final);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByRangoFechas($fecha_inicial, $fecha_final)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana,
                td.nombre as tipo_dia_nombre,
                ds.nombre as dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.fecha BETWEEN :fecha_inicial AND :fecha_final
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicial', $fecha_inicial);
        $sentence->bindParam(':fecha_final', $fecha_final);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Endpoint para el portal de padres.
     * Devuelve días del mes, eventos y cumpleaños al vuelo.
     */
    public static function getCalendarioMes($anio, $mes)
    {
        $db = Flight::db();

        // 1. Días del mes
        $stmtDias = $db->prepare("
            SELECT 
                c.id, c.fecha, c.id_tipo_dia, c.dia, c.mes, c.anio, c.id_dia_semana,
                td.nombre AS tipo_dia_nombre,
                ds.nombre AS dia_semana_nombre
            FROM calendarios c
            LEFT JOIN tipos_dias td ON c.id_tipo_dia = td.id
            LEFT JOIN dias_semana ds ON c.id_dia_semana = ds.id
            WHERE c.anio = :anio AND c.mes = :mes
            ORDER BY c.dia
        ");
        $stmtDias->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmtDias->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmtDias->execute();
        $dias = $stmtDias->fetchAll();

        // 2. Eventos del mes
        $fecha_inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fecha_fin = date('Y-m-t', strtotime($fecha_inicio));

        $stmtEventos = $db->prepare("
            SELECT 
                ce.id, ce.fecha, ce.id_tipo_evento_calendario, ce.descripcion,
                tec.nombre AS tipo_evento_nombre,
                tec.icono AS tipo_evento_icono
            FROM calendarios_eventos ce
            LEFT JOIN tipos_evento_calendario tec ON tec.id = ce.id_tipo_evento_calendario
            WHERE ce.fecha BETWEEN :fecha_inicio AND :fecha_fin
            AND ce.id_tenant = :id_tenant
            ORDER BY ce.fecha
        ");
        $stmtEventos->bindParam(':fecha_inicio', $fecha_inicio);
        $stmtEventos->bindParam(':fecha_fin', $fecha_fin);
        $stmtEventos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtEventos->execute();
        $eventos = $stmtEventos->fetchAll();

        // 3. Cumpleaños de estudiantes activos
        $stmtCumpleEstudiantes = $db->prepare("
            SELECT 
                p.id AS id_persona,
                p.primer_nombre,
                p.primer_apellido,
                p.fecha_nacimiento,
                'estudiante' AS tipo_persona,
                NULL AS sobrenombre,
                NULL AS cargo_nombre_corto
            FROM personas p
            INNER JOIN estudiantes e ON e.id_persona = p.id AND e.activo = 1
            WHERE MONTH(p.fecha_nacimiento) = :mes
            AND e.id_tenant = :id_tenant
            ORDER BY DAY(p.fecha_nacimiento)
        ");
        $stmtCumpleEstudiantes->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmtCumpleEstudiantes->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtCumpleEstudiantes->execute();
        $cumpleEstudiantes = $stmtCumpleEstudiantes->fetchAll();

        // 4. Cumpleaños de colaboradores activos (con sobrenombre y cargo)
        $stmtCumpleColaboradores = $db->prepare("
            SELECT 
                p.id AS id_persona,
                p.primer_nombre,
                p.primer_apellido,
                p.fecha_nacimiento,
                'colaborador' AS tipo_persona,
                col.sobrenombre,
                ca.nombre_corto AS cargo_nombre_corto
            FROM personas p
            INNER JOIN colaboradores col ON col.id_persona = p.id AND col.activo = 1
            LEFT JOIN cargos ca ON ca.id = col.id_cargo
            WHERE MONTH(p.fecha_nacimiento) = :mes
            AND col.id_tenant = :id_tenant
            ORDER BY DAY(p.fecha_nacimiento)
        ");
        $stmtCumpleColaboradores->bindParam(':mes', $mes, PDO::PARAM_INT);
        $stmtCumpleColaboradores->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $stmtCumpleColaboradores->execute();
        $cumpleColaboradores = $stmtCumpleColaboradores->fetchAll();

        // Formatear cumpleaños
        $cumpleanos = [];
        foreach (array_merge($cumpleEstudiantes, $cumpleColaboradores) as $c) {
            $diaCumple = (int) date('d', strtotime($c['fecha_nacimiento']));
            
            // Para colaboradores: usar sobrenombre si existe, si no primer_nombre
            if ($c['tipo_persona'] === 'colaborador') {
                $nombre = !empty($c['sobrenombre']) ? $c['sobrenombre'] : trim($c['primer_nombre'] . ' ' . $c['primer_apellido']);
            } else {
                $nombre = trim($c['primer_nombre'] . ' ' . $c['primer_apellido']);
            }

            $cumpleanos[] = [
                'id_persona' => $c['id_persona'],
                'nombre' => $nombre,
                'tipo_persona' => $c['tipo_persona'],
                'dia' => $diaCumple,
                'fecha_nacimiento' => $c['fecha_nacimiento'],
                'cargo' => $c['cargo_nombre_corto']
            ];
        }

        // Ordenar cumpleaños por día
        usort($cumpleanos, function($a, $b) {
            return $a['dia'] - $b['dia'];
        });

        Flight::json([
            'anio' => (int) $anio,
            'mes' => (int) $mes,
            'dias' => $dias,
            'eventos' => $eventos,
            'cumpleanos' => $cumpleanos
        ]);
    }

    private static function convertirDiaSemana($dia_php)
    {
        if ($dia_php == 0) {
            return 7;
        } else {
            return $dia_php;
        }
    }

    public static function new()
    {
        $db = Flight::db();
        $fecha = Flight::request()->data['fecha'];
        $id_tipo_dia = Flight::request()->data['id_tipo_dia'];
        
        $fecha_obj = new DateTime($fecha);
        $dia = (int)$fecha_obj->format('d');
        $mes = (int)$fecha_obj->format('m');
        $anio = (int)$fecha_obj->format('Y');
        $dia_semana_php = (int)$fecha_obj->format('w');
        $id_dia_semana = self::convertirDiaSemana($dia_semana_php);
        
        $sentence = $db->prepare("insert into calendarios(fecha, id_tipo_dia, dia, mes, anio, id_dia_semana) values (:fecha, :id_tipo_dia, :dia, :mes, :anio, :id_dia_semana)");
        
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_tipo_dia', $id_tipo_dia);
        $sentence->bindParam(':dia', $dia);
        $sentence->bindParam(':mes', $mes);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        
        $sentence->execute();
        $id = $db->lastInsertId();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $fecha = Flight::request()->data['fecha'];
        $id_tipo_dia = Flight::request()->data['id_tipo_dia'];
        
        $fecha_obj = new DateTime($fecha);
        $dia = (int)$fecha_obj->format('d');
        $mes = (int)$fecha_obj->format('m');
        $anio = (int)$fecha_obj->format('Y');
        $dia_semana_php = (int)$fecha_obj->format('w');
        $id_dia_semana = self::convertirDiaSemana($dia_semana_php);
        
        $sentence = $db->prepare("update calendarios set fecha = :fecha, id_tipo_dia = :id_tipo_dia, dia = :dia, mes = :mes, anio = :anio, id_dia_semana = :id_dia_semana where id = :id");
        
        $sentence->bindParam(':id', $id);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':id_tipo_dia', $id_tipo_dia);
        $sentence->bindParam(':dia', $dia);
        $sentence->bindParam(':mes', $mes);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindParam(':id_dia_semana', $id_dia_semana);
        
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from calendarios where id = :id");
        $sentence->bindParam(':id', $id);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    // =============================================
    // MÉTODOS DE CÁLCULO DE TIEMPO HÁBIL
    // Uso interno, no son endpoints
    // =============================================

    /**
     * Carga el calendario hábil con horarios para un rango de fechas.
     * Se llama UNA vez y el resultado se pasa a los métodos de cálculo.
     * Retorna array indexado por fecha: ['2025-01-02' => ['hora_entrada' => '08:00:00', 'hora_salida' => '18:00:00'], ...]
     */
    public static function obtenerCalendarioHabil($fechaInicio, $fechaFin, $db)
    {
        $sentence = $db->prepare("
            SELECT 
                c.fecha,
                ds.hora_entrada,
                ds.hora_salida
            FROM calendarios c
            INNER JOIN dias_semana ds ON ds.id = c.id_dia_semana
            WHERE c.dia_habil = 1
              AND c.fecha BETWEEN :fecha_inicio AND :fecha_fin
            ORDER BY c.fecha
        ");
        $sentence->bindParam(':fecha_inicio', $fechaInicio);
        $sentence->bindParam(':fecha_fin', $fechaFin);
        $sentence->execute();
        $rows = $sentence->fetchAll(PDO::FETCH_ASSOC);

        $calendario = [];
        foreach ($rows as $row) {
            $calendario[$row['fecha']] = [
                'hora_entrada' => $row['hora_entrada'],
                'hora_salida' => $row['hora_salida']
            ];
        }

        return $calendario;
    }

    /**
     * Calcula días hábiles completos entre dos fechas.
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return int cantidad de días hábiles
     */
    public static function calcularDiasHabiles2($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $diaInicio = substr($fechaInicio, 0, 10);
        $diaFin = substr($fechaFin, 0, 10);

        $dias = 0;
        foreach ($calendarioHabil as $fecha => $horario) {
            if ($fecha >= $diaInicio && $fecha <= $diaFin) {
                $dias++;
            }
        }

        return $dias;
    }

    /**
     * Calcula horas hábiles entre dos fechas/horas.
     * Considera horas parciales del primer y último día.
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return float horas hábiles con decimales
     */
    public static function calcularHorasHabiles($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $dtInicio = new DateTime($fechaInicio);
        $dtFin = new DateTime($fechaFin);

        if ($dtFin <= $dtInicio) {
            return 0.0;
        }

        $diaInicio = $dtInicio->format('Y-m-d');
        $diaFin = $dtFin->format('Y-m-d');

        $totalHoras = 0.0;

        foreach ($calendarioHabil as $fecha => $horario) {
            if ($fecha < $diaInicio || $fecha > $diaFin) {
                continue;
            }

            $jornadaInicio = new DateTime($fecha . ' ' . $horario['hora_entrada']);
            $jornadaFin = new DateTime($fecha . ' ' . $horario['hora_salida']);

            // Determinar inicio efectivo para este día
            if ($fecha === $diaInicio) {
                // Primer día: contar desde la hora del registro o desde hora_entrada (lo que sea mayor)
                $efectivoInicio = max($dtInicio, $jornadaInicio);
            } else {
                $efectivoInicio = $jornadaInicio;
            }

            // Determinar fin efectivo para este día
            if ($fecha === $diaFin) {
                // Último día: contar hasta la hora actual o hasta hora_salida (lo que sea menor)
                $efectivoFin = min($dtFin, $jornadaFin);
            } else {
                $efectivoFin = $jornadaFin;
            }

            // Solo sumar si hay tiempo efectivo
            if ($efectivoFin > $efectivoInicio) {
                $diff = $efectivoInicio->diff($efectivoFin);
                $horas = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
                // Si hay días en el diff (no debería pasar pero por seguridad)
                $horas += $diff->days * 24;
                $totalHoras += $horas;
            }
        }

        return round($totalHoras, 2);
    }

    /**
     * Calcula tiempo hábil y devuelve texto legible: "2d 5h", "0d 3h", "5d 0h"
     * @param string $fechaInicio formato 'Y-m-d H:i:s'
     * @param string $fechaFin formato 'Y-m-d H:i:s'
     * @param array $calendarioHabil resultado de obtenerCalendarioHabil()
     * @return array ['texto' => '2d 5h', 'total_horas' => 25.5, 'dias' => 2, 'horas' => 5]
     */
    public static function calcularTiempoHabil($fechaInicio, $fechaFin, $calendarioHabil)
    {
        $totalHoras = self::calcularHorasHabiles($fechaInicio, $fechaFin, $calendarioHabil);

        // Calcular horas de jornada estándar (Lunes-Viernes = 10h)
        $horasJornada = 10;
        $dias = floor($totalHoras / $horasJornada);
        $horasRestantes = floor($totalHoras - ($dias * $horasJornada));

        $texto = $dias . 'd ' . $horasRestantes . 'h';

        return [
            'texto' => $texto,
            'total_horas' => $totalHoras,
            'dias' => (int) $dias,
            'horas' => (int) $horasRestantes
        ];
    }
}