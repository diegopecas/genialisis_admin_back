<?php
class PermisosRol
{
    /**
     * Obtiene todos los roles activos del tenant
     */
    public static function getRoles()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            

            $db = Flight::db();
            $stmt = $db->prepare("SELECT id, nombre FROM roles WHERE id_tenant = :id_tenant ORDER BY nombre");
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $roles = $stmt->fetchAll();
            Flight::json($roles);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene los portales distintos que existen en la tabla permisos
     */
    public static function getPortales()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            

            // Los portales válidos del sistema. 'ambos' NO es un portal: es el
            // marcador de un permiso que aplica a los dos, y el filtrado del árbol
            // ya lo resuelve con (portal = :portal OR portal = 'ambos').
            $portales = [JWTService::PORTAL_INSTITUCIONAL, JWTService::PORTAL_PADRES];
            Flight::json($portales);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el árbol completo de módulos con sus permisos filtrado por portal
     * opciones_sistema y permisos se leen de db_master
     */
    public static function getArbol()
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            

            $portal = isset($_GET['portal']) ? $_GET['portal'] : 'institucional';
            $dbMaster = Flight::db_master();

            $stmtModulos = $dbMaster->prepare("
                SELECT id, id_padre, nombre, ruta, icono, orden 
                FROM opciones_sistema  
                WHERE activo = 1 AND (portal = :portal OR portal = 'ambos')
                ORDER BY orden, nombre
            ");
            $stmtModulos->bindParam(':portal', $portal);
            $stmtModulos->execute();
            $modulos = $stmtModulos->fetchAll();

            $stmtPermisos = $dbMaster->prepare("
                SELECT id, id_modulo, codigo, nombre 
                FROM permisos 
                WHERE activo = 1 AND (portal = :portal OR portal = 'ambos')
                ORDER BY codigo
            ");
            $stmtPermisos->bindParam(':portal', $portal);
            $stmtPermisos->execute();
            $permisos = $stmtPermisos->fetchAll();

            $permisosPorModulo = [];
            foreach ($permisos as $p) {
                $idModulo = $p['id_modulo'] ?? 0;
                if (!isset($permisosPorModulo[$idModulo])) {
                    $permisosPorModulo[$idModulo] = [];
                }
                $permisosPorModulo[$idModulo][] = [
                    'id' => $p['id'],
                    'codigo' => $p['codigo'],
                    'nombre' => $p['nombre']
                ];
            }

            $arbol = self::construirArbol($modulos, $permisosPorModulo, null);

            Flight::json($arbol);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Construye el árbol recursivo de módulos con permisos
     */
    private static function construirArbol($modulos, $permisosPorModulo, $idPadre)
    {
        $resultado = [];

        foreach ($modulos as $modulo) {
            if ($modulo['id_padre'] == $idPadre) {
                $nodo = [
                    'id' => $modulo['id'],
                    'nombre' => $modulo['nombre'],
                    'icono' => $modulo['icono'],
                    'permisos' => $permisosPorModulo[$modulo['id']] ?? [],
                    'hijos' => self::construirArbol($modulos, $permisosPorModulo, $modulo['id'])
                ];
                $resultado[] = $nodo;
            }
        }

        return $resultado;
    }

    /**
     * Obtiene los códigos de permisos asignados a un rol (del tenant)
     */
    public static function getPermisosByRol($idRol)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            

            $db = Flight::db();
            $stmt = $db->prepare("
                SELECT codigo_permiso 
                FROM permisos_x_rol 
                WHERE id_rol = :id_rol
                AND id_tenant = :id_tenant
            ");
            $stmt->bindParam(':id_rol', $idRol);
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $permisos = $stmt->fetchAll(PDO::FETCH_COLUMN);

            Flight::json($permisos);
        } catch (Exception $e) {
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda los permisos de un rol usando códigos
     */
    public static function guardarPermisos($idRol)
    {
        try {
            $userData = JWTService::requerirAutenticacion();
            

            $db = Flight::db();
            $db->beginTransaction();

            $nuevosCodigos = Flight::request()->data['permisos'] ?? [];

            // 1. Obtener codigos actuales del rol para historial
            $stmtActuales = $db->prepare("
                SELECT codigo_permiso 
                FROM permisos_x_rol 
                WHERE id_rol = :id_rol
                AND id_tenant = :id_tenant
            ");
            $stmtActuales->bindParam(':id_rol', $idRol);
            $stmtActuales->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtActuales->execute();
            $codigosAnteriores = $stmtActuales->fetchAll(PDO::FETCH_COLUMN);

            // 2. Guardar historial
            $stmtHistorial = $db->prepare("
                INSERT INTO permisos_historial (id, id_tenant, id_rol, permisos_anteriores, permisos_nuevos, id_usuario_modifico, fecha)
                VALUES (:id, :id_tenant, :id_rol, :anteriores, :nuevos, :id_usuario, NOW())
            ");
            $idHist = Uuid::generar();
            $stmtHistorial->bindValue(':id', $idHist);
            $stmtHistorial->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtHistorial->bindParam(':id_rol', $idRol);
            $anterioresJson = json_encode($codigosAnteriores, JSON_UNESCAPED_UNICODE);
            $nuevosJson = json_encode($nuevosCodigos, JSON_UNESCAPED_UNICODE);
            $stmtHistorial->bindParam(':anteriores', $anterioresJson);
            $stmtHistorial->bindParam(':nuevos', $nuevosJson);
            $idUsuario = $userData->id;
            $stmtHistorial->bindParam(':id_usuario', $idUsuario);
            $stmtHistorial->execute();

            // 3. Borrar permisos actuales del rol
            $stmtBorrar = $db->prepare("DELETE FROM permisos_x_rol WHERE id_rol = :id_rol AND id_tenant = :id_tenant");
            $stmtBorrar->bindParam(':id_rol', $idRol);
            $stmtBorrar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtBorrar->execute();

            // 4. Insertar nuevos permisos por código
            if (!empty($nuevosCodigos)) {
                $stmtInsertar = $db->prepare("INSERT INTO permisos_x_rol (id, id_tenant, id_rol, codigo_permiso) VALUES (:id, :id_tenant, :id_rol, :codigo_permiso)");
                foreach ($nuevosCodigos as $codigo) {
                    $idPxr = Uuid::generar();
                    $stmtInsertar->bindValue(':id', $idPxr);
                    $stmtInsertar->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
                    $stmtInsertar->bindParam(':id_rol', $idRol);
                    $stmtInsertar->bindParam(':codigo_permiso', $codigo);
                    $stmtInsertar->execute();
                }
            }

            $db->commit();

            Flight::json([
                'success' => true,
                'message' => 'Permisos actualizados correctamente',
                'total_permisos' => count($nuevosCodigos)
            ]);
        } catch (Exception $e) {
            $db->rollBack();
            error_log("❌ Error guardando permisos del rol {$idRol}: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }
}