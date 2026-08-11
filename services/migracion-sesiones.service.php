<?php
/**
 * Tabla migracion_sesiones.
 *
 * Una sesion por cliente en montaje. Cuelga del cliente ya registrado en
 * el Admin, con su contrato firmado: eso amarra el proceso comercial con
 * el tecnico.
 *
 * Aqui vive tambien el cambio de destino (pruebas -> produccion) y la
 * purga, porque las dos cosas son del ciclo de vida de la sesion.
 */
class MigracionSesiones
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT s.id, s.id_cliente, s.nombre_cliente, s.codigo_tenant_destino,
                                             s.id_tenant_destino, s.anno, s.estado, s.nombre_usuario,
                                             s.fecha_creacion, s.fecha_validacion,
                                             c.nombre AS nombre_conexion, c.ambiente, c.base_datos,
                                             (SELECT COUNT(*) FROM migracion_archivos a WHERE a.id_sesion = s.id) AS total_archivos,
                                             (SELECT COUNT(*) FROM migracion_bloques b WHERE b.id_sesion = s.id AND b.estado IN ('ejecutado','validado')) AS bloques_listos,
                                             (SELECT COUNT(*) FROM migracion_bloques b2 WHERE b2.id_sesion = s.id) AS total_bloques
                                      FROM migracion_sesiones s
                                      LEFT JOIN migracion_conexiones c ON s.id_conexion = c.id
                                      WHERE s.id_tenant = :id_tenant
                                      ORDER BY s.fecha_creacion DESC");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionSesiones::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT s.*, c.nombre AS nombre_conexion, c.ambiente, c.base_datos, c.host
                                      FROM migracion_sesiones s
                                      LEFT JOIN migracion_conexiones c ON s.id_conexion = c.id
                                      WHERE s.id = :id AND s.id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionSesiones::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * La sesion con todo lo que la pantalla de detalle necesita para
     * pintarse de una: bloques, archivos, preguntas abiertas y el resumen
     * del expediente.
     */
    public static function getDetalle($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sesion = self::obtener($id);

            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }

            $sentence = $db->prepare("SELECT c.nombre AS nombre_conexion, c.ambiente, c.base_datos, c.host
                                      FROM migracion_conexiones c WHERE c.id = :id_conexion");
            $sentence->bindValue(':id_conexion', $sesion['id_conexion']);
            $sentence->execute();
            $conexion = $sentence->fetch(PDO::FETCH_ASSOC);
            if ($conexion) {
                $sesion = array_merge($sesion, $conexion);
            }

            $sentence = $db->prepare("SELECT id, codigo, nombre, orden, estado
                                      FROM migracion_bloques
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant
                                      ORDER BY orden");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $sesion['bloques'] = $sentence->fetchAll();

            $sentence = $db->prepare("SELECT id, nombre_original, extension, tamano, tipo_detectado, estado, fecha_carga
                                      FROM migracion_archivos
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant
                                      ORDER BY fecha_carga");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $sesion['archivos'] = $sentence->fetchAll();

            $sentence = $db->prepare("SELECT id, pregunta, contexto, estado, fecha_creacion
                                      FROM migracion_preguntas
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant AND estado = 'abierta'
                                      ORDER BY fecha_creacion");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $sesion['preguntas_abiertas'] = $sentence->fetchAll();

            $sentence = $db->prepare("SELECT tabla_destino, estado, COUNT(*) AS total
                                      FROM migracion_registros
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant
                                      GROUP BY tabla_destino, estado
                                      ORDER BY tabla_destino");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $sesion['resumen_registros'] = $sentence->fetchAll();

            Flight::json($sesion);
        } catch (Exception $e) {
            error_log("MigracionSesiones::getDetalle - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function new()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $data = Flight::request()->data;

            $id_cliente = isset($data['id_cliente']) ? $data['id_cliente'] : null;
            $nombre_cliente = isset($data['nombre_cliente']) ? trim($data['nombre_cliente']) : '';
            $codigo_tenant_destino = isset($data['codigo_tenant_destino']) ? trim($data['codigo_tenant_destino']) : '';
            $id_tenant_destino = isset($data['id_tenant_destino']) && $data['id_tenant_destino'] !== ''
                ? (int)$data['id_tenant_destino'] : null;
            $anno = isset($data['anno']) && $data['anno'] ? (int)$data['anno'] : (int)date('Y');
            $id_conexion = isset($data['id_conexion']) ? $data['id_conexion'] : null;
            $id_conexion_semilla = isset($data['id_conexion_semilla']) ? $data['id_conexion_semilla'] : null;
            $notas = isset($data['notas']) ? $data['notas'] : null;

            if ($nombre_cliente === '' || $codigo_tenant_destino === '') {
                Flight::json(array('error' => 'El nombre del cliente y el código del tenant destino son obligatorios'), 400);
                return;
            }
            if (!$id_conexion) {
                Flight::json(array('error' => 'Debe escoger la conexión destino'), 400);
                return;
            }

            $conexion = MigracionConexiones::obtener($id_conexion);
            if (!$conexion) {
                Flight::json(array('error' => 'La conexión destino no existe o está inactiva'), 400);
                return;
            }
            if ((int)$conexion['solo_lectura'] === 1) {
                Flight::json(array('error' => 'La conexión escogida es de solo lectura y no puede ser destino'), 400);
                return;
            }

            $db->beginTransaction();

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO migracion_sesiones
                (id, id_tenant, id_cliente, nombre_cliente, codigo_tenant_destino, id_tenant_destino, anno,
                 id_conexion, id_conexion_semilla, estado, id_usuario, nombre_usuario, notas)
                VALUES (:id, :id_tenant, :id_cliente, :nombre_cliente, :codigo_tenant_destino, :id_tenant_destino, :anno,
                        :id_conexion, :id_conexion_semilla, 'abierta', :id_usuario, :nombre_usuario, :notas)");
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_cliente', $id_cliente);
            $sentence->bindParam(':nombre_cliente', $nombre_cliente);
            $sentence->bindParam(':codigo_tenant_destino', $codigo_tenant_destino);
            $sentence->bindValue(':id_tenant_destino', $id_tenant_destino, $id_tenant_destino === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindValue(':anno', $anno, PDO::PARAM_INT);
            $sentence->bindValue(':id_conexion', $id_conexion);
            $sentence->bindValue(':id_conexion_semilla', $id_conexion_semilla);
            $sentence->bindValue(':id_usuario', isset($userData->id) ? $userData->id : null);
            $sentence->bindValue(':nombre_usuario', isset($userData->usuario) ? $userData->usuario : null);
            $sentence->bindValue(':notas', $notas);
            $sentence->execute();

            MigracionBloques::sembrar($db, $idNew);

            $db->commit();

            $id = $idNew;
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("MigracionSesiones::new - " . $e->getMessage());
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

            $sesion = self::obtener($id);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }
            if (!self::estaAbierta($sesion)) {
                Flight::json(array('error' => 'La sesión está en estado "' . $sesion['estado'] . '" y ya no admite cambios'), 400);
                return;
            }

            $nombre_cliente = isset($data['nombre_cliente']) ? trim($data['nombre_cliente']) : $sesion['nombre_cliente'];
            $codigo_tenant_destino = isset($data['codigo_tenant_destino']) ? trim($data['codigo_tenant_destino']) : $sesion['codigo_tenant_destino'];
            $id_tenant_destino = isset($data['id_tenant_destino']) && $data['id_tenant_destino'] !== ''
                ? (int)$data['id_tenant_destino'] : null;
            $anno = isset($data['anno']) ? (int)$data['anno'] : (int)$sesion['anno'];
            $id_conexion = isset($data['id_conexion']) ? $data['id_conexion'] : $sesion['id_conexion'];
            $id_conexion_semilla = isset($data['id_conexion_semilla']) ? $data['id_conexion_semilla'] : $sesion['id_conexion_semilla'];
            $notas = isset($data['notas']) ? $data['notas'] : $sesion['notas'];

            $sentence = $db->prepare("UPDATE migracion_sesiones SET
                                        id_cliente = :id_cliente,
                                        nombre_cliente = :nombre_cliente,
                                        codigo_tenant_destino = :codigo_tenant_destino,
                                        id_tenant_destino = :id_tenant_destino,
                                        anno = :anno,
                                        id_conexion = :id_conexion,
                                        id_conexion_semilla = :id_conexion_semilla,
                                        notas = :notas
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_cliente', isset($data['id_cliente']) ? $data['id_cliente'] : $sesion['id_cliente']);
            $sentence->bindParam(':nombre_cliente', $nombre_cliente);
            $sentence->bindParam(':codigo_tenant_destino', $codigo_tenant_destino);
            $sentence->bindValue(':id_tenant_destino', $id_tenant_destino, $id_tenant_destino === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $sentence->bindValue(':anno', $anno, PDO::PARAM_INT);
            $sentence->bindValue(':id_conexion', $id_conexion);
            $sentence->bindValue(':id_conexion_semilla', $id_conexion_semilla);
            $sentence->bindValue(':notas', $notas);
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("MigracionSesiones::replace - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.administrar');

            $id = Flight::request()->data['id'];
            $sesion = self::obtener($id);

            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }
            if (in_array($sesion['estado'], array('validada', 'purgada'), true)) {
                Flight::json(array('error' => 'Una sesión validada no se borra, queda como registro histórico'), 400);
                return;
            }

            // Los archivos fisicos van primero: si falla la BD, no quedan
            // huerfanos en disco.
            MigracionArchivos::borrarCarpeta($id);

            $db->beginTransaction();

            foreach (array('migracion_registros', 'migracion_preguntas', 'migracion_mensajes',
                           'migracion_archivos', 'migracion_scripts', 'migracion_ejecuciones',
                           'migracion_bloques') as $tabla) {
                $sentence = $db->prepare("DELETE FROM {$tabla} WHERE id_sesion = :id AND id_tenant = :id_tenant");
                $sentence->bindValue(':id', $id);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
            }

            $sentence = $db->prepare("DELETE FROM migracion_sesiones WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("MigracionSesiones::delete - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Cambia la conexion destino: es como se pasa de pruebas a produccion.
     * Los scripts ya ejecutados vuelven a "aprobado" porque en el nuevo
     * destino no se ha escrito nada, y dejarlos en "ejecutado" mentiria.
     */
    public static function cambiarDestino()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.ejecutar');

            $data = Flight::request()->data;
            $id_sesion = isset($data['id_sesion']) ? $data['id_sesion'] : null;
            $id_conexion = isset($data['id_conexion']) ? $data['id_conexion'] : null;

            if (!$id_sesion || !$id_conexion) {
                Flight::json(array('error' => 'Faltan la sesión o la conexión destino'), 400);
                return;
            }

            $conexion = MigracionConexiones::obtener($id_conexion);
            if (!$conexion || (int)$conexion['solo_lectura'] === 1) {
                Flight::json(array('error' => 'La conexión no es válida como destino'), 400);
                return;
            }

            $db->beginTransaction();

            $sentence = $db->prepare("UPDATE migracion_sesiones SET id_conexion = :id_conexion, hash_esquema = NULL
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id_conexion', $id_conexion);
            $sentence->bindValue(':id', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $sentence = $db->prepare("UPDATE migracion_bloques SET estado = 'propuesto'
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant
                                        AND estado IN ('ejecutado','validado')");
            $sentence->bindValue(':id', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $sentence = $db->prepare("UPDATE migracion_scripts SET estado = 'aprobado'
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant AND estado = 'ejecutado'");
            $sentence->bindValue(':id', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'mensaje' => 'Destino cambiado a ' . $conexion['nombre'] . ' (' . $conexion['ambiente'] . '). Los scripts aprobados quedaron listos para volver a ejecutarse.'
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("MigracionSesiones::cambiarDestino - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Valida la sesion y borra los datos personales del cliente.
     *
     * La bitacora sobrevive con el detalle vaciado: asi se puede responder
     * "esto se cargo y cuando" dentro de un año, sin guardar el dato.
     */
    public static function purgar()
    {
        $db = Flight::db();
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.purgar');

            $id_sesion = isset(Flight::request()->data['id_sesion']) ? Flight::request()->data['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $sesion = self::obtener($id_sesion);
            if (!$sesion) {
                Flight::json(array('error' => 'La sesión no existe'), 404);
                return;
            }
            if ($sesion['estado'] === 'purgada') {
                Flight::json(array('error' => 'Esta sesión ya fue purgada'), 400);
                return;
            }

            $borrados = MigracionArchivos::borrarCarpeta($id_sesion);

            $db->beginTransaction();

            foreach (array('migracion_registros', 'migracion_preguntas', 'migracion_mensajes',
                           'migracion_archivos', 'migracion_scripts') as $tabla) {
                $sentence = $db->prepare("DELETE FROM {$tabla} WHERE id_sesion = :id AND id_tenant = :id_tenant");
                $sentence->bindValue(':id', $id_sesion);
                $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $sentence->execute();
            }

            $sentence = $db->prepare("UPDATE migracion_ejecuciones
                                      SET tablas_tocadas = NULL, tablas_globales = NULL, mensaje_error = NULL
                                      WHERE id_sesion = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $sentence = $db->prepare("UPDATE migracion_sesiones
                                      SET estado = 'purgada', fecha_purga = NOW(),
                                          fecha_validacion = COALESCE(fecha_validacion, NOW())
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array(
                'success' => true,
                'archivos_borrados' => $borrados,
                'mensaje' => 'Sesión validada y purgada. Queda la bitácora de lo que se cargó, sin los datos personales.'
            ));
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("MigracionSesiones::purgar - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * @return array|false
     */
    public static function obtener($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM migracion_sesiones WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    public static function estaAbierta($sesion)
    {
        return in_array($sesion['estado'], array('abierta', 'en_proceso'), true);
    }

    /**
     * Marca la sesion como en proceso cuando se ejecuta el primer script.
     */
    public static function marcarEnProceso($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("UPDATE migracion_sesiones SET estado = 'en_proceso'
                                  WHERE id = :id AND id_tenant = :id_tenant AND estado = 'abierta'");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }

    public static function guardarHashEsquema($id, $hash)
    {
        $db = Flight::db();
        $sentence = $db->prepare("UPDATE migracion_sesiones SET hash_esquema = :hash
                                  WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindValue(':hash', $hash);
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }
}
