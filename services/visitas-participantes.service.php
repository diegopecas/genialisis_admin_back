<?php
/**
 * Participantes de la visita: el equipo del jardin que entra por el enlace
 * publico y se registra con la hoja de presentacion.
 *
 * El registro NO exige sesion: llega por el flujo publico, que ya valido el
 * token de la visita antes de llamar aqui.
 */
class VisitasParticipantes
{
    // Participantes de una visita, con sus grupos. Requiere sesion.
    public static function getByVisita($idVisita)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            PermisosService::validar($userData, 'operaciones.visitas.resultados');

            Flight::json(self::listar($idVisita));
        } catch (Exception $e) {
            error_log("Error en VisitasParticipantes::getByVisita: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los participantes'), 500);
        }
    }

    /**
     * Listado reutilizable de participantes con sus grupos anidados.
     * Lo consumen la pantalla de resultados y el flujo publico.
     */
    public static function listar($idVisita)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                vp.id,
                vp.numero_documento,
                vp.nombre,
                vp.sobrenombre,
                vp.cargo,
                vp.rol_encuesta,
                vp.fecha_registro
            FROM visitas_participantes vp
            WHERE vp.id_visita = :id_visita
              AND vp.id_tenant = :id_tenant
            ORDER BY vp.fecha_registro ASC
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $participantes = $sentence->fetchAll();

        if (empty($participantes)) {
            return array();
        }

        $grupos = $db->prepare("
            SELECT vpg.id_participante, vpg.grupo, vpg.cantidad_ninos, vpg.orden
            FROM visitas_participantes_grupos vpg
            INNER JOIN visitas_participantes vp ON vp.id = vpg.id_participante
            WHERE vp.id_visita = :id_visita
              AND vpg.id_tenant = :id_tenant
            ORDER BY vpg.orden ASC
        ");
        $grupos->bindParam(':id_visita', $idVisita);
        $grupos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $grupos->execute();
        $filas = $grupos->fetchAll();

        foreach ($participantes as &$participante) {
            $participante['grupos'] = array();
            foreach ($filas as $fila) {
                if ($fila['id_participante'] === $participante['id']) {
                    $participante['grupos'][] = array(
                        'grupo'          => $fila['grupo'],
                        'cantidad_ninos' => $fila['cantidad_ninos'],
                    );
                }
            }
        }

        return $participantes;
    }

    /**
     * Crea o actualiza el participante y reemplaza sus grupos.
     * Se llama desde el flujo publico, dentro de su propia validacion de
     * token. Devuelve el id del participante.
     *
     * @param string      $idVisita
     * @param array       $datos     nombre, sobrenombre, cargo, rol_encuesta, grupos[]
     * @param string|null $idExistente
     */
    public static function guardar($idVisita, $datos, $idExistente = null)
    {
        $db = Flight::db();

        $documento   = isset($datos['numero_documento']) ? trim($datos['numero_documento']) : '';
        $nombre      = isset($datos['nombre']) ? trim($datos['nombre']) : '';
        $sobrenombre = isset($datos['sobrenombre']) ? trim($datos['sobrenombre']) : null;
        $cargo       = isset($datos['cargo']) ? trim($datos['cargo']) : null;
        $rolEncuesta = isset($datos['rol_encuesta']) ? $datos['rol_encuesta'] : 'docente';
        $grupos      = isset($datos['grupos']) && is_array($datos['grupos']) ? $datos['grupos'] : array();

        if ($documento === '') {
            throw new Exception('El número de documento es obligatorio');
        }

        if ($nombre === '') {
            throw new Exception('El nombre es obligatorio');
        }

        // El documento identifica a la persona dentro de la visita. Si ya lo
        // tiene otro participante, no se puede robar.
        $duplicado = $db->prepare("
            SELECT id FROM visitas_participantes
            WHERE id_visita = :id_visita
              AND numero_documento = :numero_documento
              AND id_tenant = :id_tenant
            LIMIT 1
        ");
        $duplicado->bindParam(':id_visita', $idVisita);
        $duplicado->bindParam(':numero_documento', $documento);
        $duplicado->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $duplicado->execute();
        $fila = $duplicado->fetch();

        if ($fila && $fila['id'] !== $idExistente) {
            throw new Exception('Ese número de documento ya está registrado en esta visita');
        }

        if (!in_array($rolEncuesta, array('docente', 'coordinacion', 'direccion'), true)) {
            $rolEncuesta = 'docente';
        }

        $id = $idExistente;

        if ($id) {
            $sentence = $db->prepare("
                UPDATE visitas_participantes
                SET numero_documento = :numero_documento,
                    nombre = :nombre,
                    sobrenombre = :sobrenombre,
                    cargo = :cargo,
                    rol_encuesta = :rol_encuesta
                WHERE id = :id AND id_visita = :id_visita AND id_tenant = :id_tenant
            ");
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':id_visita', $idVisita);
            $sentence->bindParam(':numero_documento', $documento);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':rol_encuesta', $rolEncuesta);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $borrar = $db->prepare("DELETE FROM visitas_participantes_grupos WHERE id_participante = :id_participante AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_participante', $id);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();
        } else {
            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO visitas_participantes (id, id_tenant, id_visita, numero_documento, nombre, sobrenombre, cargo, rol_encuesta)
                VALUES (:id, :id_tenant, :id_visita, :numero_documento, :nombre, :sobrenombre, :cargo, :rol_encuesta)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_visita', $idVisita);
            $sentence->bindParam(':numero_documento', $documento);
            $sentence->bindParam(':nombre', $nombre);
            $sentence->bindParam(':sobrenombre', $sobrenombre);
            $sentence->bindParam(':cargo', $cargo);
            $sentence->bindParam(':rol_encuesta', $rolEncuesta);
            $sentence->execute();
        }

        if (!empty($grupos)) {
            $insertar = $db->prepare("
                INSERT INTO visitas_participantes_grupos (id, id_tenant, id_participante, grupo, cantidad_ninos, orden)
                VALUES (:id, :id_tenant, :id_participante, :grupo, :cantidad_ninos, :orden)
            ");
            $orden = 0;
            foreach ($grupos as $grupo) {
                $nombreGrupo = isset($grupo['grupo']) ? trim($grupo['grupo']) : '';
                if ($nombreGrupo === '') {
                    continue;
                }
                $orden++;
                $cantidad = isset($grupo['cantidad_ninos']) && $grupo['cantidad_ninos'] !== ''
                    ? (int)$grupo['cantidad_ninos']
                    : null;

                $insertar->bindValue(':id', Uuid::generar());
                $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertar->bindValue(':id_participante', $id);
                $insertar->bindValue(':grupo', $nombreGrupo);
                $insertar->bindValue(':cantidad_ninos', $cantidad, $cantidad === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $insertar->bindValue(':orden', $orden, PDO::PARAM_INT);
                $insertar->execute();
            }
        }

        return $id;
    }

    /**
     * Busca un participante por su documento dentro de la visita.
     * Devuelve null si no existe: ahi el flujo publico pide la presentacion.
     */
    public static function buscarPorDocumento($idVisita, $numeroDocumento)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id
            FROM visitas_participantes
            WHERE id_visita = :id_visita
              AND numero_documento = :numero_documento
              AND id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindParam(':numero_documento', $numeroDocumento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();

        return $fila ? $fila['id'] : null;
    }

    /**
     * Verifica que el participante pertenezca a la visita. Es la validacion
     * que sostiene todo el flujo publico: sin ella, con un token valido se
     * podrian escribir respuestas sobre participantes de otra visita.
     */
    public static function perteneceAVisita($idParticipante, $idVisita)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id FROM visitas_participantes
            WHERE id = :id AND id_visita = :id_visita AND id_tenant = :id_tenant
            LIMIT 1
        ");
        $sentence->bindParam(':id', $idParticipante);
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetch() ? true : false;
    }

    /**
     * Estado de un participante: que cuestionarios ya respondio. Lo usa el
     * menu de actividades del celular para marcar pendiente / respondido.
     */
    public static function estado($idParticipante)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM visitas_encuestas WHERE id_participante = :id1) AS encuesta,
                (SELECT COUNT(*) FROM visitas_ideas WHERE id_participante = :id2) AS ideas,
                (SELECT COUNT(*) FROM visitas_caracter_respuestas WHERE id_participante = :id3) AS caracter
        ");
        $sentence->bindParam(':id1', $idParticipante);
        $sentence->bindParam(':id2', $idParticipante);
        $sentence->bindParam(':id3', $idParticipante);
        $sentence->execute();
        $fila = $sentence->fetch();

        return array(
            'encuesta' => (int)$fila['encuesta'] > 0,
            'ideas'    => (int)$fila['ideas'] > 0,
            'caracter' => (int)$fila['caracter'] > 0,
        );
    }
}
