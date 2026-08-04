<?php

/**
 * Servicio de la tabla ia_chat_contexto_cache.
 *
 * Guarda una "foto" del contexto del jardín (JSON) por tenant y fecha, para que
 * el chat IA no tenga que recalcular los resúmenes/detalle del dashboard en cada
 * mensaje. Una fila por (tenant, fecha): al guardar se hace upsert.
 *
 * Vigencia:
 *   - Fechas pasadas: los datos ya no cambian, la foto NO vence.
 *   - Fecha de hoy: aplica el TTL configurable (contexto_cache_ttl_min).
 *
 * La decisión de qué contiene el JSON vive en IaChat; aquí solo está el CRUD.
 * Las fechas de cálculo se manejan en UTC (UTC_TIMESTAMP) para que la vigencia
 * no dependa de la zona horaria de sesión.
 */
class IaChatContextoCache
{
    /**
     * Retorna el JSON de la foto para (tenant, $fecha) si está vigente.
     * Para fechas pasadas siempre está vigente (los datos no cambian). Para hoy
     * aplica $ttlMin minutos; si $ttlMin <= 0 no se usa caché para hoy.
     * Retorna null si no hay foto vigente.
     */
    public static function obtenerVigente($db, $fecha, $ttlMin)
    {
        $esPasado = ($fecha < date('Y-m-d'));

        if ($esPasado) {
            $sentence = $db->prepare("SELECT contexto 
                FROM ia_chat_contexto_cache 
                WHERE id_tenant = :id_tenant AND fecha = :fecha 
                LIMIT 1");
            $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
            $sentence->bindParam(':fecha', $fecha);
            $sentence->execute();
            $row = $sentence->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['contexto'] : null;
        }

        // Fecha de hoy: aplica TTL. Si es <= 0, no se usa caché.
        $ttlMin = (int) $ttlMin;
        if ($ttlMin <= 0) {
            return null;
        }

        $sentence = $db->prepare("SELECT contexto 
            FROM ia_chat_contexto_cache 
            WHERE id_tenant = :id_tenant AND fecha = :fecha 
              AND TIMESTAMPADD(MINUTE, :ttl, fecha_calculo) >= UTC_TIMESTAMP() 
            LIMIT 1");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindValue(':ttl', $ttlMin, PDO::PARAM_INT);
        $sentence->execute();
        $row = $sentence->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['contexto'] : null;
    }

    /**
     * Guarda (upsert) la foto de (tenant, $fecha) y actualiza fecha_calculo.
     */
    public static function guardar($db, $fecha, $contextoJson)
    {
        $sentence = $db->prepare("INSERT INTO ia_chat_contexto_cache (id, id_tenant, fecha, contexto, fecha_calculo) 
            VALUES (:id, :id_tenant, :fecha, :contexto, UTC_TIMESTAMP()) 
            ON DUPLICATE KEY UPDATE contexto = VALUES(contexto), fecha_calculo = UTC_TIMESTAMP()");
        $id = Uuid::generar();
        $sentence->bindValue(':id', $id);
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->bindParam(':fecha', $fecha);
        $sentence->bindParam(':contexto', $contextoJson);
        $sentence->execute();
    }

    /**
     * Elimina las fotos del tenant (todas las fechas). Fuerza recálculo.
     */
    public static function eliminar($db)
    {
        $sentence = $db->prepare("DELETE FROM ia_chat_contexto_cache WHERE id_tenant = :id_tenant");
        $sentence->bindValue(':id_tenant', TenantContext::id(), PDO::PARAM_INT);
        $sentence->execute();
    }
}