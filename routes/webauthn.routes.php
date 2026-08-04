<?php
// Biometrico CON tenant. Pausado mientras WEBAUTHN_ACTIVO sea false
// (ver config/master.env.php).
if (defined('WEBAUTHN_ACTIVO') && WEBAUTHN_ACTIVO === false) {
    $webauthnPausado = function () {
        Flight::halt(403, json_encode([
            'error' => 'El ingreso biométrico está temporalmente deshabilitado',
            'code'  => 'WEBAUTHN_DISABLED'
        ]));
    };

    // Registro de biométrico (requiere JWT)
    Flight::route('POST /webauthn/registro/opciones', $webauthnPausado);
    Flight::route('POST /webauthn/registro/verificar', $webauthnPausado);

    // Autenticación con biométrico CON tenant
    Flight::route('POST /webauthn/auth/opciones', $webauthnPausado);
    Flight::route('POST /webauthn/auth/verificar', $webauthnPausado);

    // Utilidades. 'disponible' responde false en vez de error: el front la usa
    // para decidir si ofrece el boton, y un false limpio es la respuesta correcta.
    Flight::route('POST /webauthn/disponible', function () {
        Flight::json(['disponible' => false, 'tiene_credenciales' => false]);
    });
    Flight::route('GET /webauthn/credenciales', $webauthnPausado);
    Flight::route('DELETE /webauthn/credenciales', $webauthnPausado);
    return;
}

// Registro de biométrico (requiere JWT)
Flight::route('POST /webauthn/registro/opciones', [WebAuthn::class, 'generarOpcionesRegistro']);
Flight::route('POST /webauthn/registro/verificar', [WebAuthn::class, 'verificarRegistro']);
 
// Autenticación con biométrico CON tenant (requiere tenant configurado)
Flight::route('POST /webauthn/auth/opciones', [WebAuthn::class, 'generarOpcionesAutenticacion']);
Flight::route('POST /webauthn/auth/verificar', [WebAuthn::class, 'verificarAutenticacion']);
 
// Utilidades (requieren tenant)
Flight::route('POST /webauthn/disponible', [WebAuthn::class, 'verificarDisponibilidad']);
Flight::route('GET /webauthn/credenciales', [WebAuthn::class, 'listarCredenciales']);
Flight::route('DELETE /webauthn/credenciales', [WebAuthn::class, 'eliminarCredencial']);