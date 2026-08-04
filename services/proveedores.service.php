<?php
class Proveedores
{
    public static function getAll()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pr.id, pr.id_persona, pr.id_tipo_proveedor, pr.activo, pr.fecha_registro,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.id_tipo_identificacion, ti.nombre tipo_identificacion,
        p.numero_identificacion, p.fecha_nacimiento, p.id_genero, g.nombre nombre_genero, p.direccion,
        p.telefono, p.correo_electronico, p.id_ciudad, c.nombre nombre_ciudad,
        tp.nombre nombre_tipo_proveedor, p.razon_social,
        CASE 
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM proveedores pr
        INNER JOIN personas p ON pr.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        INNER JOIN tipos_proveedor tp ON pr.id_tipo_proveedor = tp.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE pr.id_tenant = :id_tenant
        ORDER BY pr.fecha_registro DESC");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getActivos()
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pr.id, pr.id_persona, pr.id_tipo_proveedor, pr.activo, pr.fecha_registro,
        p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido, 
        p.numero_identificacion, p.razon_social, p.telefono, p.correo_electronico,
        tp.nombre nombre_tipo_proveedor,
        CASE 
            WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
            ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                       IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
        END AS nombre_completo
        FROM proveedores pr
        INNER JOIN personas p ON pr.id_persona = p.id
        INNER JOIN tipos_proveedor tp ON pr.id_tipo_proveedor = tp.id
        WHERE pr.activo = 1
        AND pr.id_tenant = :id_tenant
        ORDER BY nombre_completo");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("
        SELECT pr.id, 
               pr.id_persona, 
               pr.id_tipo_proveedor,
               pr.activo, 
               pr.fecha_registro,
               p.primer_nombre, 
               p.segundo_nombre,
               p.primer_apellido, 
               p.segundo_apellido, 
               p.id_tipo_identificacion, 
               ti.nombre AS tipo_identificacion,
               p.numero_identificacion, 
               p.fecha_nacimiento, 
               p.id_genero, 
               g.nombre AS nombre_genero, 
               p.direccion,
               p.telefono,
               p.correo_electronico,
               p.nacionalidad,
               p.id_ciudad,
               c.nombre AS nombre_ciudad,
               p.razon_social,
               p.ocupacion,
               tp.nombre AS nombre_tipo_proveedor,
               CASE 
                   WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
                   ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                              IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
               END AS nombre_completo
        FROM proveedores pr
        INNER JOIN personas p ON pr.id_persona = p.id
        INNER JOIN tipos_identificacion ti ON p.id_tipo_identificacion = ti.id
        LEFT JOIN generos g ON p.id_genero = g.id
        INNER JOIN tipos_proveedor tp ON pr.id_tipo_proveedor = tp.id
        LEFT JOIN ciudades c ON p.id_ciudad = c.id
        WHERE pr.id = :id
        AND pr.id_tenant = :id_tenant
        ");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        try {
            $db = Flight::db();

            $id_persona = Flight::request()->data['id_persona'];
            $id_tipo_proveedor = Flight::request()->data['id_tipo_proveedor'];

            error_log("Datos recibidos para crear proveedor: id_persona=$id_persona, id_tipo_proveedor=$id_tipo_proveedor");

            $id = Uuid::generar();
            $sentence = $db->prepare("INSERT INTO proveedores(
                id,
                id_tenant,
                id_persona, 
                id_tipo_proveedor,
                activo,
                fecha_registro
            ) VALUES (
                :id,
                :id_tenant,
                :id_persona, 
                :id_tipo_proveedor,
                1,
                NOW()
            )");

            $sentence->bindValue(':id', $id);
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':id_persona', $id_persona);
            $sentence->bindParam(':id_tipo_proveedor', $id_tipo_proveedor);
            $ok = $sentence->execute();

            if (!$ok) {
                error_log("Error: no se pudo insertar el proveedor.");
                Flight::json(array('error' => 'No se pudo crear el proveedor.'), 500);
                return;
            }

            error_log("ID proveedor insertado: $id");
            Flight::json(array('id' => $id));
        } catch (Exception $e) {
            error_log("Error en la ejecución del método new de proveedores: " . $e->getMessage());
            Flight::json(array('error' => $e->getMessage()), 500);
        }
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $id_persona = Flight::request()->data['id_persona'];
        $id_tipo_proveedor = Flight::request()->data['id_tipo_proveedor'];
        $activo = Flight::request()->data['activo'];

        $sentence = $db->prepare("UPDATE proveedores SET 
                                id_persona = :id_persona, 
                                id_tipo_proveedor = :id_tipo_proveedor,
                                activo = :activo
                                WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindParam(':id_tipo_proveedor', $id_tipo_proveedor);
        $sentence->bindParam(':activo', $activo);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $sentence = $db->prepare("DELETE FROM proveedores WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();

        Flight::json(array('id' => $id));
    }

    public static function verificarDuplicados()
    {
        $db = Flight::db();
        $id_persona = Flight::request()->data['id_persona'];
        error_log("Verificando duplicados para proveedor: id_persona=$id_persona");

        $sentence = $db->prepare("SELECT COUNT(*) as total FROM proveedores WHERE id_persona = :id_persona AND id_tenant = :id_tenant");
        $sentence->bindParam(':id_persona', $id_persona);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetch();

        Flight::json(array('existe' => $response['total'] > 0));
    }

    public static function getByTipo($id_tipo)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT pr.*, p.*, tp.nombre as tipo_proveedor_nombre,
            CASE 
                WHEN p.razon_social IS NOT NULL AND p.razon_social != '' THEN p.razon_social
                ELSE CONCAT(IFNULL(p.primer_nombre, ''), ' ', IFNULL(p.segundo_nombre, ''), ' ', 
                           IFNULL(p.primer_apellido, ''), ' ', IFNULL(p.segundo_apellido, ''))
            END AS nombre_completo
            FROM proveedores pr
            INNER JOIN personas p ON pr.id_persona = p.id
            INNER JOIN tipos_proveedor tp ON pr.id_tipo_proveedor = tp.id
            WHERE pr.id_tipo_proveedor = :id_tipo AND pr.activo = 1 AND pr.id_tenant = :id_tenant
            ORDER BY nombre_completo");
        $sentence->bindParam(':id_tipo', $id_tipo);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }
}