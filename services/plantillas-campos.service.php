<?php

/**
 * Campos parametrizables de las plantillas.
 *
 * Cada plantilla declara que datos hay que pedirle al usuario al diligenciar
 * el documento (modalidad de pago, dia de corte, etc.). En el texto de la
 * plantilla esos datos se referencian con el marcador {{campo_LLAVE}}, y para
 * los campos de tipo opcion tambien con {{campo_LLAVE_VALOR}}, que imprime una
 * X cuando esa opcion es la elegida.
 */
class PlantillasCampos
{
    /**
     * GET /plantillas-campos
     * Todos los campos configurados del tenant.
     */
    public static function getAll()
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT pc.id, pc.id_plantilla, pc.llave, pc.etiqueta, pc.ayuda, pc.tipo,
                       pc.opciones, pc.valor_defecto, pc.obligatorio, pc.orden, pc.activo,
                       p.clave AS clave_plantilla, p.titulo AS titulo_plantilla
                FROM plantillas_campos pc
                INNER JOIN plantillas p ON p.id = pc.id_plantilla
                WHERE pc.id_tenant = :id_tenant
                ORDER BY p.clave, pc.orden
            ");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * GET /plantillas-campos/por-clave/@clave
     * Campos activos de una plantilla, identificada por su clave.
     * Es el que consume el formulario para pintar los controles.
     */
    public static function getPorClave($clave)
    {
        try {
            $db = Flight::db();
            $sentence = $db->prepare("
                SELECT pc.id, pc.llave, pc.etiqueta, pc.ayuda, pc.tipo, pc.opciones,
                       pc.valor_defecto, pc.obligatorio, pc.orden
                FROM plantillas_campos pc
                INNER JOIN plantillas p ON p.id = pc.id_plantilla
                WHERE p.clave = :clave
                  AND pc.activo = 1
                  AND pc.id_tenant = :id_tenant
                ORDER BY pc.orden
            ");
            $sentence->bindParam(':clave', $clave);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();
            Flight::json($sentence->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * POST /plantillas-campos
     */
    public static function new()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;
            $id = Uuid::generar();

            $sentence = $db->prepare("
                INSERT INTO plantillas_campos
                    (id, id_tenant, id_plantilla, llave, etiqueta, ayuda, tipo, opciones,
                     valor_defecto, obligatorio, orden, activo)
                VALUES
                    (:id, :id_tenant, :id_plantilla, :llave, :etiqueta, :ayuda, :tipo, :opciones,
                     :valor_defecto, :obligatorio, :orden, 1)
            ");
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':id_plantilla', $data->id_plantilla);
            $sentence->bindValue(':llave', $data->llave);
            $sentence->bindValue(':etiqueta', $data->etiqueta);
            $sentence->bindValue(':ayuda', isset($data->ayuda) ? $data->ayuda : null);
            $sentence->bindValue(':tipo', isset($data->tipo) ? $data->tipo : 'texto');
            $sentence->bindValue(':opciones', isset($data->opciones) ? $data->opciones : null);
            $sentence->bindValue(':valor_defecto', isset($data->valor_defecto) ? $data->valor_defecto : null);
            $sentence->bindValue(':obligatorio', isset($data->obligatorio) ? (int)$data->obligatorio : 0, PDO::PARAM_INT);
            $sentence->bindValue(':orden', isset($data->orden) ? (int)$data->orden : 0, PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * PUT /plantillas-campos
     */
    public static function replace()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("
                UPDATE plantillas_campos
                SET llave = :llave, etiqueta = :etiqueta, ayuda = :ayuda, tipo = :tipo,
                    opciones = :opciones, valor_defecto = :valor_defecto,
                    obligatorio = :obligatorio, orden = :orden, activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            $sentence->bindValue(':id', $data->id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindValue(':llave', $data->llave);
            $sentence->bindValue(':etiqueta', $data->etiqueta);
            $sentence->bindValue(':ayuda', isset($data->ayuda) ? $data->ayuda : null);
            $sentence->bindValue(':tipo', isset($data->tipo) ? $data->tipo : 'texto');
            $sentence->bindValue(':opciones', isset($data->opciones) ? $data->opciones : null);
            $sentence->bindValue(':valor_defecto', isset($data->valor_defecto) ? $data->valor_defecto : null);
            $sentence->bindValue(':obligatorio', isset($data->obligatorio) ? (int)$data->obligatorio : 0, PDO::PARAM_INT);
            $sentence->bindValue(':orden', isset($data->orden) ? (int)$data->orden : 0, PDO::PARAM_INT);
            $sentence->bindValue(':activo', isset($data->activo) ? (int)$data->activo : 1, PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $data->id));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    /**
     * DELETE /plantillas-campos
     */
    public static function delete()
    {
        try {
            $db = Flight::db();
            $data = Flight::request()->data;

            $sentence = $db->prepare("DELETE FROM plantillas_campos WHERE id = :id AND id_tenant = :id_tenant");
            $sentence->bindValue(':id', $data->id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            Flight::json(array('id' => $data->id));
        } catch (Exception $e) {
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }
}
