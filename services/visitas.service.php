<?php
/**
 * Visitas a clientes (talleres). Cada visita genera un token que es la
 * credencial del enlace publico: quien lo tiene responde sin iniciar sesion,
 * asi que el token nunca se expone en listados publicos ni se deriva de datos
 * adivinables.
 */
class Visitas
{
    // Listado de visitas del tenant con el nombre del cliente y los conteos
    // que se muestran en la tabla (asistentes y cuestionarios respondidos).
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    v.id,
                    v.id_cliente,
                    v.nombre,
                    v.fecha,
                    v.fecha_vencimiento,
                    v.token,
                    v.observaciones,
                    v.activo,
                    v.fecha_registro,
                    COALESCE(
                        NULLIF(TRIM(p.razon_social), ''),
                        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                    ) AS nombre_cliente,
                    (SELECT COUNT(*) FROM visitas_participantes vp
                      WHERE vp.id_visita = v.id) AS total_participantes,
                    (SELECT COUNT(*) FROM visitas_colaboradores vc
                      WHERE vc.id_visita = v.id) AS total_colaboradores
                FROM visitas v
                INNER JOIN clientes c ON c.id = v.id_cliente
                INNER JOIN personas p ON p.id = c.id_persona
                WHERE v.id_tenant = :id_tenant
                ORDER BY v.fecha DESC, v.nombre ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en Visitas::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las visitas'), 500);
        }
    }

    // Detalle de una visita con sus colaboradores asociados.
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    v.id,
                    v.id_cliente,
                    v.nombre,
                    v.fecha,
                    v.fecha_vencimiento,
                    v.token,
                    v.observaciones,
                    v.activo,
                    COALESCE(
                        NULLIF(TRIM(p.razon_social), ''),
                        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                    ) AS nombre_cliente
                FROM visitas v
                INNER JOIN clientes c ON c.id = v.id_cliente
                INNER JOIN personas p ON p.id = c.id_persona
                WHERE v.id = :id AND v.id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $visita = $sentence->fetch();

            if (!$visita) {
                Flight::json(array('error' => 'No se encontró la visita'), 404);
                return;
            }

            $visita['colaboradores'] = VisitasColaboradores::listar($visita['id']);

            Flight::json($visita);
        } catch (Exception $e) {
            error_log("Error en Visitas::getById: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener la visita'), 500);
        }
    }

    // Crea la visita y su token. Devuelve el id, igual que el resto de
    // servicios del proyecto.
    public static function new()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas.administrar');

            $db = Flight::db();

            $idCliente     = Flight::request()->data['id_cliente'] ?? null;
            $nombre        = Flight::request()->data['nombre'] ?? null;
            $fecha         = Flight::request()->data['fecha'] ?? null;
            $vencimiento   = Flight::request()->data['fecha_vencimiento'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $activo        = Flight::request()->data['activo'] ?? 1;
            $colaboradores = Flight::request()->data['colaboradores'] ?? array();

            if (!$idCliente || !$nombre || !$fecha) {
                Flight::json(array('error' => 'Faltan datos de la visita (cliente, nombre y fecha)'), 400);
                return;
            }

            $vencimiento = self::normalizarVencimiento($vencimiento);

            $id = Uuid::generar();

            $db->beginTransaction();

            $sentence = $db->prepare("
                INSERT INTO visitas (id, id_tenant, id_cliente, nombre, fecha, fecha_vencimiento, token, observaciones, activo)
                VALUES (:id, :id_tenant, :id_cliente, :nombre, :fecha, :fecha_vencimiento, :token, :observaciones, :activo)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_cliente', $idCliente);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindValue(':fecha_vencimiento', $vencimiento, $vencimiento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindValue(':token', self::generarToken());
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->execute();

            VisitasColaboradores::sincronizar($db, $id, $colaboradores);

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Visitas::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Actualiza la visita y vuelve a sincronizar sus colaboradores.
    // El token no se toca: cambiarlo invalidaria los enlaces ya repartidos.
    public static function replace()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas.administrar');

            $db = Flight::db();

            $id            = Flight::request()->data['id'] ?? null;
            $idCliente     = Flight::request()->data['id_cliente'] ?? null;
            $nombre        = Flight::request()->data['nombre'] ?? null;
            $fecha         = Flight::request()->data['fecha'] ?? null;
            $vencimiento   = Flight::request()->data['fecha_vencimiento'] ?? null;
            $observaciones = Flight::request()->data['observaciones'] ?? null;
            $activo        = Flight::request()->data['activo'] ?? 1;
            $colaboradores = Flight::request()->data['colaboradores'] ?? array();

            if (!$id || !$idCliente || !$nombre || !$fecha) {
                Flight::json(array('error' => 'Faltan datos de la visita'), 400);
                return;
            }

            $vencimiento = self::normalizarVencimiento($vencimiento);

            $db->beginTransaction();

            $sentence = $db->prepare("
                UPDATE visitas
                SET id_cliente = :id_cliente,
                    nombre = :nombre,
                    fecha = :fecha,
                    fecha_vencimiento = :fecha_vencimiento,
                    observaciones = :observaciones,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_cliente', $idCliente);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->bindValue(':fecha_vencimiento', $vencimiento, $vencimiento === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $sentence->bindParam(':observaciones', $observaciones);
            $sentence->bindParam(':activo', $activo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            VisitasColaboradores::sincronizar($db, $id, $colaboradores);

            $db->commit();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Visitas::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    // Borrado real: se lleva participantes, respuestas y calificaciones de la
    // visita. El listado no filtra por activo, asi que una baja logica dejaria
    // la visita a la vista; activo solo sirve para cerrar el enlace.
    public static function delete()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas.administrar');

            $db = Flight::db();
            $id = Flight::request()->data['id'] ?? null;

            if (!$id) {
                Flight::json(array('error' => 'Falta el id de la visita'), 400);
                return;
            }

            $verif = $db->prepare("SELECT id FROM visitas WHERE id = :id AND id_tenant = :id_tenant");
            $verif->bindParam(':id', $id);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();
            if (!$verif->fetch()) {
                Flight::json(array('error' => 'No se encontró la visita'), 404);
                return;
            }

            $db->beginTransaction();

            // Respuestas que cuelgan de los participantes de la visita.
            $db->prepare("
                DELETE ver FROM visitas_encuesta_respuestas ver
                INNER JOIN visitas_encuestas ve ON ve.id = ver.id_encuesta
                INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE vee FROM visitas_encuesta_extras vee
                INNER JOIN visitas_encuestas ve ON ve.id = vee.id_encuesta
                INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE ve FROM visitas_encuestas ve
                INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE vi FROM visitas_ideas vi
                INNER JOIN visitas_participantes vp ON vp.id = vi.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE vcr FROM visitas_caracter_respuestas vcr
                INNER JOIN visitas_participantes vp ON vp.id = vcr.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE vpg FROM visitas_participantes_grupos vpg
                INNER JOIN visitas_participantes vp ON vp.id = vpg.id_participante
                WHERE vp.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("DELETE FROM visitas_participantes WHERE id_visita = :id")
               ->execute(array(':id' => $id));

            // Calificaciones: cuelgan de la visita, no del participante.
            $db->prepare("
                DELETE vcd FROM visitas_calificaciones_detalle vcd
                INNER JOIN visitas_calificaciones vc ON vc.id = vcd.id_calificacion
                WHERE vc.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("
                DELETE vcc FROM visitas_calificaciones_colaboradores vcc
                INNER JOIN visitas_calificaciones vc ON vc.id = vcc.id_calificacion
                WHERE vc.id_visita = :id
            ")->execute(array(':id' => $id));

            $db->prepare("DELETE FROM visitas_calificaciones WHERE id_visita = :id")
               ->execute(array(':id' => $id));

            $db->prepare("DELETE FROM visitas_colaboradores WHERE id_visita = :id")
               ->execute(array(':id' => $id));

            $sentence = $db->prepare("DELETE FROM visitas WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $db->commit();

            Flight::json(array('id' => $id, 'mensaje' => 'Visita eliminada'));
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Error en Visitas::delete: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Consolidado de lo respondido en la visita. Devuelve un solo objeto con
     * todos los bloques para que la pantalla de resultados no tenga que
     * encadenar seis peticiones.
     */
    public static function resultados($id)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas.resultados');

            $db = Flight::db();

            $verif = $db->prepare("SELECT id, nombre, fecha FROM visitas WHERE id = :id AND id_tenant = :id_tenant");
            $verif->bindParam(':id', $id);
            $verif->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $verif->execute();
            $visita = $verif->fetch();

            if (!$visita) {
                Flight::json(array('error' => 'No se encontró la visita'), 404);
                return;
            }

            Flight::json(array(
                'visita'         => $visita,
                'participantes'  => VisitasParticipantes::listar($id),
                'encuestas'      => VisitasRespuestas::resumenEncuestas($id),
                'ideas'          => VisitasRespuestas::resumenIdeas($id),
                'caracter'       => VisitasRespuestas::resumenCaracter($id),
                'calificacion'   => VisitasCalificaciones::resumen($id),
            ));
        } catch (Exception $e) {
            error_log("Error en Visitas::resultados: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Clientes para el selector del formulario de visita.
     *
     * Existe aparte de Clientes::getAll porque ese listado no trae
     * razon_social, que es justo el nombre con el que se conoce a un jardin.
     * Tocar aquel metodo romperia a quien ya lo consume.
     */
    public static function clientesDisponibles()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas');

            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT
                    c.id,
                    c.activo,
                    COALESCE(
                        NULLIF(TRIM(p.razon_social), ''),
                        TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                    ) AS nombre_cliente,
                    p.numero_identificacion
                FROM clientes c
                INNER JOIN personas p ON p.id = c.id_persona
                WHERE c.id_tenant = :id_tenant
                ORDER BY nombre_cliente ASC
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll());
        } catch (Exception $e) {
            error_log("Error en Visitas::clientesDisponibles: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los clientes'), 500);
        }
    }

    /**
     * Resuelve una visita por su token. Uso interno del flujo publico: no
     * expone el id del cliente ni nada que no se necesite en el celular.
     * Devuelve null si el token no existe o la visita esta cerrada.
     */
    public static function porToken($token)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                v.id,
                v.nombre,
                v.fecha,
                v.fecha_vencimiento,
                v.activo,
                COALESCE(
                    NULLIF(TRIM(p.razon_social), ''),
                    TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido))
                ) AS nombre_cliente
            FROM visitas v
            INNER JOIN clientes c ON c.id = v.id_cliente
            INNER JOIN personas p ON p.id = c.id_persona
            WHERE v.token = :token AND v.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':token', $token);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $visita = $sentence->fetch();

        if (!$visita || (int)$visita['activo'] !== 1) {
            return null;
        }

        // Enlace vencido: se compara contra la hora del servidor, que es la
        // unica confiable. La del celular la pone quien lo usa.
        if (!empty($visita['fecha_vencimiento'])) {
            if (strtotime($visita['fecha_vencimiento']) < time()) {
                return null;
            }
        }

        return $visita;
    }

    /**
     * Normaliza el vencimiento que manda el front. El input datetime-local
     * llega como 'YYYY-MM-DDTHH:MM' y MySQL lo quiere con espacio. Vacio o
     * ilegible se guarda como NULL, que significa "no vence".
     */
    private static function normalizarVencimiento($valor)
    {
        if (empty($valor)) {
            return null;
        }

        $valor = str_replace('T', ' ', trim($valor));

        $tiempo = strtotime($valor);
        if ($tiempo === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $tiempo);
    }

    // Token del enlace publico: 32 hex aleatorios. random_bytes es
    // criptograficamente seguro; sin sesion de por medio, el token es la
    // unica barrera del enlace.
    private static function generarToken()
    {
        return bin2hex(random_bytes(16));
    }
}
