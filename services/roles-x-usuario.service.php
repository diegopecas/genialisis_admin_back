<?php
class RolesXUsuario
{

    /**
     * Usuarios del tenant con marca de si tienen el rol indicado.
     * Alimenta la pantalla Usuarios por Rol.
     */
    public static function getUsuariosByRol($idRol)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT u.id, u.usuario, u.correo_electronico, u.activo,
                CONCAT_WS(' ', p.primer_nombre, p.segundo_nombre, p.primer_apellido, p.segundo_apellido) nombre_completo,
                p.numero_identificacion,
                CASE WHEN rxu.id IS NULL THEN 0 ELSE 1 END asignado,
                CASE WHEN EXISTS (SELECT 1 FROM colaboradores c WHERE c.id_persona = p.id) THEN 1 ELSE 0 END es_colaborador,
                CASE WHEN EXISTS (SELECT 1 FROM representantes a WHERE a.id_persona = p.id) THEN 1 ELSE 0 END es_representante
                FROM usuarios u
                INNER JOIN personas p ON u.id_persona = p.id
                LEFT JOIN roles_x_usuario rxu ON rxu.id_usuario = u.id AND rxu.id_rol = :id_rol
                WHERE u.id_tenant = :id_tenant
                ORDER BY nombre_completo");
        $sentence->bindParam(':id_rol', $idRol);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Roles asignados a un usuario (para el formulario de usuario).
     */
    public static function getRolesByUsuario($idUsuario)
    {
        $db = Flight::db();
        $sentence = $db->prepare("SELECT r.id, r.nombre
                FROM roles_x_usuario rxu
                INNER JOIN roles r ON r.id = rxu.id_rol
                WHERE rxu.id_usuario = :id_usuario AND rxu.id_tenant = :id_tenant
                ORDER BY r.nombre");
        $sentence->bindParam(':id_usuario', $idUsuario);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
        $response = $sentence->fetchAll();
        Flight::json($response);
    }

    /**
     * Sincroniza los usuarios de un rol: recibe el arreglo completo de ids de
     * usuario que deben quedar con el rol; inserta los que faltan y elimina
     * los que sobran. Payload: { id_rol, usuarios: [id_usuario, ...] }
     */
    public static function sincronizarRol()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $idRol = Flight::request()->data['id_rol'];
            $usuarios = Flight::request()->data['usuarios'];
            if (!is_array($usuarios)) {
                $usuarios = [];
            }

            // Set actual
            $actualStmt = $db->prepare("SELECT id_usuario FROM roles_x_usuario WHERE id_rol = :id_rol AND id_tenant = :id_tenant");
            $actualStmt->bindParam(':id_rol', $idRol);
            $actualStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actualStmt->execute();
            $actuales = $actualStmt->fetchAll(PDO::FETCH_COLUMN);

            $insertar = array_diff($usuarios, $actuales);
            $eliminar = array_diff($actuales, $usuarios);

            $insStmt = $db->prepare("INSERT INTO roles_x_usuario(id, id_tenant, id_rol, id_usuario) VALUES (:id, :id_tenant, :id_rol, :id_usuario)");
            foreach ($insertar as $idUsuario) {
                $insStmt->execute([
                    ':id' => Uuid::generar(),
                    ':id_tenant' => TenantContext::id(),
                    ':id_rol' => $idRol,
                    ':id_usuario' => $idUsuario
                ]);
            }

            if (count($eliminar) > 0) {
                $marcadores = implode(',', array_fill(0, count($eliminar), '?'));
                $delStmt = $db->prepare("DELETE FROM roles_x_usuario WHERE id_rol = ? AND id_tenant = ? AND id_usuario IN ($marcadores)");
                $delStmt->execute(array_merge([$idRol, TenantContext::id()], array_values($eliminar)));
            }

            $db->commit();
            Flight::json(['id_rol' => $idRol, 'insertados' => count($insertar), 'eliminados' => count($eliminar)]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sincroniza los roles de un usuario. Payload: { id_usuario, roles: [id_rol, ...] }
     */
    public static function sincronizarUsuario()
    {
        $db = Flight::db();
        try {
            $db->beginTransaction();

            $idUsuario = Flight::request()->data['id_usuario'];
            $roles = Flight::request()->data['roles'];
            if (!is_array($roles)) {
                $roles = [];
            }

            $actualStmt = $db->prepare("SELECT id_rol FROM roles_x_usuario WHERE id_usuario = :id_usuario AND id_tenant = :id_tenant");
            $actualStmt->bindParam(':id_usuario', $idUsuario);
            $actualStmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $actualStmt->execute();
            $actuales = $actualStmt->fetchAll(PDO::FETCH_COLUMN);

            $insertar = array_diff($roles, $actuales);
            $eliminar = array_diff($actuales, $roles);

            $insStmt = $db->prepare("INSERT INTO roles_x_usuario(id, id_tenant, id_rol, id_usuario) VALUES (:id, :id_tenant, :id_rol, :id_usuario)");
            foreach ($insertar as $idRol) {
                $insStmt->execute([
                    ':id' => Uuid::generar(),
                    ':id_tenant' => TenantContext::id(),
                    ':id_rol' => $idRol,
                    ':id_usuario' => $idUsuario
                ]);
            }

            if (count($eliminar) > 0) {
                $marcadores = implode(',', array_fill(0, count($eliminar), '?'));
                $delStmt = $db->prepare("DELETE FROM roles_x_usuario WHERE id_usuario = ? AND id_tenant = ? AND id_rol IN ($marcadores)");
                $delStmt->execute(array_merge([$idUsuario, TenantContext::id()], array_values($eliminar)));
            }

            $db->commit();
            Flight::json(['id_usuario' => $idUsuario, 'insertados' => count($insertar), 'eliminados' => count($eliminar)]);
        } catch (Exception $e) {
            $db->rollBack();
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

}
