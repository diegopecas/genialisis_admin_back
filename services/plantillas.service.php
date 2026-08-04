<?php

/**
 * Servicio para gestión de Plantillas
 * Maneja las operaciones CRUD para tipos_plantilla y plantillas
 */

class Plantillas
{
    /**
     * Obtener todas las plantillas
     * GET /plantillas
     */
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT p.id, p.id_tipo_plantilla, p.clave, p.titulo,
                       p.fecha_creacion, p.fecha_actualizacion,
                       tp.codigo as tipo_codigo, tp.nombre as tipo_nombre
                FROM plantillas p
                INNER JOIN tipos_plantillas tp ON p.id_tipo_plantilla = tp.id
                WHERE p.id_tenant = :id_tenant
                ORDER BY tp.nombre, p.clave
            ");

            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en Plantillas::getAll: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtener plantilla por tipo y clave
     * GET /plantillas/obtener-by-tipo-clave/:codigoTipo/:clavePlantilla
     */
    public static function getByTipoClave($codigoTipo, $clavePlantilla)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
            SELECT p.id, p.id_tipo_plantilla, p.clave, p.titulo, p.contenido,
                   p.fecha_creacion, p.fecha_actualizacion,
                   tp.codigo as tipo_codigo, tp.nombre as tipo_nombre
            FROM plantillas p
            INNER JOIN tipos_plantillas tp ON p.id_tipo_plantilla = tp.id
            WHERE tp.codigo = :codigo_tipo
            AND p.clave = :clave_plantilla
            AND p.id_tenant = :id_tenant
            LIMIT 1
        ");

            $sentence->bindParam(':codigo_tipo', $codigoTipo);
            $sentence->bindParam(':clave_plantilla', $clavePlantilla);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                // ===== LOGS DE DEBUG =====
                error_log("=== DEBUG PLANTILLA getByTipoClave ===");
                error_log("Clave: " . $clavePlantilla);
                error_log("Tipo: " . $codigoTipo);
                error_log("Contenido es NULL antes de decodificar: " . ($resultado['contenido'] === null ? 'SI' : 'NO'));
                error_log("Longitud contenido RAW: " . strlen($resultado['contenido']));
                error_log("Primeros 200 chars: " . substr($resultado['contenido'], 0, 200));
                error_log("Últimos 50 chars: " . substr($resultado['contenido'], -50));

                // Decodificar el JSON del contenido
                $contenidoDecodificado = json_decode($resultado['contenido'], true);

                error_log("Contenido decodificado es NULL: " . ($contenidoDecodificado === null ? 'SI' : 'NO'));
                error_log("JSON last error code: " . json_last_error());
                error_log("JSON last error msg: " . json_last_error_msg());

                if ($contenidoDecodificado !== null) {
                    error_log("Contenido decodificado correctamente - Claves principales: " . implode(', ', array_keys($contenidoDecodificado)));
                    error_log("Tiene introduccion_singular: " . (isset($contenidoDecodificado['introduccion_singular']) ? 'SI' : 'NO'));
                }
                // ===== FIN LOGS =====

                $resultado['contenido'] = $contenidoDecodificado;
                Flight::json($resultado);
            } else {
                error_log("Plantilla no encontrada: tipo=$codigoTipo, clave=$clavePlantilla");
                Flight::json(array('error' => 'Plantilla no encontrada'), 404);
            }
        } catch (Exception $e) {
            error_log("Error en Plantillas::getByTipoClave: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtener todas las plantillas de un tipo
     * GET /plantillas/obtener-by-tipo/:codigoTipo
     */
    public static function getByTipo($codigoTipo)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT p.id, p.id_tipo_plantilla, p.clave, p.titulo,
                       p.fecha_creacion, p.fecha_actualizacion,
                       tp.codigo as tipo_codigo, tp.nombre as tipo_nombre
                FROM plantillas p
                INNER JOIN tipos_plantillas tp ON p.id_tipo_plantilla = tp.id
                WHERE tp.codigo = :codigo_tipo
                AND p.id_tenant = :id_tenant
                ORDER BY p.clave
            ");

            $sentence->bindParam(':codigo_tipo', $codigoTipo);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $resultados = $sentence->fetchAll(PDO::FETCH_ASSOC);
            Flight::json($resultados);
        } catch (Exception $e) {
            error_log("Error en Plantillas::getByTipo: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Obtener plantilla por ID
     * GET /plantillas/:id
     */
    public static function getById($id)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT p.id, p.id_tipo_plantilla, p.clave, p.titulo, p.contenido,
                       p.fecha_creacion, p.fecha_actualizacion,
                       tp.codigo as tipo_codigo, tp.nombre as tipo_nombre
                FROM plantillas p
                INNER JOIN tipos_plantillas tp ON p.id_tipo_plantilla = tp.id
                WHERE p.id = :id
                AND p.id_tenant = :id_tenant
                LIMIT 1
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            $resultado = $sentence->fetch(PDO::FETCH_ASSOC);

            if ($resultado) {
                // Decodificar el JSON del contenido
                $resultado['contenido'] = json_decode($resultado['contenido'], true);
                Flight::json($resultado);
            } else {
                Flight::json(array('error' => 'Plantilla no encontrada'), 404);
            }
        } catch (Exception $e) {
            error_log("Error en Plantillas::getById: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * Actualizar contenido de una plantilla
     * PUT /plantillas
     */
    public static function replace()
    {
        try {
            $db = Flight::db();
            $id = Flight::request()->data['id'];
            $titulo = Flight::request()->data['titulo'];
            $contenido = Flight::request()->data['contenido'];

            // Si contenido es un array, convertir a JSON
            if (is_array($contenido)) {
                $contenido = json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $sentence = $db->prepare("
                UPDATE plantillas 
                SET titulo = :titulo,
                    contenido = :contenido
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");

            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':titulo', $titulo);
            $sentence->bindParam(':contenido', $contenido);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);

            $sentence->execute();

            Flight::json(array('id' => $id, 'message' => 'Plantilla actualizada correctamente'));
        } catch (Exception $e) {
            error_log("Error en Plantillas::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}