<?php
class NominasDetalle {
    
    /**
     * Obtener todos los detalles de una nómina
     */
    public static function getByNomina($idNomina) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    nd.*,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_colaborador,
                    cn.codigo as codigo_concepto,
                    cn.nombre as nombre_concepto,
                    cn.es_suma
                FROM nominas_detalle nd
                INNER JOIN colaboradores c ON nd.id_colaborador = c.id
                INNER JOIN personas p ON c.id_persona = p.id
                INNER JOIN conceptos_nomina cn ON nd.id_concepto = cn.id
                WHERE nd.id_nomina = :id_nomina
                  AND nd.id_tenant = :id_tenant
                ORDER BY p.primer_nombre, p.primer_apellido, cn.orden ASC
            ");
            
            $stmt->execute(['id_nomina' => $idNomina, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener detalle de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener detalle agrupado por colaborador
     */
    public static function getAgrupadoPorColaborador($idNomina) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    nd.id_colaborador,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_colaborador,
                    c.documento,
                    SUM(CASE WHEN cn.es_suma = 1 THEN nd.valor_total ELSE 0 END) as total_devengado,
                    SUM(CASE WHEN cn.es_suma = 0 THEN nd.valor_total ELSE 0 END) as total_deducciones,
                    SUM(CASE WHEN cn.es_suma = 1 THEN nd.valor_total ELSE -nd.valor_total END) as neto_pagar
                FROM nominas_detalle nd
                INNER JOIN colaboradores c ON nd.id_colaborador = c.id
                INNER JOIN personas p ON c.id_persona = p.id
                INNER JOIN conceptos_nomina cn ON nd.id_concepto = cn.id
                WHERE nd.id_nomina = :id_nomina
                  AND nd.id_tenant = :id_tenant
                GROUP BY nd.id_colaborador, nombre_colaborador, c.documento
                ORDER BY nombre_colaborador ASC
            ");
            
            $stmt->execute(['id_nomina' => $idNomina, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener resumen por colaborador',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener detalle de un colaborador específico en una nómina
     */
    public static function getByColaborador($idNomina, $idColaborador) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    nd.*,
                    cn.codigo as codigo_concepto,
                    cn.nombre as nombre_concepto,
                    cn.es_suma,
                    cn.orden
                FROM nominas_detalle nd
                INNER JOIN conceptos_nomina cn ON nd.id_concepto = cn.id
                WHERE nd.id_nomina = :id_nomina 
                  AND nd.id_colaborador = :id_colaborador
                  AND nd.id_tenant = :id_tenant
                ORDER BY cn.orden ASC
            ");
            
            $stmt->execute([
                'id_nomina' => $idNomina,
                'id_colaborador' => $idColaborador,
                'id_tenant' => TenantContext::id()
            ]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener detalle del colaborador',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtener un detalle por ID
     */
    public static function getById($id) {
        try {
            $db = Flight::db();
            
            $stmt = $db->prepare("
                SELECT 
                    nd.*,
                    CONCAT_WS(' ', p.primer_nombre, p.primer_apellido) as nombre_colaborador,
                    cn.codigo as codigo_concepto,
                    cn.nombre as nombre_concepto,
                    cn.es_suma
                FROM nominas_detalle nd
                INNER JOIN colaboradores c ON nd.id_colaborador = c.id
                INNER JOIN personas p ON c.id_persona = p.id
                INNER JOIN conceptos_nomina cn ON nd.id_concepto = cn.id
                WHERE nd.id = :id
                  AND nd.id_tenant = :id_tenant
            ");
            
            $stmt->execute(['id' => $id, 'id_tenant' => TenantContext::id()]);
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            Flight::json($result, 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al obtener detalle',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear nuevo detalle de nómina
     */
    public static function new() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id_nomina']) || !isset($data['id_colaborador']) || 
                !isset($data['id_concepto']) || !isset($data['valor_unitario'])) {
                Flight::json(['error' => 'Datos incompletos'], 400);
                return;
            }
            
            $db = Flight::db();
            
            // Calcular valor_total
            $cantidad = $data['cantidad'] ?? 1;
            $valorTotal = $cantidad * $data['valor_unitario'];
            
            $stmt = $db->prepare("
                INSERT INTO nominas_detalle (
                    id,
                    id_tenant,
                    id_nomina,
                    id_colaborador,
                    id_concepto,
                    cantidad,
                    valor_unitario,
                    valor_total,
                    observaciones,
                    id_contabilizacion
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_nomina,
                    :id_colaborador,
                    :id_concepto,
                    :cantidad,
                    :valor_unitario,
                    :valor_total,
                    :observaciones,
                    :id_contabilizacion
                )
            ");
            
            $idDetalle = Uuid::generar();
            $stmt->execute([
                'id' => $idDetalle,
                'id_tenant' => TenantContext::id(),
                'id_nomina' => $data['id_nomina'],
                'id_colaborador' => $data['id_colaborador'],
                'id_concepto' => $data['id_concepto'],
                'cantidad' => $cantidad,
                'valor_unitario' => $data['valor_unitario'],
                'valor_total' => $valorTotal,
                'observaciones' => $data['observaciones'] ?? null,
                'id_contabilizacion' => $data['id_contabilizacion'] ?? null
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Detalle de nómina creado correctamente',
                'id' => $idDetalle
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al crear detalle de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear múltiples detalles en una sola transacción
     */
    public static function newMultiple() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['detalles']) || !is_array($data['detalles']) || count($data['detalles']) === 0) {
                Flight::json(['error' => 'Debe proporcionar un array de detalles'], 400);
                return;
            }
            
            $db = Flight::db();
            
            // Iniciar transacción
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO nominas_detalle (
                    id,
                    id_tenant,
                    id_nomina,
                    id_colaborador,
                    id_concepto,
                    cantidad,
                    valor_unitario,
                    valor_total,
                    observaciones,
                    id_contabilizacion
                ) VALUES (
                    :id,
                    :id_tenant,
                    :id_nomina,
                    :id_colaborador,
                    :id_concepto,
                    :cantidad,
                    :valor_unitario,
                    :valor_total,
                    :observaciones,
                    :id_contabilizacion
                )
            ");
            
            $insertados = 0;
            foreach ($data['detalles'] as $detalle) {
                if (!isset($detalle['id_nomina']) || !isset($detalle['id_colaborador']) || 
                    !isset($detalle['id_concepto']) || !isset($detalle['valor_unitario'])) {
                    continue;
                }
                
                $cantidad = $detalle['cantidad'] ?? 1;
                $valorTotal = $cantidad * $detalle['valor_unitario'];
                
                $idDetalle = Uuid::generar();
                $stmt->execute([
                    'id' => $idDetalle,
                    'id_tenant' => TenantContext::id(),
                    'id_nomina' => $detalle['id_nomina'],
                    'id_colaborador' => $detalle['id_colaborador'],
                    'id_concepto' => $detalle['id_concepto'],
                    'cantidad' => $cantidad,
                    'valor_unitario' => $detalle['valor_unitario'],
                    'valor_total' => $valorTotal,
                    'observaciones' => $detalle['observaciones'] ?? null,
                    'id_contabilizacion' => $detalle['id_contabilizacion'] ?? null
                ]);
                
                $insertados++;
            }
            
            // Confirmar transacción
            $db->commit();
            
            Flight::json([
                'success' => true,
                'message' => "Se crearon {$insertados} detalles de nómina correctamente",
                'insertados' => $insertados
            ], 200);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            
            Flight::json([
                'error' => 'Error al crear detalles de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Actualizar detalle de nómina
     */
    public static function replace() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            // Calcular valor_total
            $cantidad = $data['cantidad'] ?? 1;
            $valorTotal = $cantidad * $data['valor_unitario'];
            
            $stmt = $db->prepare("
                UPDATE nominas_detalle 
                SET 
                    id_concepto = :id_concepto,
                    cantidad = :cantidad,
                    valor_unitario = :valor_unitario,
                    valor_total = :valor_total,
                    observaciones = :observaciones,
                    id_contabilizacion = :id_contabilizacion
                WHERE id = :id AND id_tenant = :id_tenant
            ");
            
            $stmt->execute([
                'id' => $data['id'],
                'id_tenant' => TenantContext::id(),
                'id_concepto' => $data['id_concepto'],
                'cantidad' => $cantidad,
                'valor_unitario' => $data['valor_unitario'],
                'valor_total' => $valorTotal,
                'observaciones' => $data['observaciones'] ?? null,
                'id_contabilizacion' => $data['id_contabilizacion'] ?? null
            ]);
            
            Flight::json([
                'success' => true,
                'message' => 'Detalle de nómina actualizado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al actualizar detalle de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar detalle de nómina
     */
    public static function delete() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id'])) {
                Flight::json(['error' => 'ID no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM nominas_detalle WHERE id = :id AND id_tenant = :id_tenant");
            $stmt->execute(['id' => $data['id'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Detalle de nómina eliminado correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar detalle de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Eliminar todos los detalles de una nómina
     */
    public static function deleteByNomina() {
        try {
            $data = Flight::request()->data->getData();
            
            if (!isset($data['id_nomina'])) {
                Flight::json(['error' => 'ID de nómina no proporcionado'], 400);
                return;
            }
            
            $db = Flight::db();
            
            $stmt = $db->prepare("DELETE FROM nominas_detalle WHERE id_nomina = :id_nomina AND id_tenant = :id_tenant");
            $stmt->execute(['id_nomina' => $data['id_nomina'], 'id_tenant' => TenantContext::id()]);
            
            Flight::json([
                'success' => true,
                'message' => 'Detalles de nómina eliminados correctamente'
            ], 200);
            
        } catch (Exception $e) {
            Flight::json([
                'error' => 'Error al eliminar detalles de nómina',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
?>
