<?php
/**
 * Catalogo de preguntas de caracter (seleccion unica) con sus opciones.
 * Catalogo sembrado: solo lectura, sin pantalla de administracion.
 */
class VisitasCaracterItems
{
    // Preguntas con sus opciones. Requiere sesion (uso administrativo).
    public static function getAll()
    {
        try {
            JWTService::requerirAutenticacion();
            Flight::json(self::listar());
        } catch (Exception $e) {
            error_log("Error en VisitasCaracterItems::getAll: " . $e->getMessage());
            Flight::json(array('error' => 'Ocurrió un error al obtener las preguntas de carácter'), 500);
        }
    }

    // Preguntas activas con sus opciones anidadas, en orden.
    public static function listar()
    {
        $db = Flight::db();

        $sentence = $db->prepare("
            SELECT id, texto, orden
            FROM visitas_caracter_items
            WHERE id_tenant = :id_tenant AND activo = 1
            ORDER BY orden ASC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $items = $sentence->fetchAll();

        if (empty($items)) {
            return array();
        }

        $opciones = $db->prepare("
            SELECT vco.id, vco.id_item, vco.texto, vco.orden
            FROM visitas_caracter_opciones vco
            INNER JOIN visitas_caracter_items vci ON vci.id = vco.id_item
            WHERE vco.id_tenant = :id_tenant AND vci.activo = 1
            ORDER BY vco.orden ASC
        ");
        $opciones->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $opciones->execute();
        $filas = $opciones->fetchAll();

        foreach ($items as &$item) {
            $item['opciones'] = array();
            foreach ($filas as $fila) {
                if ($fila['id_item'] === $item['id']) {
                    $item['opciones'][] = array(
                        'id'    => $fila['id'],
                        'texto' => $fila['texto'],
                    );
                }
            }
        }

        return $items;
    }

    /**
     * Mapa item => ids de opciones validas. Con el se descarta una respuesta
     * que apunte a una opcion de otra pregunta.
     */
    public static function mapaOpciones()
    {
        $mapa = array();
        foreach (self::listar() as $item) {
            $ids = array();
            foreach ($item['opciones'] as $opcion) {
                $ids[] = $opcion['id'];
            }
            $mapa[$item['id']] = $ids;
        }
        return $mapa;
    }
}
