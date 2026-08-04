<?php 
class ConceptosPago
{

    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("select id, descripcion, valor_defecto, estado from conceptos_pago where id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
    
}
