<?php
/**
 * Tabla migracion_registros.
 *
 * El expediente: una fila por entidad a crear, con su valor, de donde
 * salio, que tan seguro esta y en que estado va. La clave natural (tipo y
 * numero de identificacion, por ejemplo) permite reprocesar un archivo sin
 * duplicar.
 *
 * Es lo que le da continuidad a la conversacion: sin esto el asistente
 * pregunta tres veces el mismo dato.
 */
class MigracionRegistros
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            $tabla_destino = isset(Flight::request()->query['tabla_destino']) ? Flight::request()->query['tabla_destino'] : null;

            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $filtroTabla = $tabla_destino ? ' AND tabla_destino = :tabla_destino' : '';

            $sentence = $db->prepare("SELECT id, id_sesion, id_bloque, tabla_destino, clave_natural, datos_json,
                                             fuente, confianza, estado, id_destino, fecha_creacion
                                      FROM migracion_registros
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant {$filtroTabla}
                                      ORDER BY tabla_destino, clave_natural");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            if ($tabla_destino) {
                $sentence->bindParam(':tabla_destino', $tabla_destino);
            }
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionRegistros::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, id_bloque, tabla_destino, clave_natural, datos_json,
                                             fuente, confianza, estado, id_destino, fecha_creacion
                                      FROM migracion_registros
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionRegistros::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $id_bloque = isset($data['id_bloque']) ? $data['id_bloque'] : null;
            $tabla_destino = isset($data['tabla_destino']) ? trim($data['tabla_destino']) : '';
            $clave_natural = isset($data['clave_natural']) ? trim($data['clave_natural']) : '';
            $datos_json = isset($data['datos_json']) ? $data['datos_json'] : null;
            $fuente = isset($data['fuente']) ? $data['fuente'] : null;
            $confianza = isset($data['confianza']) ? $data['confianza'] : null;
            $estado = isset($data['estado']) ? $data['estado'] : 'propuesto';

            if (!$id_sesion || $tabla_destino === '' || $clave_natural === '') {
                Flight::json(array('error' => 'La sesión, la tabla destino y la clave natural son obligatorias'), 400);
                return;
            }

            if (is_array($datos_json)) {
                $datos_json = json_encode($datos_json, JSON_UNESCAPED_UNICODE);
            }

            $sentence = $db->prepare("INSERT INTO migracion_registros
                (id, id_tenant, id_sesion, id_bloque, tabla_destino, clave_natural, datos_json, fuente, confianza, estado)
                VALUES (:id, :id_tenant, :id_sesion, :id_bloque, :tabla_destino, :clave_natural, :datos_json, :fuente, :confianza, :estado)
                ON DUPLICATE KEY UPDATE
                    datos_json = :datos_json2, fuente = :fuente2, confianza = :confianza2, estado = :estado2");
            $idNew = Uuid::generar();
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_bloque', $id_bloque);
            $sentence->bindParam(':tabla_destino', $tabla_destino);
            $sentence->bindParam(':clave_natural', $clave_natural);
            $sentence->bindValue(':datos_json', $datos_json);
            $sentence->bindValue(':datos_json2', $datos_json);
            $sentence->bindValue(':fuente', $fuente);
            $sentence->bindValue(':fuente2', $fuente);
            $sentence->bindValue(':confianza', $confianza);
            $sentence->bindValue(':confianza2', $confianza);
            $sentence->bindValue(':estado', $estado);
            $sentence->bindValue(':estado2', $estado);
            $sentence->execute();
            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionRegistros::new - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $data = Flight::request()->data;

            $id = $data['id'];
            $datos_json = isset($data['datos_json']) ? $data['datos_json'] : null;
            $fuente = isset($data['fuente']) ? $data['fuente'] : null;
            $confianza = isset($data['confianza']) ? $data['confianza'] : null;
            $estado = isset($data['estado']) ? $data['estado'] : 'propuesto';
            $id_destino = isset($data['id_destino']) ? $data['id_destino'] : null;

            if (is_array($datos_json)) {
                $datos_json = json_encode($datos_json, JSON_UNESCAPED_UNICODE);
            }

            $sentence = $db->prepare("UPDATE migracion_registros SET
                                        datos_json = :datos_json,
                                        fuente = :fuente,
                                        confianza = :confianza,
                                        estado = :estado,
                                        id_destino = :id_destino
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':datos_json', $datos_json);
            $sentence->bindValue(':fuente', $fuente);
            $sentence->bindValue(':confianza', $confianza);
            $sentence->bindValue(':estado', $estado);
            $sentence->bindValue(':id_destino', $id_destino);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionRegistros::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'];

            $sentence = $db->prepare("DELETE FROM migracion_registros WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("MigracionRegistros::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Conteo por tabla y estado, para el contexto del asistente.
     *
     * @return array
     */
    public static function resumen($id_sesion)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT tabla_destino, estado, COUNT(*) AS total
                                  FROM migracion_registros
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                  GROUP BY tabla_destino, estado
                                  ORDER BY tabla_destino");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
