<?php 
class IaTextos
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada	referencia,	id_persona_reporta,	fecha, texto, estado from ia_textos where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, fecha, texto, estado from ia_textos where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByReferencia($referencia)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, fecha, texto, estado from ia_textos where referencia like '%:referencia%' and id_tenant = :id_tenant");
        $sentence->bindParam(':referencia', $referencia);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByPersonaInvolucrada($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, id_persona_involucrada, referencia, id_persona_reporta, fecha, texto, estado from ia_textos where id_persona_involucrada = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $id_persona_involucrada = Flight::request()->data['id_persona_involucrada'];
        $referencia = Flight::request()->data['referencia'];
        $id_persona_reporta = Flight::request()->data['id_persona_reporta'];
        $texto = Flight::request()->data['texto'];
        $id = Uuid::generar();
        $sentence = $db->prepare("insert into ia_textos(id, id_tenant, id_persona_involucrada, referencia, id_persona_reporta, texto) values (:id, :id_tenant, :id_persona_involucrada, :referencia, :id_persona_reporta, :texto)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':id_persona_involucrada', $id_persona_involucrada);
        $sentence->bindParam(':referencia', $referencia);
        $sentence->bindParam(':id_persona_reporta', $id_persona_reporta);
        $sentence->bindParam(':texto', $texto);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("delete from ia_textos where id = :id and id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        // Flight::json(array('id' => $id));
        self::getById($id);
    }
    
}
