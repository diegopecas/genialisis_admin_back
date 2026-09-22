<?php
/**
 * Resuelve la carpeta de configuracion segun el entorno y define CONFIG_DIR.
 *
 * Sin la variable de entorno GENIALISIS_ENV se usa config/ (hosting y cron de
 * cPanel quedan igual). Con GENIALISIS_ENV=qa|prod|demo se usa config_{entorno}/.
 *
 * Arranque local:
 *   PowerShell: $env:GENIALISIS_ENV="qa"; C:/xampp/php/php -S localhost:1111
 *   cmd:        set "GENIALISIS_ENV=qa" && C:/xampp/php/php -S localhost:1111
 *
 * En PowerShell la variable queda viva en esa terminal. Para volver a config/:
 *   Remove-Item Env:GENIALISIS_ENV
 */

if (!defined('CONFIG_DIR')) {
    $entornoConfig = strtolower(trim((string) getenv('GENIALISIS_ENV')));

    if ($entornoConfig === '') {
        define('CONFIG_DIR', __DIR__ . '/config');
    } else {
        $errorConfig = null;

        // Solo letras, numeros, guion y guion bajo: evita salir de la raiz con ../
        if (!preg_match('/^[a-z0-9_-]+$/', $entornoConfig)) {
            $errorConfig = "GENIALISIS_ENV invalido: '{$entornoConfig}'";
        } elseif (!is_dir(__DIR__ . '/config_' . $entornoConfig)) {
            $errorConfig = "No existe la carpeta config_{$entornoConfig} para GENIALISIS_ENV={$entornoConfig}";
        }

        // Si el entorno esta mal escrito se corta aqui con un error claro,
        // en vez de caer en silencio a config/.
        if ($errorConfig !== null) {
            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, $errorConfig . "\n");
                exit(1);
            }
            error_log($errorConfig);
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => $errorConfig,
                'code' => 'CONFIG_ENV_INVALIDO'
            ], JSON_UNESCAPED_UNICODE);
            exit(1);
        }

        define('CONFIG_DIR', __DIR__ . '/config_' . $entornoConfig);
    }

    unset($entornoConfig, $errorConfig);
}
