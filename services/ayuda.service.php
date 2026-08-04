<?php

class Ayuda
{
    /**
     * Obtiene el árbol de módulos filtrado por permisos del usuario y portal
     * opciones_sistema y permisos se leen de db_master
     * permisos_x_rol, roles_x_usuario se leen de db (tenant)
     */
    public static function getModulos()
    {
        try {
            $userData = JWTService::requerirAutenticacion();

            $dbMaster = Flight::db_master();
            $db = Flight::db();

            $portal = isset($_GET['portal']) ? $_GET['portal'] : 'institucional';
            $esSuperAdmin = isset($userData->super_admin) && (int)$userData->super_admin === 1;

            // Obtener todos los modulos activos de la maestra filtrados por portal
            $stmtOpciones = $dbMaster->prepare("
                SELECT id, id_padre, nombre, ruta, ruta_principal, descripcion, descripcion_texto, 
                       icono, orden, imagenes, tags, portal, activo
                FROM opciones_sistema
                WHERE activo = 1 AND (portal = :portal OR portal = 'ambos')
                ORDER BY orden, nombre
            ");
            $stmtOpciones->bindParam(':portal', $portal);
            $stmtOpciones->execute();
            $todosModulos = $stmtOpciones->fetchAll(PDO::FETCH_ASSOC);

            foreach ($todosModulos as &$m) {
                $m['imagenes'] = $m['imagenes'] ? json_decode($m['imagenes'], true) : [];
            }
            unset($m);

            if ($esSuperAdmin) {
                $arbol = self::construirArbolAyuda($todosModulos, null);
                Flight::json($arbol);
                return;
            }

            // Obtener códigos de permisos del usuario en el tenant
            $stmtPermisos = $db->prepare("
                SELECT DISTINCT pxr.codigo_permiso
                FROM roles_x_usuario ru
                INNER JOIN permisos_x_rol pxr ON ru.id_rol = pxr.id_rol
                WHERE ru.id_usuario = :id_usuario
                AND ru.id_tenant = :id_tenant
            ");
            $stmtPermisos->bindParam(':id_usuario', $userData->id);
            $stmtPermisos->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmtPermisos->execute();
            $codigosPermitidos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);

            if (empty($codigosPermitidos)) {
                Flight::json([]);
                return;
            }

            // Obtener IDs de módulos asociados a esos códigos
            $placeholders = implode(',', array_fill(0, count($codigosPermitidos), '?'));
            $stmtModulos = $dbMaster->prepare("
                SELECT DISTINCT id_modulo 
                FROM permisos 
                WHERE codigo IN ({$placeholders}) AND activo = 1 AND id_modulo IS NOT NULL
            ");
            $stmtModulos->execute($codigosPermitidos);
            $idsModulosPermitidos = array_map('intval', $stmtModulos->fetchAll(PDO::FETCH_COLUMN));

            if (empty($idsModulosPermitidos)) {
                Flight::json([]);
                return;
            }

            $idsCompletos = self::obtenerIdsConAncestros($todosModulos, $idsModulosPermitidos);
            $arbol = self::construirArbolAyudaFiltrado($todosModulos, null, $idsCompletos, $idsModulosPermitidos);

            Flight::json($arbol);
        } catch (Exception $e) {
            error_log("Error en Ayuda::getModulos: " . $e->getMessage());
            Flight::json(['error' => $e->getMessage()], 500);
        }
    }

    private static function construirArbolAyuda($modulos, $idPadre)
    {
        $resultado = [];
        foreach ($modulos as $modulo) {
            $moduloIdPadre = $modulo['id_padre'];
            if (($moduloIdPadre === null && $idPadre === null) || ($moduloIdPadre !== null && (int)$moduloIdPadre === (int)$idPadre)) {
                $nodo = $modulo;
                $nodo['hijos'] = self::construirArbolAyuda($modulos, $modulo['id']);
                $resultado[] = $nodo;
            }
        }
        return $resultado;
    }

    private static function construirArbolAyudaFiltrado($modulos, $idPadre, $idsCompletos, $idsConAcceso)
    {
        $resultado = [];
        foreach ($modulos as $modulo) {
            $moduloIdPadre = $modulo['id_padre'];
            $coincidePadre = ($moduloIdPadre === null && $idPadre === null) || ($moduloIdPadre !== null && (int)$moduloIdPadre === (int)$idPadre);

            if ($coincidePadre && in_array((int)$modulo['id'], $idsCompletos)) {
                $nodo = $modulo;
                $nodo['tiene_acceso'] = in_array((int)$modulo['id'], $idsConAcceso);
                $nodo['hijos'] = self::construirArbolAyudaFiltrado($modulos, $modulo['id'], $idsCompletos, $idsConAcceso);
                $resultado[] = $nodo;
            }
        }
        return $resultado;
    }

    private static function obtenerIdsConAncestros($modulos, $idsAcceso)
    {
        $modulosPorId = [];
        foreach ($modulos as $m) {
            $modulosPorId[(int)$m['id']] = $m;
        }

        $idsCompletos = [];
        foreach ($idsAcceso as $id) {
            $actual = $id;
            while ($actual !== null && !in_array($actual, $idsCompletos)) {
                $idsCompletos[] = $actual;
                if (isset($modulosPorId[$actual]) && $modulosPorId[$actual]['id_padre'] !== null) {
                    $actual = (int)$modulosPorId[$actual]['id_padre'];
                } else {
                    $actual = null;
                }
            }
        }

        return $idsCompletos;
    }
}