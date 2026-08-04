<?php
// Login biometrico SIN tenant. Pausado mientras WEBAUTHN_ACTIVO sea false
// (ver config/master.env.php). Las rutas se registran igual para responder
// con un codigo claro en vez de un 404 confuso.
if (defined('WEBAUTHN_ACTIVO') && WEBAUTHN_ACTIVO === false) {
    $webauthnPausado = function () {
        Flight::halt(403, json_encode([
            'error' => 'El ingreso biométrico está temporalmente deshabilitado',
            'code'  => 'WEBAUTHN_DISABLED'
        ]));
    };

    Flight::route('POST /auth/webauthn/opciones', $webauthnPausado);
    Flight::route('POST /auth/webauthn/verificar', $webauthnPausado);
    return;
}

Flight::route('POST /auth/webauthn/opciones', [WebAuthn::class, 'generarOpcionesLoginDirecto']);
Flight::route('POST /auth/webauthn/verificar', [WebAuthn::class, 'verificarLoginDirecto']);