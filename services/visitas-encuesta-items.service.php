<?php
/**
 * Catalogo de items de la encuesta de trabajo. Es un catalogo sembrado:
 * no tiene pantalla de administracion, solo lectura.
 *
 * Un item puede pedir dos cosas: si lo hace (si/no) y cuanto tiempo le toma.
 * Los items con pregunta_hace = 0 son los hipoteticos del ejercicio (cobrar
 * onces, el asistente por dia): ahi el si/no no aplica porque el jardin puede
 * no venderlos, y marcar "no" ensuciaria el diagnostico.
 */
class VisitasEncuestaItems
{
    // Items visibles para un rol. Requiere sesion (uso administrativo).
    public static function getByRol($rol)
    {
        try {
            JWTService::requerirAutenticacion();
            Flight::json(self::listar($rol));
        } catch (Exception $e) {
            error_log("Error en VisitasEncuestaItems::getByRol: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener los ítems de la encuesta'), 500);
        }
    }

    /**
     * Items que le corresponden a un rol, en orden.
     * 'todos' lo ven los tres; las secciones propias solo su rol.
     */
    public static function listar($rol)
    {
        if (!in_array($rol, array('docente', 'coordinacion', 'direccion'), true)) {
            $rol = 'docente';
        }

        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT id, seccion, titulo_seccion, rol, unidad, pregunta_hace, texto, orden
            FROM visitas_encuesta_items
            WHERE id_tenant = :id_tenant
              AND activo = 1
              AND (rol = 'todos' OR rol = :rol)
            ORDER BY orden ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindValue(':rol', $rol);
        $sentence->execute();
        return $sentence->fetchAll();
    }

    /**
     * Ids validos para un rol. Sirve para descartar respuestas de items que
     * ese participante no deberia estar contestando.
     */
    public static function idsValidos($rol)
    {
        $ids = array();
        foreach (self::listar($rol) as $item) {
            $ids[] = $item['id'];
        }
        return $ids;
    }
}
