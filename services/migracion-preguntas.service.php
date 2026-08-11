<?php
/**
 * Tabla migracion_preguntas.
 *
 * La cola de lo que el asistente necesita confirmar antes de escribir.
 * Son las dudas que salen de comparar los archivos contra los catalogos
 * reales del destino: grados escritos de diez formas, parentescos que el
 * catalogo distingue y el archivo no, etc.
 */
class MigracionPreguntas
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            $estado = isset(Flight::request()->query['estado']) ? Flight::request()->query['estado'] : null;

            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $filtroEstado = $estado ? ' AND estado = :estado' : '';

            $sentence = $db->prepare("SELECT id, id_sesion, id_bloque, pregunta, contexto, respuesta,
                                             estado, fecha_creacion, fecha_respuesta
                                      FROM migracion_preguntas
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant {$filtroEstado}
                                      ORDER BY fecha_creacion");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            if ($estado) {
                $sentence->bindParam(':estado', $estado);
            }
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionPreguntas::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, id_bloque, pregunta, contexto, respuesta,
                                             estado, fecha_creacion, fecha_respuesta
                                      FROM migracion_preguntas
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionPreguntas::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $data = Flight::request()->data;
            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $pregunta = isset($data['pregunta']) ? trim($data['pregunta']) : '';

            if (!$id_sesion || $pregunta === '') {
                Flight::json(array('error' => 'La sesión y la pregunta son obligatorias'), 400);
                return;
            }

            $id = self::registrar(
                $id_sesion,
                isset($data['id_bloque']) ? $data['id_bloque'] : null,
                $pregunta,
                isset($data['contexto']) ? $data['contexto'] : null
            );

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionPreguntas::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id = $data['id'];
            $respuesta = isset($data['respuesta']) ? $data['respuesta'] : null;
            $estado = isset($data['estado']) ? $data['estado'] : 'respondida';

            if ($estado === 'respondida' && ($respuesta === null || trim($respuesta) === '')) {
                Flight::json(array('error' => 'Falta la respuesta'), 400);
                return;
            }

            $sentence = $db->prepare("UPDATE migracion_preguntas SET
                                        respuesta = :respuesta,
                                        estado = :estado,
                                        fecha_respuesta = CASE WHEN :estado2 = 'respondida' THEN NOW() ELSE fecha_respuesta END
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':respuesta', $respuesta);
            $sentence->bindValue(':estado', $estado);
            $sentence->bindValue(':estado2', $estado);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionPreguntas::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.chat');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM migracion_preguntas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionPreguntas::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Registra una pregunta que dejo el asistente. Si ya esta abierta la
     * misma, no la repite.
     *
     * @return string|null id de la pregunta, o null si ya existia
     */
    public static function registrar($id_sesion, $id_bloque, $pregunta, $contexto = null)
    {
        $db = Flight::db();

        $sentence = $db->prepare("SELECT id FROM migracion_preguntas
                                  WHERE id_sesion = :id_sesion AND pregunta = :pregunta
                                    AND estado = 'abierta' AND id_tenant = :id_tenant");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':pregunta', $pregunta);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        if ($sentence->fetch()) {
            return null;
        }

        $idNew = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO migracion_preguntas
            (id, id_tenant, id_sesion, id_bloque, pregunta, contexto, estado)
            VALUES (:id, :id_tenant, :id_sesion, :id_bloque, :pregunta, :contexto, 'abierta')");
        $sentence->bindValue(':id', $idNew);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_bloque', $id_bloque);
        $sentence->bindValue(':pregunta', $pregunta);
        $sentence->bindValue(':contexto', $contexto);
        $sentence->execute();

        return $idNew;
    }

    /**
     * Preguntas de la sesion para el contexto del asistente: las abiertas
     * y las ya resueltas, que son las que evitan volver a preguntar.
     *
     * @return array
     */
    public static function obtenerDeSesion($id_sesion, $limite = 25)
    {
        $db = Flight::db();
        $limite = (int)$limite;
        $sentence = $db->prepare("SELECT pregunta, respuesta, estado
                                  FROM migracion_preguntas
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                  ORDER BY fecha_creacion DESC
                                  LIMIT {$limite}");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
