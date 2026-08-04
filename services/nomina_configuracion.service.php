<?php
class NominaConfiguracion {
    
    /**
     * Obtener todas las configuraciones
     */
    public static function getAll() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo,
                    fecha_creacion
                FROM nomina_configuracion
                WHERE id_tenant = :id_tenant
                ORDER BY anio DESC, nombre ASC
            ");
            
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener configuraciones de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener configuraciones activas
     */
    public static function getActivas() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo,
                    fecha_creacion
                FROM nomina_configuracion
                WHERE activo = 1
                AND id_tenant = :id_tenant
                ORDER BY anio DESC, nombre ASC
            ");
            
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener configuraciones activas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener configuraciones por año
     */
    public static function getByAnio($anio) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo,
                    fecha_creacion
                FROM nomina_configuracion
                WHERE anio = :anio AND activo = 1 AND id_tenant = :id_tenant
                ORDER BY nombre ASC
            ");
            
            $stmt->execute(['anio' => $anio, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener configuraciones por año',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener una configuración por ID
     */
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo,
                    fecha_creacion
                FROM nomina_configuracion
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            
            $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener configuración',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener configuración por código y año
     */
    public static function getByCodigo($codigo, $anio = null) {
        try {
            $db = Flight::db();
            
            // Si no se especifica año, usar el año actual
            if ($anio === null) {
                $anio = date('Y');
            }
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo,
                    fecha_creacion
                FROM nomina_configuracion
                WHERE codigo = :codigo AND anio = :anio AND activo = 1 AND id_tenant = :id_tenant
                LIMIT 1
            ");
            
            $stmt->execute([
                'codigo' => $codigo,
                'anio' => $anio,
                'id_tenant' => TenantContext::id()
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener configuración por código',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear nueva configuración
     */
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['codigo']) || !isset($data['nombre']) || 
                !isset($data['valor']) || !isset($data['anio'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            // Verificar si ya existe la combinación código-año
            $stmt = $db->prepare("
                SELECT COUNT(*) as existe 
                FROM nomina_configuracion 
                WHERE codigo = :codigo AND anio = :anio AND id_tenant = :id_tenant
            ");
            $stmt->execute([
                'codigo' => $data['codigo'],
                'anio' => $data['anio'],
                'id_tenant' => TenantContext::id()
            ]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['existe'] > 0) {
                Flight::json([
                    'error' => 'Ya existe una configuración con ese código para el año ' . $data['anio']
                ], 400);
                return;
            }
            
            $stmt = $db->prepare("
                INSERT INTO nomina_configuracion (
                    id,
                    id_tenant,
                    codigo,
                    nombre,
                    descripcion,
                    valor,
                    anio,
                    activo
                ) VALUES (
                    :id,
                    :id_tenant,
                    :codigo,
                    :nombre,
                    :descripcion,
                    :valor,
                    :anio,
                    :activo
                )
            ");
            
            $idConfig = Uuid::generar();
            $stmt->execute([
                'id' => $idConfig,
                'id_tenant' => TenantContext::id(),
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'valor' => $data['valor'],
                'anio' => $data['anio'],
                'activo' => $data['activo'] ?? 1
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Configuración creada correctamente',
                'id' => $idConfig
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear configuración',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar configuración
     */
    public static function replace() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            // Verificar si existe otra configuración con el mismo código-año (excepto la actual)
            $stmt = $db->prepare("
                SELECT COUNT(*) as existe 
                FROM nomina_configuracion 
                WHERE codigo = :codigo AND anio = :anio AND id != :id AND id_tenant = :id_tenant
            ");
            $stmt->execute([
                'codigo' => $data['codigo'],
                'anio' => $data['anio'],
                'id' => $data['id'],
                'id_tenant' => TenantContext::id()
            ]);
            $existe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existe['existe'] > 0) {
                Flight::json([
                    'error' => 'Ya existe otra configuración con ese código para el año ' . $data['anio']
                ], 400);
                return;
            }
            
            $stmt = $db->prepare("
                UPDATE nomina_configuracion 
                SET 
                    codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    valor = :valor,
                    anio = :anio,
                    activo = :activo
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'id_tenant' => TenantContext::id(),
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'] ?? null,
                'valor' => $data['valor'],
                'anio' => $data['anio'],
                'activo' => $data['activo']
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Configuración actualizada correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar configuración',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar configuración
     */
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM nomina_configuracion WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Configuración eliminada correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar configuración',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>
