<?php
/**
 * Asistentes de Genialisis a una visita. Son los que despues arman el bloque
 * "uno por uno" del cuestionario de calificacion.
 *
 * El nombre se escribe en la visita: la tabla colaboradores del Admin esta
 * vacia y no se puede depender de ella. id_colaborador queda como enlace
 * opcional por si algun dia se cargan.
 */
class VisitasColaboradores
{
    // Asistentes de una visita. Requiere sesion.
    public static function getByVisita($idVisita)
    {
        try {
            Flight::json(self::listar($idVisita));
        } catch (Exception $e) {
            error_log("Error en VisitasColaboradores::getByVisita: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los asistentes de la visita'), 500);
        }
    }

    /**
     * Listado reutilizable (lo consumen Visitas::getById, la calificacion y el
     * flujo publico). El id que devuelve es el de la fila de la visita: es al
     * que apuntan los puntajes del bloque "uno por uno".
     *
     * Si la fila quedo amarrada a un colaborador, se muestra el nombre de la
     * persona; si no, el que se escribio a mano.
     */
    public static function listar($idVisita)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT
                vc.id,
                vc.id_colaborador,
                vc.orden,
                COALESCE(
                    NULLIF(TRIM(CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido)), ''),
                    vc.nombre
                ) AS nombre
            FROM visitas_colaboradores vc
            LEFT JOIN colaboradores col ON col.id = vc.id_colaborador
            LEFT JOIN personas p ON p.id = col.id_persona
            WHERE vc.id_visita = :id_visita
              AND vc.id_tenant = :id_tenant
            ORDER BY vc.orden ASC, vc.nombre ASC
        ");
        $sentence->bindParam(':id_visita', $idVisita);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Deja la visita exactamente con los asistentes recibidos: borra los que
     * ya no estan e inserta los nuevos. Recibe la conexion porque se llama
     * dentro de la transaccion de Visitas.
     *
     * Se borra y se vuelve a insertar, asi que los ids de las filas cambian en
     * cada guardado. Por eso, si se edita la lista de asistentes despues de
     * que alguien ya califico, esos puntajes quedan huerfanos: el listado se
     * arma antes del taller, no despues.
     *
     * @param PDO    $db
     * @param string $idVisita
     * @param array  $asistentes  [{nombre, id_colaborador?}] o ['Nombre', ...]
     */
    public static function sincronizar($db, $idVisita, $asistentes)
    {
        if (!is_array($asistentes)) {
            $asistentes = array();
        }

        $borrar = $db->prepare("DELETE FROM visitas_colaboradores WHERE id_visita = :id_visita AND id_tenant = :id_tenant");
        $borrar->bindParam(':id_visita', $idVisita);
        $borrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $borrar->execute();

        if (empty($asistentes)) {
            return;
        }

        $insertar = $db->prepare("
            INSERT INTO visitas_colaboradores (id, id_tenant, id_visita, id_colaborador, nombre, orden)
            VALUES (:id, :id_tenant, :id_visita, :id_colaborador, :nombre, :orden)
        ");

        $orden = 0;

        foreach ($asistentes as $asistente) {
            // Se acepta el nombre suelto o el objeto completo: asi el front
            // puede mandar lo que le quede natural sin romper nada.
            if (is_array($asistente)) {
                $nombre        = isset($asistente['nombre']) ? trim($asistente['nombre']) : '';
                $idColaborador = isset($asistente['id_colaborador']) && $asistente['id_colaborador'] !== ''
                    ? $asistente['id_colaborador']
                    : null;
            } else {
                $nombre        = trim((string)$asistente);
                $idColaborador = null;
            }

            if ($nombre === '') {
                continue;
            }

            $orden++;

            $insertar->bindValue(':id', Uuid::generar());
            $insertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $insertar->bindValue(':id_visita', $idVisita);
            $insertar->bindValue(':id_colaborador', $idColaborador, $idColaborador === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $insertar->bindValue(':nombre', $nombre);
            $insertar->bindValue(':orden', $orden, PDO::PARAM_INT);
            $insertar->execute();
        }
    }
}
