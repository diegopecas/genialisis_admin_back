<?php
class Nominas {
    
    /**
     * Obtener todas las nóminas
     */
    public static function getAll() {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    n.*,
                    en.nombre as nombre_estado,
                    CONCAT_WS(' ', pg.primer_nombre, pg.primer_apellido) as nombre_usuario_genera,
                    CONCAT_WS(' ', pc.primer_nombre, pc.primer_apellido) as nombre_usuario_cierra
                FROM nominas n
                INNER JOIN estados_nomina en ON n.id_estado = en.id
                INNER JOIN usuarios ug ON n.id_usuario_genera = ug.id
                INNER JOIN personas pg ON ug.id_persona = pg.id
                LEFT JOIN usuarios uc ON n.id_usuario_cierra = uc.id
                LEFT JOIN personas pc ON uc.id_persona = pc.id
                WHERE n.id_tenant = :id_tenant
                ORDER BY n.fecha_inicio DESC
            ");
            
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener nóminas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener una nómina por ID
     */
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    n.*,
                    en.nombre as nombre_estado,
                    CONCAT_WS(' ', pg.primer_nombre, pg.primer_apellido) as nombre_usuario_genera,
                    CONCAT_WS(' ', pc.primer_nombre, pc.primer_apellido) as nombre_usuario_cierra
                FROM nominas n
                INNER JOIN estados_nomina en ON n.id_estado = en.id
                INNER JOIN usuarios ug ON n.id_usuario_genera = ug.id
                INNER JOIN personas pg ON ug.id_persona = pg.id
                LEFT JOIN usuarios uc ON n.id_usuario_cierra = uc.id
                LEFT JOIN personas pc ON uc.id_persona = pc.id
                WHERE n.id = :id
                AND n.id_tenant = :id_tenant
            ");
            
            $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener nóminas activas (para selección en pagos)
     */
    public static function getActivas() {
        try {
            $db = Flight::db();
            
            // Asumiendo que estado 1 = Activa/Generada, 2 = Cerrada, 3 = Pagada
            $stmt = $db->prepare("
                SELECT 
                    n.id,
                    n.periodo,
                    n.fecha_inicio,
                    n.fecha_fin,
                    n.id_estado,
                    en.nombre as nombre_estado,
                    CONCAT(n.periodo, ' (', DATE_FORMAT(n.fecha_inicio, '%d/%m/%Y'), ' - ', 
                           DATE_FORMAT(n.fecha_fin, '%d/%m/%Y'), ')') as nombre_completo
                FROM nominas n
                INNER JOIN estados_nomina en ON n.id_estado = en.id
                WHERE n.id_estado IN (1, 2)
                AND n.id_tenant = :id_tenant
                ORDER BY n.fecha_inicio DESC
                LIMIT 50
            ");
            
            $stmt->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener nóminas activas',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear nueva nómina
     */
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['periodo']) || !isset($data['fecha_inicio']) || 
                !isset($data['fecha_fin']) || !isset($data['id_usuario_genera'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                INSERT INTO nominas (
                    id,
                    id_tenant,
                    periodo,
                    fecha_inicio,
                    fecha_fin,
                    id_estado,
                    id_usuario_genera,
                    observaciones,
                    fecha_generacion
                ) VALUES (
                    :id,
                    :id_tenant,
                    :periodo,
                    :fecha_inicio,
                    :fecha_fin,
                    :id_estado,
                    :id_usuario_genera,
                    :observaciones,
                    NOW()
                )
            ");
            
            $idNomina = Uuid::generar();
            $stmt->execute([
                'id' => $idNomina,
                'id_tenant' => TenantContext::id(),
                'periodo' => $data['periodo'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'id_estado' => $data['id_estado'] ?? 1,
                'id_usuario_genera' => $data['id_usuario_genera'],
                'observaciones' => $data['observaciones'] ?? null
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina creada correctamente',
                'id' => $idNomina
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar nómina
     */
    public static function replace() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                UPDATE nominas 
                SET 
                    periodo = :periodo,
                    fecha_inicio = :fecha_inicio,
                    fecha_fin = :fecha_fin,
                    id_estado = :id_estado,
                    observaciones = :observaciones
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'id_tenant' => TenantContext::id(),
                'periodo' => $data['periodo'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'id_estado' => $data['id_estado'],
                'observaciones' => $data['observaciones'] ?? null
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina actualizada correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Cerrar nómina
     */
    public static function cerrar() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id']) || !isset($data['id_usuario_cierra'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                UPDATE nominas 
                SET 
                    id_estado = 2,
                    fecha_cierre = NOW(),
                    id_usuario_cierra = :id_usuario_cierra
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'id_usuario_cierra' => $data['id_usuario_cierra'],
                'id_tenant' => TenantContext::id()
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina cerrada correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al cerrar nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Marcar nómina como pagada
     */
    public static function marcarPagada() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("
                UPDATE nomina 
                SET 
                    id_estado = 3,
                    fecha_pago = NOW()
                WHERE id = :id
                AND id_tenant = :id_tenant
            ");
            
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina marcada como pagada'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al marcar nómina como pagada',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar nómina
     */
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM nominas WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Nómina eliminada correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>