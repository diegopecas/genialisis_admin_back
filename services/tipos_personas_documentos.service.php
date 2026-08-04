<?php
class TiposPersonasDocumentos
{
    public static function getByTipoDocumento($idTipoDocumento)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tpd.id, tpd.id_tipo_persona, tpd.id_tipo_documento, 
                   tpd.obligatorio, tpd.orden, tp.nombre as nombre_tipo_persona
            FROM tipos_personas_documentos tpd
            INNER JOIN tipos_personas tp ON tpd.id_tipo_persona = tp.id
            WHERE tpd.id_tipo_documento = :id_tipo_documento
            AND tpd.id_tenant = :id_tenant
            ORDER BY tp.id
        ");
        $sentence->bindParam(':id_tipo_documento', $idTipoDocumento);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function save()
    {
        $db = Flight::db();
        
        // Leer JSON del body
        $body = json_decode(Flight::request()->getBody(), true);
        $idTipoDocumento = $body['id_tipo_documento'];
        $asociaciones = $body['asociaciones'];

        // Eliminar asociaciones existentes para este tipo de documento
        $deleteStmt = $db->prepare("
            DELETE FROM tipos_personas_documentos 
            WHERE id_tipo_documento = :id_tipo_documento
            AND id_tenant = :id_tenant
        ");
        $deleteStmt->bindParam(':id_tipo_documento', $idTipoDocumento);
        $deleteStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $deleteStmt->execute();

        // Insertar las nuevas asociaciones
        if (!empty($asociaciones)) {
            $insertStmt = $db->prepare("
                INSERT INTO tipos_personas_documentos 
                    (id, id_tenant, id_tipo_persona, id_tipo_documento, obligatorio, orden)
                VALUES 
                    (:id, :id_tenant, :id_tipo_persona, :id_tipo_documento, :obligatorio, :orden)
            ");

            foreach ($asociaciones as $asoc) {
                $idTipoPersona = $asoc['id_tipo_persona'];
                $obligatorio = $asoc['obligatorio'];
                $orden = $asoc['orden'];
                
                $idAsoc = Uuid::generar();
                $insertStmt->bindValue(':id', $idAsoc);
                $insertStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                $insertStmt->bindParam(':id_tipo_persona', $idTipoPersona);
                $insertStmt->bindParam(':id_tipo_documento', $idTipoDocumento);
                $insertStmt->bindParam(':obligatorio', $obligatorio);
                $insertStmt->bindParam(':orden', $orden);
                $insertStmt->execute();
            }
        }

        // Retornar las asociaciones actualizadas
        self::getByTipoDocumento($idTipoDocumento);
    }
}