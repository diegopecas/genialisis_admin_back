<?php
/**
 * Tabla migracion_ejecuciones.
 *
 * La bitacora de lo que se ejecuto contra el destino, y el deshacer.
 *
 * Sobrevive a la purga con el detalle vaciado: queda endpoint, base, fecha
 * y resultado, sin el dato personal.
 *
 * El deshacer se apoya en que el tenant nace vacio: borrar por id_tenant
 * las tablas que toco el bloque, en orden inverso al de escritura. No se
 * repara, se borra y se vuelve a sembrar.
 */
class MigracionEjecuciones
{
    public static function getAll()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $id_sesion = isset(Flight::request()->query['id_sesion']) ? Flight::request()->query['id_sesion'] : null;
            if (!$id_sesion) {
                Flight::json(array('error' => 'Falta el id de la sesión'), 400);
                return;
            }

            $db = Flight::db();
            $sentence = $db->prepare("SELECT id, id_sesion, id_script, codigo_bloque, codigo_tenant_destino,
                                             id_tenant_destino, base_destino, ambiente, sentencias, filas_afectadas,
                                             tablas_tocadas, tablas_globales, exito, mensaje_error, deshecho,
                                             fecha_deshecho, nombre_usuario, fecha
                                      FROM migracion_ejecuciones
                                      WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                      ORDER BY fecha DESC");
            $sentence->bindParam(':id_sesion', $id_sesion);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionEjecuciones::getAll - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function getById($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.listado');

            $db = Flight::db();
            $sentence = $db->prepare("SELECT * FROM migracion_ejecuciones
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $response = $sentence->fetchAll();
            Flight::json($response);
        } catch (Exception $e) {
            error_log("MigracionEjecuciones::getById - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Deshace lo que escribio una ejecucion.
     *
     * Los bloques anteriores ya validados no se tocan: se borra solo lo de
     * esta ejecucion, se corrige y se vuelve a correr ese pedazo.
     */
    public static function deshacer()
    {
        $destino = null;

        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'migracion.deshacer');

            $id = isset(Flight::request()->data['id']) ? Flight::request()->data['id'] : null;
            if (!$id) {
                Flight::json(array('error' => 'Falta el id de la ejecución a deshacer'), 400);
                return;
            }

            $ejecucion = self::obtener($id);
            if (!$ejecucion) {
                Flight::json(array('error' => 'La ejecución no existe'), 404);
                return;
            }
            if ((int)$ejecucion['deshecho'] === 1) {
                Flight::json(array('error' => 'Esta ejecución ya se deshizo'), 400);
                return;
            }
            if ((int)$ejecucion['exito'] !== 1) {
                Flight::json(array('error' => 'Esta ejecución falló; no hay nada que deshacer'), 400);
                return;
            }
            if (empty($ejecucion['tablas_tocadas'])) {
                Flight::json(array('error' => 'La bitácora de esta ejecución ya fue purgada; no se puede deshacer automáticamente'), 400);
                return;
            }

            $idTenantDestino = (int)$ejecucion['id_tenant_destino'];
            if ($idTenantDestino <= 0) {
                Flight::json(array('error' => 'La ejecución no tiene tenant destino registrado; deshacer por tenant no es seguro'), 400);
                return;
            }

            $sesion = MigracionSesiones::obtener($ejecucion['id_sesion']);
            $tablas = json_decode($ejecucion['tablas_tocadas'], true);
            $globales = $ejecucion['tablas_globales'] ? json_decode($ejecucion['tablas_globales'], true) : array();

            if (!is_array($tablas) || empty($tablas)) {
                Flight::json(array('error' => 'No hay tablas registradas en la bitácora'), 400);
                return;
            }
            if (!is_array($globales)) {
                $globales = array();
            }

            // Orden inverso: las dependencias se borran antes que sus padres.
            $tablas = array_reverse($tablas);

            $destino = MigracionConexiones::pdoDestino($sesion['id_conexion']);
            $destino->beginTransaction();

            $detalle = array();
            $totalFilas = 0;

            foreach ($tablas as $tabla) {
                // Un catalogo global no lleva id_tenant: no se puede borrar
                // por tenant sin arrastrar datos de otros clientes.
                if (in_array($tabla, $globales, true)) {
                    continue;
                }
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $tabla)) {
                    continue;
                }

                $sentence = $destino->prepare("DELETE FROM `{$tabla}` WHERE id_tenant = :id_tenant");
                $sentence->bindValue(':id_tenant', $idTenantDestino, PDO::PARAM_INT);
                $sentence->execute();

                $filas = $sentence->rowCount();
                $totalFilas += $filas;
                $detalle[] = array('tabla' => $tabla, 'filas' => $filas);
            }

            $destino->commit();

            $db = Flight::db();
            $sentence = $db->prepare("UPDATE migracion_ejecuciones SET deshecho = 1, fecha_deshecho = NOW()
                                      WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            MigracionScripts::cambiarEstado($ejecucion['id_script'], 'deshecho');

            if ($ejecucion['codigo_bloque']) {
                MigracionBloques::cambiarEstadoPorCodigo($ejecucion['id_sesion'], $ejecucion['codigo_bloque'], 'deshecho');
            }

            $aviso = empty($globales) ? null
                : 'Estos catálogos globales NO se deshicieron porque no llevan id_tenant: ' . implode(', ', $globales) . '. Revísalos a mano si hace falta.';

            Flight::json(array(
                'success' => true,
                'bloque' => $ejecucion['codigo_bloque'],
                'filas_borradas' => $totalFilas,
                'detalle' => $detalle,
                'aviso_globales' => $aviso
            ));
        } catch (Exception $e) {
            if ($destino && $destino->inTransaction()) {
                $destino->rollBack();
            }
            error_log("MigracionEjecuciones::deshacer - " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // =====================================================
    // USO INTERNO DE LOS DEMAS SERVICIOS DEL MODULO
    // =====================================================

    /**
     * Deja el rastro de una ejecucion, haya salido bien o mal.
     * Lo llama MigracionScripts::ejecutar.
     */
    public static function registrar($sesion, $script, $conexion, $datos)
    {
        $db = Flight::db();
        $sentence = $db->prepare("INSERT INTO migracion_ejecuciones
            (id, id_tenant, id_sesion, id_script, codigo_bloque, codigo_tenant_destino, id_tenant_destino,
             base_destino, ambiente, sentencias, filas_afectadas, tablas_tocadas, tablas_globales,
             exito, mensaje_error, nombre_usuario)
            VALUES (:id, :id_tenant, :id_sesion, :id_script, :codigo_bloque, :codigo_tenant_destino, :id_tenant_destino,
                    :base_destino, :ambiente, :sentencias, :filas, :tablas, :globales,
                    :exito, :error, :usuario)");
        $sentence->bindValue(':id', Uuid::generar());
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':id_sesion', $sesion['id']);
        $sentence->bindValue(':id_script', $script['id']);
        $sentence->bindValue(':codigo_bloque', isset($script['codigo_bloque']) ? $script['codigo_bloque'] : null);
        $sentence->bindValue(':codigo_tenant_destino', $sesion['codigo_tenant_destino']);
        $sentence->bindValue(':id_tenant_destino', $sesion['id_tenant_destino'], $sesion['id_tenant_destino'] === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $sentence->bindValue(':base_destino', $conexion['base_datos']);
        $sentence->bindValue(':ambiente', $conexion['ambiente']);
        $sentence->bindValue(':sentencias', $datos['sentencias'], PDO::PARAM_INT);
        $sentence->bindValue(':filas', $datos['filas'], PDO::PARAM_INT);
        $sentence->bindValue(':tablas', json_encode($datos['tablas']));
        $sentence->bindValue(':globales', json_encode($datos['globales']));
        $sentence->bindValue(':exito', $datos['exito'] ? 1 : 0, PDO::PARAM_INT);
        $sentence->bindValue(':error', $datos['error']);
        $sentence->bindValue(':usuario', $datos['usuario']);
        $sentence->execute();
    }

    /**
     * @return array|false
     */
    public static function obtener($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT * FROM migracion_ejecuciones WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Ultimas ejecuciones de una sesion, para el contexto del asistente.
     *
     * @return array
     */
    public static function obtenerDeSesion($id_sesion, $limite = 10)
    {
        $db = Flight::db();
        $limite = (int)$limite;
        $sentence = $db->prepare("SELECT codigo_bloque, filas_afectadas, exito, deshecho, fecha
                                  FROM migracion_ejecuciones
                                  WHERE id_sesion = :id_sesion AND id_tenant = :id_tenant
                                  ORDER BY fecha DESC LIMIT {$limite}");
        $sentence->bindValue(':id_sesion', $id_sesion);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll(PDO::FETCH_ASSOC);
    }
}
