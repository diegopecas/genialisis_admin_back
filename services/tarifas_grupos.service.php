<?php
class TarifasGrupos
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_grupo, tg.id_producto_matricula, tg.id_producto_pension, tg.anio,
                   g.nombre AS nombre_grupo,
                   pm.nombre AS nombre_matricula, tg.valor_matricula,
                   pp.nombre AS nombre_pension, tg.valor_pension
            FROM tarifas_grupos tg
            INNER JOIN grupos g ON tg.id_grupo = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
            WHERE tg.id_tenant = :id_tenant
            ORDER BY g.orden, tg.anio DESC
        ");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_grupo, tg.id_producto_matricula, tg.id_producto_pension, tg.anio,
                   g.nombre AS nombre_grupo,
                   pm.nombre AS nombre_matricula, tg.valor_matricula,
                   pp.nombre AS nombre_pension, tg.valor_pension
            FROM tarifas_grupos tg
            INNER JOIN grupos g ON tg.id_grupo = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
            WHERE tg.id = :id AND tg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupo($idGrupo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_grupo, tg.id_producto_matricula, tg.id_producto_pension, tg.anio,
                   g.nombre AS nombre_grupo,
                   pm.nombre AS nombre_matricula, tg.valor_matricula,
                   pp.nombre AS nombre_pension, tg.valor_pension
            FROM tarifas_grupos tg
            INNER JOIN grupos g ON tg.id_grupo = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
            WHERE tg.id_grupo = :id_grupo AND tg.id_tenant = :id_tenant
            ORDER BY tg.anio DESC
        ");
        $sentence->bindParam(':id_grupo', $idGrupo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByGrupoAnio($idGrupo, $anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_grupo, tg.id_producto_matricula, tg.id_producto_pension, tg.anio,
                   g.nombre AS nombre_grupo,
                   pm.nombre AS nombre_matricula, tg.valor_matricula,
                   pp.nombre AS nombre_pension, tg.valor_pension
            FROM tarifas_grupos tg
            INNER JOIN grupos g ON tg.id_grupo = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
            WHERE tg.id_grupo = :id_grupo AND tg.anio = :anio AND tg.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id_grupo', $idGrupo);
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getByAnio($anio)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
            SELECT tg.id, tg.id_grupo, tg.id_producto_matricula, tg.id_producto_pension, tg.anio,
                   g.nombre AS nombre_grupo, g.orden,
                   pm.nombre AS nombre_matricula, tg.valor_matricula,
                   pp.nombre AS nombre_pension, tg.valor_pension
            FROM tarifas_grupos tg
            INNER JOIN grupos g ON tg.id_grupo = g.id
            INNER JOIN productos_servicios pm ON tg.id_producto_matricula = pm.id
            INNER JOIN productos_servicios pp ON tg.id_producto_pension = pp.id
            WHERE tg.anio = :anio AND tg.id_tenant = :id_tenant
            ORDER BY g.orden
        ");
        $sentence->bindParam(':anio', $anio);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            
            $id_grupo = Flight::request()->data['id_grupo'];
            $id_producto_matricula = Flight::request()->data['id_producto_matricula'];
            $id_producto_pension = Flight::request()->data['id_producto_pension'];
            $valor_matricula = isset(Flight::request()->data['valor_matricula']) ? Flight::request()->data['valor_matricula'] : 0;
            $valor_pension = isset(Flight::request()->data['valor_pension']) ? Flight::request()->data['valor_pension'] : 0;
            $anio = Flight::request()->data['anio'];

            $idNew = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO tarifas_grupos 
                                      (id, id_tenant, id_grupo, id_producto_matricula, id_producto_pension, valor_matricula, valor_pension, anio) 
                                      VALUES (:id, :id_tenant, :id_grupo, :id_producto_matricula, :id_producto_pension, :valor_matricula, :valor_pension, :anio)");
            
            $sentence->bindValue(':id', $idNew);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_producto_matricula', $id_producto_matricula);
            $sentence->bindParam(':id_producto_pension', $id_producto_pension);
            $sentence->bindParam(':valor_matricula', $valor_matricula);
            $sentence->bindParam(':valor_pension', $valor_pension);
            $sentence->bindParam(':anio', $anio);
            
            $sentence->execute();
            $id = $idNew;

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en TarifasGrupos::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            
            $id = Flight::request()->data['id'];
            $id_grupo = Flight::request()->data['id_grupo'];
            $id_producto_matricula = Flight::request()->data['id_producto_matricula'];
            $id_producto_pension = Flight::request()->data['id_producto_pension'];
            $valor_matricula = isset(Flight::request()->data['valor_matricula']) ? Flight::request()->data['valor_matricula'] : 0;
            $valor_pension = isset(Flight::request()->data['valor_pension']) ? Flight::request()->data['valor_pension'] : 0;
            $anio = Flight::request()->data['anio'];

            $sentence = $db->prepare("UPDATE tarifas_grupos SET 
                                      id_grupo = :id_grupo,
                                      id_producto_matricula = :id_producto_matricula,
                                      id_producto_pension = :id_producto_pension,
                                      valor_matricula = :valor_matricula,
                                      valor_pension = :valor_pension,
                                      anio = :anio
                                      WHERE id = :id AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_grupo', $id_grupo);
            $sentence->bindParam(':id_producto_matricula', $id_producto_matricula);
            $sentence->bindParam(':id_producto_pension', $id_producto_pension);
            $sentence->bindParam(':valor_matricula', $valor_matricula);
            $sentence->bindParam(':valor_pension', $valor_pension);
            $sentence->bindParam(':anio', $anio);
            
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en TarifasGrupos::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM tarifas_grupos WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}