<?php
/**
 * Catalogo de items del cuestionario de calificacion (Califiquemonos).
 * Catalogo sembrado: solo lectura.
 *
 * El bloque "uno por uno" no vive aqui: sale de los colaboradores asociados
 * a la visita, asi que cambia de una visita a otra sin tocar el catalogo.
 */
class VisitasCalificacionItems
{
    // Items del cuestionario. Requiere sesion (uso administrativo).
    public static function getAll()
    {
        try {
            JWTService::requerirAutenticacion();
            Flight::json(self::listar());
        } catch (Exception $e) {
            error_log("Error en VisitasCalificacionItems::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los ítems de calificación'), 500);
        }
    }

    // Items activos en orden, con su bloque y tipo (escala o texto).
    public static function listar()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, bloque, titulo_bloque, tipo, texto, orden
            FROM visitas_calificacion_items
            WHERE id_tenant = :id_tenant AND activo = 1
            ORDER BY orden ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    // Mapa id => tipo, para validar que un item de texto no llegue con puntaje.
    public static function mapaTipos()
    {
        $mapa = array();
        foreach (self::listar() as $item) {
            $mapa[$item['id']] = $item['tipo'];
        }
        return $mapa;
    }
}
