<?php
/**
 * Calificacion del taller y del equipo (Califiquemonos).
 *
 * Es anonima a proposito: la calificacion cuelga de la visita, nunca del
 * participante. Por eso aqui no hay "editar lo que ya respondi": no hay forma
 * de saber cual fila es de quien, y esa es justamente la idea.
 */
class VisitasCalificaciones
{
    /**
     * Registra una calificacion. La llama el flujo publico, que ya valido el
     * token de la visita. Devuelve el id de la calificacion creada.
     *
     * @param string $idVisita
     * @param array  $detalle       [{id_item, puntaje, texto}]
     * @param array  $colaboradores [{id_visita_colaborador, puntaje}]
     */
    public static function registrar($idVisita, $detalle, $colaboradores)
    {
        $db = Flight::db();

        $tipos = VisitasCalificacionItems::mapaTipos();

        // Solo se aceptan puntajes de asistentes que esten en la visita.
        // El id es el de la fila de visitas_colaboradores, no el del
        // colaborador: los asistentes se escriben por nombre.
        $idsAsistentes = array();
        foreach (VisitasColaboradores::listar($idVisita) as $asistente) {
            $idsAsistentes[] = $asistente['id'];
        }

        $db->beginTransaction();

        try {
            $id = Uuid::generar();

            $crear = $db->prepare("
                INSERT INTO visitas_calificaciones (id, id_tenant, id_visita)
                VALUES (:id, :id_tenant, :id_visita)
            ");
            $crear->bindValue(':id', $id);
            $crear->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $crear->bindParam(':id_visita', $idVisita);
            $crear->execute();

            if (is_array($detalle) && !empty($detalle)) {
                $insertar = $db->prepare("
                    INSERT INTO visitas_calificaciones_detalle (id, id_tenant, id_calificacion, id_item, puntaje, texto)
                    VALUES (:id, :id_tenant, :id_calificacion, :id_item, :puntaje, :texto)
                ");

                foreach ($detalle as $fila) {
                    $idItem = isset($fila['id_item']) ? $fila['id_item'] : null;
                    if (!$idItem || !isset($tipos[$idItem])) {
                        continue;
                    }

                    $esEscala = $tipos[$idItem] === 'escala';

                    $puntaje = null;
                    $texto   = null;

                    if ($esEscala) {
                        if (isset($fila['puntaje']) && $fila['puntaje'] !== null && $fila['puntaje'] !== '') {
                            $puntaje = (int)$fila['puntaje'];
                            if ($puntaje < 1 || $puntaje > 5) {
                                $puntaje = null;
                            }
                        }
                    } else {
                        $texto = isset($fila['texto']) ? trim($fila['texto']) : '';
                        if ($texto === '') {
                            $texto = null;
                        }
                    }

                    if ($puntaje === null && $texto === null) {
                        continue;
                    }

                    $insertar->bindValue(':id', Uuid::generar());
                    $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertar->bindValue(':id_calificacion', $id);
                    $insertar->bindValue(':id_item', $idItem);
                    $insertar->bindValue(':puntaje', $puntaje, $puntaje === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insertar->bindValue(':texto', $texto, $texto === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
                    $insertar->execute();
                }
            }

            if (is_array($colaboradores) && !empty($colaboradores)) {
                $insertarColab = $db->prepare("
                    INSERT INTO visitas_calificaciones_colaboradores (id, id_tenant, id_calificacion, id_visita_colaborador, puntaje)
                    VALUES (:id, :id_tenant, :id_calificacion, :id_visita_colaborador, :puntaje)
                ");

                foreach ($colaboradores as $fila) {
                    $idAsistente = isset($fila['id_visita_colaborador']) ? $fila['id_visita_colaborador'] : null;
                    if (!$idAsistente || !in_array($idAsistente, $idsAsistentes, true)) {
                        continue;
                    }

                    if (!isset($fila['puntaje']) || $fila['puntaje'] === null || $fila['puntaje'] === '') {
                        continue;
                    }
                    $puntaje = (int)$fila['puntaje'];
                    if ($puntaje < 1 || $puntaje > 5) {
                        continue;
                    }

                    $insertarColab->bindValue(':id', Uuid::generar());
                    $insertarColab->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $insertarColab->bindValue(':id_calificacion', $id);
                    $insertarColab->bindValue(':id_visita_colaborador', $idAsistente);
                    $insertarColab->bindValue(':puntaje', $puntaje, PDO::PARAM_INT);
                    $insertarColab->execute();
                }
            }

            $db->commit();

            return $id;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Resumen para la pantalla de resultados: promedios por item, promedios
     * por colaborador y las respuestas abiertas sueltas (sin autor).
     */
    public static function resumen($idVisita)
    {
        $db = Flight::db();

        $totales = $db->prepare("
            SELECT COUNT(*) AS total
            FROM visitas_calificaciones
            WHERE id_visita = :id_visita AND id_tenant = :id_tenant
        ");
        $totales->bindParam(':id_visita', $idVisita);
        $totales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $totales->execute();
        $fila = $totales->fetch();

        $items = $db->prepare("
            SELECT
                vci.id,
                vci.bloque,
                vci.titulo_bloque,
                vci.tipo,
                vci.texto,
                vci.orden,
                ROUND(AVG(vcd.puntaje), 2) AS promedio,
                COUNT(vcd.puntaje) AS respuestas
            FROM visitas_calificacion_items vci
            LEFT JOIN visitas_calificaciones_detalle vcd
                   ON vcd.id_item = vci.id
                  AND vcd.id_calificacion IN (
                        SELECT id FROM visitas_calificaciones
                        WHERE id_visita = :id_visita AND id_tenant = :id_tenant
                      )
            WHERE vci.id_tenant = :id_tenant2 AND vci.activo = 1 AND vci.tipo = 'escala'
            GROUP BY vci.id, vci.bloque, vci.titulo_bloque, vci.tipo, vci.texto, vci.orden
            ORDER BY vci.orden ASC
        ");
        $items->bindParam(':id_visita', $idVisita);
        $items->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $items->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $items->execute();

        $abiertas = $db->prepare("
            SELECT vci.texto AS pregunta, vci.orden, vcd.texto AS respuesta
            FROM visitas_calificaciones_detalle vcd
            INNER JOIN visitas_calificacion_items vci ON vci.id = vcd.id_item
            INNER JOIN visitas_calificaciones vc ON vc.id = vcd.id_calificacion
            WHERE vc.id_visita = :id_visita
              AND vcd.id_tenant = :id_tenant
              AND vci.tipo = 'texto'
              AND vcd.texto IS NOT NULL
            ORDER BY vci.orden ASC
        ");
        $abiertas->bindParam(':id_visita', $idVisita);
        $abiertas->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $abiertas->execute();

        $porColaborador = $db->prepare("
            SELECT
                vcol.id AS id_visita_colaborador,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)), ''),
                    vcol.nombre
                ) AS nombre,
                ROUND(AVG(vcc.puntaje), 2) AS promedio,
                COUNT(vcc.puntaje) AS respuestas
            FROM visitas_colaboradores vcol
            LEFT JOIN colaboradores col ON col.id = vcol.id_colaborador
            LEFT JOIN personas p ON p.id = col.id_persona
            LEFT JOIN visitas_calificaciones_colaboradores vcc
                   ON vcc.id_visita_colaborador = vcol.id
                  AND vcc.id_calificacion IN (
                        SELECT id FROM visitas_calificaciones
                        WHERE id_visita = :id_visita AND id_tenant = :id_tenant
                      )
            WHERE vcol.id_visita = :id_visita2
              AND vcol.id_tenant = :id_tenant2
            GROUP BY vcol.id, nombre, vcol.orden
            ORDER BY vcol.orden ASC
        ");
        $porColaborador->bindParam(':id_visita', $idVisita);
        $porColaborador->bindParam(':id_visita2', $idVisita);
        $porColaborador->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $porColaborador->bindValue(':id_tenant2', TenantContext::id(), PDO::PARAM_INT);
        $porColaborador->execute();

        return array(
            'total_respuestas' => (int)$fila['total'],
            'items'            => $items->fetchAll(),
            'abiertas'         => $abiertas->fetchAll(),
            'colaboradores'    => $porColaborador->fetchAll(),
        );
    }
}
