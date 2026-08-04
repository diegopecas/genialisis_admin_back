<?php
/**
 * Contexto de tenant centralizado.
 *
 * Punto unico para obtener el identificador del tenant activo. El id numerico
 * SIEMPRE proviene del servidor (constante TENANT_ID definida en
 * config/tenants/{codigo}.env.php), NUNCA del request. Si en el futuro cambia
 * el origen del tenant, solo se modifica esta clase y no los services.
 */
class TenantContext
{
    /** Codigo de tenant validado/sanitizado (ej. 'lumen'). Lo fija index.php. */
    private static $codigo = null;

    /**
     * Fija el codigo de tenant ya validado. Lo invoca index.php tras sanitizar
     * el header X-Tenant y cargar el archivo de configuracion del tenant.
     *
     * @param string $codigo
     * @return void
     */
    public static function setCodigo($codigo)
    {
        self::$codigo = $codigo;
    }

    /**
     * Devuelve el codigo de tenant activo (ej. 'lumen') o null si todavia no se
     * ha cargado el contexto (p. ej. en rutas publicas previas al router).
     *
     * @return string|null
     */
    public static function codigo()
    {
        return self::$codigo;
    }

    /**
     * Devuelve el id numerico del tenant activo. Sale exclusivamente de la
     * constante TENANT_ID del archivo de configuracion del tenant (lado
     * servidor). Falla cerrado: si no esta definida o es invalida, corta la
     * peticion con 500 en lugar de devolver datos sin aislar.
     *
     * @return int
     */
    public static function id()
    {
        if (!defined('TENANT_ID')) {
            self::abortarSinContexto();
        }

        $id = (int) TENANT_ID;

        if ($id <= 0) {
            self::abortarSinContexto();
        }

        return $id;
    }

    /**
     * Corta la ejecucion cuando no hay un TENANT_ID valido. Responde 500 sin
     * filtrar datos. No deberia alcanzarse en operacion normal.
     *
     * @return void
     */
    private static function abortarSinContexto()
    {
        error_log('TenantContext: TENANT_ID no esta definido o es invalido para el tenant activo.');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode([
            'error'   => true,
            'message' => 'Contexto de tenant no inicializado',
            'code'    => 'TENANT_CONTEXT_MISSING'
        ], JSON_UNESCAPED_UNICODE);
        exit(1);
    }
}
