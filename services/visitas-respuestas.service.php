<?php
/**
 * Respuestas de los tres cuestionarios que van firmados por el participante:
 * encuesta de trabajo, ideas y caracter.
 *
 * Los metodos guardar* los llama el flujo publico, que ya valido el token de
 * la visita y que el participante pertenezca a ella. Los metodos resumen* los
 * consume la pantalla de resultados, que si exige sesion.
 */
class VisitasRespuestas
{
    // -----------------------------------------------------------------
    // ENCUESTA DE TRABAJO
    // -----------------------------------------------------------------

    /**
     * Guarda (o reemplaza) la encuesta de un participante.
     * Reemplaza en vez de acumular: si vuelve a enviar, manda la version
     * completa del formulario.
     *
     * @param string $idParticipante
     * @param string $rol             rol con el que respondio
     * @param array  $respuestas      [{id_item, lo_hace, tiempo}]
     * @param array  $extras          [{seccion, texto, tiempo}]
     * @param string $comentarioFinal
     */
    public static function guardarEncuesta($idParticipante, $rol, $respuestas, $extras, $comentarioFinal)
    {
        $db = Flight::db();

        $idsValidos = VisitasEncuestaItems::idsValidos($rol);

        $db->beginTransaction();

        try {
            $buscar = $db->prepare("SELECT id FROM visitas_encuestas WHERE id_participante = :id_participante AND id_tenant = :id_tenant");
            $buscar->bindParam(':id_participante', $idParticipante);
            $buscar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $buscar->execute();
            $existente = $buscar->fetch();

            if ($existente) {
                $idEncuesta = $existente['id'];

                $actualizar = $db->prepare("UPDATE visitas_encuestas SET comentario_final = :comentario WHERE id = :id");
                $actualizar->bindParam(':comentario', $comentarioFinal);
                $actualizar->bindParam(':id', $idEncuesta);
                $actualizar->execute();

                $db->prepare("DELETE FROM visitas_encuesta_respuestas WHERE id_encuesta = :id")
                   ->execute(array(':id' => $idEncuesta));
                $db->prepare("DELETE FROM visitas_encuesta_extras WHERE id_encuesta = :id")
                   ->execute(array(':id' => $idEncuesta));
            } else {
                $idEncuesta = Uuid::generar();
                $crear = $db->prepare("
                    INSERT INTO visitas_encuestas (id, id_tenant, id_participante, comentario_final)
                    VALUES (:id, :id_tenant, :id_participante, :comentario)
                ");
                $crear->bindValue(':id', $idEncuesta);
                $crear->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $crear->bindParam(':id_participante', $idParticipante);
                $crear->bindParam(':comentario', $comentarioFinal);
                $crear->execute();
            }

            if (is_array($respuestas) && !empty($respuestas)) {
                $insertar = $db->prepare("
                    INSERT INTO visitas_encuesta_respuestas (id, id_tenant, id_encuesta, id_item, lo_hace, tiempo)
                    VALUES (:id, :id_tenant, :id_encuesta, :id_item, :lo_hace, :tiempo)
                ");

                foreach ($respuestas as $respuesta) {
                    $idItem = isset($respuesta['id_item']) ? $respuesta['id_item'] : null;

                    // Un item que no le corresponde al rol se ignora en silencio.
                    if (!$idItem || !in_array($idItem, $idsValidos, true)) {
                        continue;
                    }

                    $loHace = isset($respuesta['lo_hace']) && $respuesta['lo_hace'] !== null && $respuesta['lo_hace'] !== ''
                        ? (int)(bool)$respuesta['lo_hace']
                        : null;
                    $tiempo = isset($respuesta['tiempo']) && $respuesta['tiempo'] !== null && $respuesta['tiempo'] !== ''
                        ? (int)$respuesta['tiempo']
                        : null;

                    // Fila vacia: no aporta nada y ensucia el conteo.
                    if ($loHace === null && $tiempo === null) {
                        continue;
                    }

                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':id_encuesta', $idEncuesta);
                    $insertar->bindValue(':id_item', $idItem);
                    $insertar->bindValue(':lo_hace', $loHace, $loHace === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insertar->bindValue(':tiempo', $tiempo, $tiempo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insertar->execute();
                }
            }

            if (is_array($extras) && !empty($extras)) {
                $insertarExtra = $db->prepare("
                    INSERT INTO visitas_encuesta_extras (id, id_tenant, id_encuesta, seccion, texto, tiempo, orden)
                    VALUES (:id, :id_tenant, :id_encuesta, :seccion, :texto, :tiempo, :orden)
                ");
                $orden = 0;
                foreach ($extras as $extra) {
                    $texto = isset($extra['texto']) ? trim($extra['texto']) : '';
                    if ($texto === '') {
                        continue;
                    }
                    $orden++;
                    $tiempo = isset($extra['tiempo']) && $extra['tiempo'] !== null && $extra['tiempo'] !== ''
                        ? (int)$extra['tiempo']
                        : null;

                    $insertarExtra->bindValue(':id', Uuid::generar());
                    $insertarExtra->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertarExtra->bindValue(':id_encuesta', $idEncuesta);
                    $insertarExtra->bindValue(':seccion', isset($extra['seccion']) ? $extra['seccion'] : '');
                    $insertarExtra->bindValue(':texto', $texto);
                    $insertarExtra->bindValue(':tiempo', $tiempo, $tiempo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insertarExtra->bindValue(':orden', $orden, PDO::PARAM_INT);
                    $insertarExtra->execute();
                }
            }

            $db->commit();

            return $idEncuesta;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Lo que ya respondio el participante en la encuesta, para repintar el
     * formulario si vuelve a entrar.
     */
    public static function encuestaDeParticipante($idParticipante)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT id, comentario_final
            FROM visitas_encuestas
            WHERE id_participante = :id_participante AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_participante', $idParticipante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $encuesta = $sentence->fetch();

        if (!$encuesta) {
            return null;
        }

        $respuestas = $db->prepare("
            SELECT id_item, lo_hace, tiempo
            FROM visitas_encuesta_respuestas
            WHERE id_encuesta = :id_encuesta
        ");
        $respuestas->execute(array(':id_encuesta' => $encuesta['id']));

        $extras = $db->prepare("
            SELECT seccion, texto, tiempo
            FROM visitas_encuesta_extras
            WHERE id_encuesta = :id_encuesta
            ORDER BY orden ASC
        ");
        $extras->execute(array(':id_encuesta' => $encuesta['id']));

        return array(
            'comentario_final' => $encuesta['comentario_final'],
            'respuestas'       => $respuestas->fetchAll(),
            'extras'           => $extras->fetchAll(),
        );
    }

    /**
     * Encuestas de la visita para la pantalla de resultados: una entrada por
     * participante con sus respuestas ya resueltas contra el catalogo.
     */
    public static function resumenEncuestas($idVisita)
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT
                ve.id AS id_encuesta,
                ve.id_participante,
                ve.comentario_final,
                vp.nombre,
                vp.sobrenombre,
                vp.cargo,
                vp.rol_encuesta
            FROM visitas_encuestas ve
            INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
            WHERE vp.id_visita = :id_visita
              AND ve.id_tenant = :id_tenant
            ORDER BY vp.nombre ASC
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $encuestas = $sentence->fetchAll();

        if (empty($encuestas)) {
            return array();
        }

        $detalle = $db->prepare("
            SELECT
                ver.id_encuesta,
                ver.lo_hace,
                ver.tiempo,
                vei.seccion,
                vei.titulo_seccion,
                vei.unidad,
                vei.pregunta_hace,
                vei.texto,
                vei.orden
            FROM visitas_encuesta_respuestas ver
            INNER JOIN visitas_encuesta_items vei ON vei.id = ver.id_item
            INNER JOIN visitas_encuestas ve ON ve.id = ver.id_encuesta
            INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
            WHERE vp.id_visita = :id_visita
              AND ver.id_tenant = :id_tenant
            ORDER BY vei.orden ASC
        ");
        $detalle->bindParam(':id_visita', $idVisita);
        $detalle->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $detalle->execute();
        $filas = $detalle->fetchAll();

        $extras = $db->prepare("
            SELECT vee.id_encuesta, vee.seccion, vee.texto, vee.tiempo
            FROM visitas_encuesta_extras vee
            INNER JOIN visitas_encuestas ve ON ve.id = vee.id_encuesta
            INNER JOIN visitas_participantes vp ON vp.id = ve.id_participante
            WHERE vp.id_visita = :id_visita
              AND vee.id_tenant = :id_tenant
            ORDER BY vee.orden ASC
        ");
        $extras->bindParam(':id_visita', $idVisita);
        $extras->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $extras->execute();
        $filasExtras = $extras->fetchAll();

        foreach ($encuestas as &$encuesta) {
            $encuesta['respuestas']     = array();
            $encuesta['extras']         = array();
            $encuesta['total_minutos']  = 0;
            $encuesta['total_horas']    = 0;

            foreach ($filas as $fila) {
                if ($fila['id_encuesta'] !== $encuesta['id_encuesta']) {
                    continue;
                }
                $encuesta['respuestas'][] = $fila;

                $tiempo = $fila['tiempo'] !== null ? (int)$fila['tiempo'] : 0;
                if ($fila['unidad'] === 'horas') {
                    $encuesta['total_horas'] += $tiempo;
                } else {
                    $encuesta['total_minutos'] += $tiempo;
                }
            }

            foreach ($filasExtras as $fila) {
                if ($fila['id_encuesta'] !== $encuesta['id_encuesta']) {
                    continue;
                }
                $encuesta['extras'][] = $fila;
            }
        }

        return $encuestas;
    }

    // -----------------------------------------------------------------
    // IDEAS
    // -----------------------------------------------------------------

    /**
     * Guarda (o reemplaza) las cuatro ideas del participante.
     * Devuelve el id del registro de ideas.
     */
    public static function guardarIdeas($idParticipante, $datos)
    {
        $db = Flight::db();

        $directora   = isset($datos['idea_directora']) ? $datos['idea_directora'] : null;
        $docentes    = isset($datos['idea_docentes']) ? $datos['idea_docentes'] : null;
        $estudiantes = isset($datos['idea_estudiantes']) ? $datos['idea_estudiantes'] : null;
        $padres      = isset($datos['idea_padres']) ? $datos['idea_padres'] : null;

        $buscar = $db->prepare("SELECT id FROM visitas_ideas WHERE id_participante = :id_participante AND id_tenant = :id_tenant");
        $buscar->bindParam(':id_participante', $idParticipante);
        $buscar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $buscar->execute();
        $existente = $buscar->fetch();

        if ($existente) {
            $id = $existente['id'];
            $sentence = $db->prepare("
                UPDATE visitas_ideas
                SET idea_directora = :directora,
                    idea_docentes = :docentes,
                    idea_estudiantes = :estudiantes,
                    idea_padres = :padres
                WHERE id = :id
            ");
            $sentence->bindParam(':id', $id);
        } else {
            $id = Uuid::generar();
            $sentence = $db->prepare("
                INSERT INTO visitas_ideas (id, id_tenant, id_participante, idea_directora, idea_docentes, idea_estudiantes, idea_padres)
                VALUES (:id, :id_tenant, :id_participante, :directora, :docentes, :estudiantes, :padres)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_participante', $idParticipante);
        }

        $sentence->bindParam(':directora', $directora);
        $sentence->bindParam(':docentes', $docentes);
        $sentence->bindParam(':estudiantes', $estudiantes);
        $sentence->bindParam(':padres', $padres);
        $sentence->execute();

        return $id;
    }

    // Ideas ya guardadas por el participante, o null si no ha respondido.
    public static function ideasDeParticipante($idParticipante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT idea_directora, idea_docentes, idea_estudiantes, idea_padres
            FROM visitas_ideas
            WHERE id_participante = :id_participante AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_participante', $idParticipante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $fila = $sentence->fetch();
        return $fila ? $fila : null;
    }

    // Ideas de todos los participantes de la visita.
    public static function resumenIdeas($idVisita)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                vp.nombre,
                vp.sobrenombre,
                vp.cargo,
                vi.idea_directora,
                vi.idea_docentes,
                vi.idea_estudiantes,
                vi.idea_padres
            FROM visitas_ideas vi
            INNER JOIN visitas_participantes vp ON vp.id = vi.id_participante
            WHERE vp.id_visita = :id_visita
              AND vi.id_tenant = :id_tenant
            ORDER BY vp.nombre ASC
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    // -----------------------------------------------------------------
    // CARACTER
    // -----------------------------------------------------------------

    /**
     * Guarda (o reemplaza) las respuestas de caracter del participante.
     *
     * @param array $respuestas [{id_item, id_opcion}]
     */
    public static function guardarCaracter($idParticipante, $respuestas)
    {
        $db = Flight::db();

        $mapa = VisitasCaracterItems::mapaOpciones();

        $db->beginTransaction();

        try {
            $borrar = $db->prepare("DELETE FROM visitas_caracter_respuestas WHERE id_participante = :id_participante AND id_tenant = :id_tenant");
            $borrar->bindParam(':id_participante', $idParticipante);
            $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $borrar->execute();

            if (is_array($respuestas) && !empty($respuestas)) {
                $insertar = $db->prepare("
                    INSERT INTO visitas_caracter_respuestas (id, id_tenant, id_participante, id_item, id_opcion)
                    VALUES (:id, :id_tenant, :id_participante, :id_item, :id_opcion)
                ");

                foreach ($respuestas as $respuesta) {
                    $idItem   = isset($respuesta['id_item']) ? $respuesta['id_item'] : null;
                    $idOpcion = isset($respuesta['id_opcion']) ? $respuesta['id_opcion'] : null;

                    if (!$idItem || !$idOpcion) {
                        continue;
                    }
                    // La opcion tiene que ser de esa pregunta.
                    if (!isset($mapa[$idItem]) || !in_array($idOpcion, $mapa[$idItem], true)) {
                        continue;
                    }

                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':id_participante', $idParticipante);
                    $insertar->bindValue(':id_item', $idItem);
                    $insertar->bindValue(':id_opcion', $idOpcion);
                    $insertar->execute();
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    // Respuestas de caracter ya guardadas por el participante.
    public static function caracterDeParticipante($idParticipante)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id_item, id_opcion
            FROM visitas_caracter_respuestas
            WHERE id_participante = :id_participante AND id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_participante', $idParticipante);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    // Respuestas de caracter de toda la visita, con los textos resueltos.
    public static function resumenCaracter($idVisita)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                vp.nombre,
                vp.sobrenombre,
                vci.texto AS pregunta,
                vci.orden,
                vco.texto AS respuesta
            FROM visitas_caracter_respuestas vcr
            INNER JOIN visitas_participantes vp ON vp.id = vcr.id_participante
            INNER JOIN visitas_caracter_items vci ON vci.id = vcr.id_item
            INNER JOIN visitas_caracter_opciones vco ON vco.id = vcr.id_opcion
            WHERE vp.id_visita = :id_visita
              AND vcr.id_tenant = :id_tenant
            ORDER BY vci.orden ASC, vp.nombre ASC
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }
}
