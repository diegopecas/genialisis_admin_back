<?php
class Roles
{

    public static function getAll()
    {
        $db = Flight::db();
        // Incluye conteos para que el front pueda mostrar uso del rol
        $sentence = $db->prepare("SELECT r.id, r.nombre,
                (SELECT COUNT(*) FROM roles_x_usuario rxu WHERE rxu.id_rol = r.id) usuarios_asignados,
                (SELECT COUNT(*) FROM permisos_x_rol pxr WHERE pxr.id_rol = r.id) permisos_asignados
                FROM roles r
                WHERE r.id_tenant = :id_tenant
                ORDER BY r.nombre");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function getById($id)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT id, nombre FROM roles WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    public static function new()
    {
        $db = Flight::db();
        $nombre = trim(Flight::request()->data['nombre']);

        // Evitar roles con el mismo nombre en el tenant
        $check = $db->prepare("SELECT id FROM roles WHERE nombre = :nombre AND id_tenant = :id_tenant");
        $check->bindParam(':nombre', $nombre);
        $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $check->execute();
        if ($check->fetch()) {
            Flight::json(['error' => 'Ya existe un rol con ese nombre'], 400);
            return;
        }

        // El id se genera en PHP (UUID) porque la PK es CHAR(36) y lastInsertId() no la devuelve
        $id = Uuid::generar();
        $sentence = $db->prepare("INSERT INTO roles(id, id_tenant, nombre) VALUES (:id, :id_tenant, :nombre)");
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':nombre', $nombre);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

    public static function replace()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];
        $nombre = trim(Flight::request()->data['nombre']);

        $check = $db->prepare("SELECT id FROM roles WHERE nombre = :nombre AND id_tenant = :id_tenant AND id <> :id");
        $check->bindParam(':nombre', $nombre);
        $check->bindParam(':id', $id);
        $check->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $check->execute();
        if ($check->fetch()) {
            Flight::json(['error' => 'Ya existe otro rol con ese nombre'], 400);
            return;
        }

        $sentence = $db->prepare("UPDATE roles SET nombre = :nombre WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':nombre', $nombre);
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        self::getById($id);
    }

    public static function delete()
    {
        $db = Flight::db();
        $id = Flight::request()->data['id'];

        // No se elimina un rol con usuarios asignados
        $checkUsuarios = $db->prepare("SELECT COUNT(*) total FROM roles_x_usuario WHERE id_rol = :id");
        $checkUsuarios->bindParam(':id', $id);
        $checkUsuarios->execute();
        $usuarios = $checkUsuarios->fetch();
        if ($usuarios && intval($usuarios['total']) > 0) {
            Flight::json(['error' => 'El rol tiene ' . $usuarios['total'] . ' usuario(s) asignado(s). Retire los usuarios antes de eliminarlo.'], 400);
            return;
        }

        // Tampoco si tiene permisos asignados
        $checkPermisos = $db->prepare("SELECT COUNT(*) total FROM permisos_x_rol WHERE id_rol = :id");
        $checkPermisos->bindParam(':id', $id);
        $checkPermisos->execute();
        $permisos = $checkPermisos->fetch();
        if ($permisos && intval($permisos['total']) > 0) {
            Flight::json(['error' => 'El rol tiene ' . $permisos['total'] . ' permiso(s) asignado(s). Retire los permisos antes de eliminarlo.'], 400);
            return;
        }

        $sentence = $db->prepare("DELETE FROM roles WHERE id = :id AND id_tenant = :id_tenant");
        $sentence->bindParam(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        Flight::json(array('id' => $id));
    }

}
