<?php
class TiposContrato {
    
    public static function getAll() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    aplica_nomina,
                    requiere_fecha_fin,
                    descripcion,
                    activo
                FROM tipos_contrato
                WHERE id_tenant = :id_tenant
                ORDER BY nombre ASC, nombre ASC
            ");
            
            $stmt->execute(['id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener tipos de contrato',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function getActivos() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    aplica_nomina,
                    requiere_fecha_fin,
                    descripcion,
                    activo
                FROM tipos_contrato
                WHERE activo = 1
                AND id_tenant = :id_tenant
                ORDER BY nombre ASC
            ");
            
            $stmt->execute(['id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener tipos de contrato activos',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    id,
                    codigo,
                    nombre,
                    aplica_nomina,
                    requiere_fecha_fin,
                    descripcion,
                    activo
                FROM tipos_contrato
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            
            $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener tipo de contrato',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['codigo']) || !isset($data['nombre'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            $id = Uuid::generar();
            
            $stmt = $db->prepare("
                INSERT INTO tipos_contrato (
                    id,
                    id_tenant,
                    codigo,
                    nombre,
                    aplica_nomina,
                    requiere_fecha_fin,
                    descripcion,
                    activo
                ) VALUES (
                    :id,
                    :id_tenant,
                    :codigo,
                    :nombre,
                    :aplica_nomina,
                    :requiere_fecha_fin,
                    :descripcion,
                    :activo
                )
            ");
            
            $stmt->execute([
                'id' => $id,
                'id_tenant' => TenantContext::id(),
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'aplica_nomina' => $data['aplica_nomina'] ?? 1,
                'requiere_fecha_fin' => $data['requiere_fecha_fin'] ?? 0,
                'descripcion' => $data['descripcion'] ?? null,
                'activo' => $data['activo'] ?? 1
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Tipo de contrato creado correctamente',
                'id' => $id
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear tipo de contrato',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function replace() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                UPDATE tipos_contrato 
                SET 
                    codigo = :codigo,
                    nombre = :nombre,
                    aplica_nomina = :aplica_nomina,
                    requiere_fecha_fin = :requiere_fecha_fin,
                    descripcion = :descripcion,
                    activo = :activo
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'id_tenant' => TenantContext::id(),
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'aplica_nomina' => $data['aplica_nomina'],
                'requiere_fecha_fin' => $data['requiere_fecha_fin'] ?? 0,
                'descripcion' => $data['descripcion'] ?? null,
                'activo' => $data['activo']
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Tipo de contrato actualizado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar tipo de contrato',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM tipos_contrato WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Tipo de contrato eliminado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar tipo de contrato',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>