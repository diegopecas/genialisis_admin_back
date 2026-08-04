<?php
class ConfiguracionGlobal
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, clave, valor_texto, valor_numero, valor_fecha, descripcion 
                                  FROM configuracion_global 
                                  WHERE id_tenant = :id_tenant
                                  ORDER BY clave");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, clave, valor_texto, valor_numero, valor_fecha, descripcion 
                                  FROM configuracion_global 
                                  WHERE id = :id
                                  AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getByClave($clave)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, clave, valor_texto, valor_numero, valor_fecha, descripcion 
                                  FROM configuracion_global 
                                  WHERE clave = :clave
                                  AND id_tenant = :id_tenant");
        $sentence->bindParam(':clave', $clave);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();
        Flight::json($response);
    }

    public static function getMultiples()
    {
        $db = Flight::db();
        $claves = Flight::request()->data['claves'];
        
        if (!is_array($claves) || empty($claves)) {
            Flight::json(array('error' => 'Se requiere un array de claves'));
            return;
        }

        $placeholders = implode(',', array_fill(0, count($claves), '?'));
        $sentence = $db->prepare("SELECT id, clave, valor_texto, valor_numero, valor_fecha, descripcion 
                                  FROM configuracion_global 
                                  WHERE clave IN ($placeholders)
                                  AND id_tenant = ?");
        $sentence->execute(array_merge($claves, [TenantContext::id()]));
        $response = $sentence->fetchAll();
        
        // Convertir a array asociativo por clave
        $resultado = [];
        foreach ($response as $row) {
            $resultado[$row['clave']] = $row;
        }
        
        Flight::json($resultado);
    }

    public static function new()
    {
        try {
            $db = Flight::db();
            
            $clave = Flight::request()->data['clave'];
            $valor_texto = isset(Flight::request()->data['valor_texto']) ? Flight::request()->data['valor_texto'] : null;
            $valor_numero = isset(Flight::request()->data['valor_numero']) ? Flight::request()->data['valor_numero'] : null;
            $valor_fecha = isset(Flight::request()->data['valor_fecha']) ? Flight::request()->data['valor_fecha'] : null;
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO configuracion_global 
                                      (id, id_tenant, clave, valor_texto, valor_numero, valor_fecha, descripcion) 
                                      VALUES (:id, :id_tenant, :clave, :valor_texto, :valor_numero, :valor_fecha, :descripcion)");
            
            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':clave', $clave);
            $sentence->bindParam(':valor_texto', $valor_texto);
            $sentence->bindParam(':valor_numero', $valor_numero);
            $sentence->bindParam(':valor_fecha', $valor_fecha);
            $sentence->bindParam(':descripcion', $descripcion);
            
            $sentence->execute();

            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en ConfiguracionGlobal::new: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        try {
            $db = Flight::db();
            
            $id = Flight::request()->data['id'];
            $clave = Flight::request()->data['clave'];
            $valor_texto = isset(Flight::request()->data['valor_texto']) ? Flight::request()->data['valor_texto'] : null;
            $valor_numero = isset(Flight::request()->data['valor_numero']) ? Flight::request()->data['valor_numero'] : null;
            $valor_fecha = isset(Flight::request()->data['valor_fecha']) ? Flight::request()->data['valor_fecha'] : null;
            $descripcion = isset(Flight::request()->data['descripcion']) ? Flight::request()->data['descripcion'] : null;

            $sentence = $db->prepare("UPDATE configuracion_global SET 
                                      clave = :clave,
                                      valor_texto = :valor_texto,
                                      valor_numero = :valor_numero,
                                      valor_fecha = :valor_fecha,
                                      descripcion = :descripcion
                                      WHERE id = :id
                                      AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':id', $id);
            $sentence->bindParam(':clave', $clave);
            $sentence->bindParam(':valor_texto', $valor_texto);
            $sentence->bindParam(':valor_numero', $valor_numero);
            $sentence->bindParam(':valor_fecha', $valor_fecha);
            $sentence->bindParam(':descripcion', $descripcion);
            
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getById($id);
        } catch (Exception $e) {
            error_log("Error en ConfiguracionGlobal::replace: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function updateByClave()
    {
        try {
            $db = Flight::db();
            
            $clave = Flight::request()->data['clave'];
            $valor_texto = isset(Flight::request()->data['valor_texto']) ? Flight::request()->data['valor_texto'] : null;
            $valor_numero = isset(Flight::request()->data['valor_numero']) ? Flight::request()->data['valor_numero'] : null;
            $valor_fecha = isset(Flight::request()->data['valor_fecha']) ? Flight::request()->data['valor_fecha'] : null;

            $sentence = $db->prepare("UPDATE configuracion_global SET 
                                      valor_texto = :valor_texto,
                                      valor_numero = :valor_numero,
                                      valor_fecha = :valor_fecha
                                      WHERE clave = :clave
                                      AND id_tenant = :id_tenant");
            
            $sentence->bindParam(':clave', $clave);
            $sentence->bindParam(':valor_texto', $valor_texto);
            $sentence->bindParam(':valor_numero', $valor_numero);
            $sentence->bindParam(':valor_fecha', $valor_fecha);
            
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->execute();

            self::getByClave($clave);
        } catch (Exception $e) {
            error_log("Error en ConfiguracionGlobal::updateByClave: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        
        $sentence = $db->prepare("DELETE FROM configuracion_global WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }
}