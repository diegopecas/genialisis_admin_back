<?php
class HorariosColaboradores
{
    public static function getByColaborador($id_colaborador)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, id_colaborador, dia_semana, hora_entrada, hora_salida, hora_inicio_descanso, hora_fin_descanso, activo
            FROM horarios_colaboradores
            WHERE id_colaborador = :id_colaborador AND id_tenant = :id_tenant
            ORDER BY dia_semana");
        $sentence->bindParam(':id_colaborador', $id_colaborador);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function guardarTodos()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $id_colaborador = Flight::request()->data['id_colaborador'];
            $horarios = Flight::request()->data['horarios'];

            if (!$id_colaborador || !is_array($horarios)) {
                $db->rollBack();
                Flight::json(array('error' => 'Datos incompletos'), 400);
                return;
            }

            foreach ($horarios as $horario) {
                $dia_semana = $horario['dia_semana'];
                $hora_entrada = $horario['hora_entrada'];
                $hora_salida = $horario['hora_salida'];
                $hora_inicio_descanso = isset($horario['hora_inicio_descanso']) && $horario['hora_inicio_descanso'] ? $horario['hora_inicio_descanso'] : null;
                $hora_fin_descanso = isset($horario['hora_fin_descanso']) && $horario['hora_fin_descanso'] ? $horario['hora_fin_descanso'] : null;
                $activo = isset($horario['activo']) ? $horario['activo'] : 1;

                $check = $db->prepare("SELECT id FROM horarios_colaboradores WHERE id_colaborador = :id_colaborador AND dia_semana = :dia_semana AND id_tenant = :id_tenant");
                $check->bindParam(':id_colaborador', $id_colaborador);
                $check->bindParam(':dia_semana', $dia_semana);
                $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $check->execute();
                $existe = $check->fetch();

                if ($existe) {
                    $stmt = $db->prepare("UPDATE horarios_colaboradores SET hora_entrada = :hora_entrada, hora_salida = :hora_salida, hora_inicio_descanso = :hora_inicio_descanso, hora_fin_descanso = :hora_fin_descanso, activo = :activo WHERE id = :id AND id_tenant = :id_tenant");
                    $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmt->bindParam(':hora_entrada', $hora_entrada);
                    $stmt->bindParam(':hora_salida', $hora_salida);
                    $stmt->bindParam(':hora_inicio_descanso', $hora_inicio_descanso);
                    $stmt->bindParam(':hora_fin_descanso', $hora_fin_descanso);
                    $stmt->bindParam(':activo', $activo);
                    $stmt->bindParam(':id', $existe['id']);
                    $stmt->execute();
                } else {
                    $stmt = $db->prepare("INSERT INTO horarios_colaboradores (id_tenant, id_colaborador, dia_semana, hora_entrada, hora_salida, hora_inicio_descanso, hora_fin_descanso, activo) VALUES (:id_tenant, :id_colaborador, :dia_semana, :hora_entrada, :hora_salida, :hora_inicio_descanso, :hora_fin_descanso, :activo)");
                    $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmt->bindParam(':id_colaborador', $id_colaborador);
                    $stmt->bindParam(':dia_semana', $dia_semana);
                    $stmt->bindParam(':hora_entrada', $hora_entrada);
                    $stmt->bindParam(':hora_salida', $hora_salida);
                    $stmt->bindParam(':hora_inicio_descanso', $hora_inicio_descanso);
                    $stmt->bindParam(':hora_fin_descanso', $hora_fin_descanso);
                    $stmt->bindParam(':activo', $activo);
                    $stmt->execute();
                }
            }

            $db->commit();
            Flight::json(array('message' => 'Horarios guardados correctamente'));
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error en guardarTodos horarios: " . $e->getMessage());
            Flight::json(array('error' => 'Error al guardar los horarios'), 500);
        }
    }
}