<?php
// ===================================================================
// CLAVE SECRETA JWT - NO VERSIONAR (agregar a .gitignore)
//
// Debe ser IDENTICA en todos los entornos para que un token firmado en
// uno se valide en cualquier otro. Se mantiene el mismo valor que estaba
// hardcodeado en jwt.service.php para no invalidar los tokens vigentes.
// ===================================================================
define('JWT_SECRET_KEY', 'LuM3n_4c4d3m1c0_2024_S3cr3t_K3y_Pr0t3ct3d_G4l3r14s_X7k9Lm2Pq');

// ===================================================================
// WEBAUTHN / LOGIN BIOMETRICO - interruptor global
//
// Vive aqui, y no en master.env.php, porque este archivo se carga antes
// del registro de rutas (index.php) y webauthn.routes.php consulta la
// constante al registrarse.
//
// false = PAUSADO. Las rutas responden 403 WEBAUTHN_DISABLED y el front
//         oculta el boton. No se borra codigo ni credenciales: al volver
//         a true, todo queda como estaba.
//
// Motivo de la pausa: la ruta /auth/webauthn (login biometrico sin tenant)
// corre antes de que index.php cargue el contexto del tenant, asi que el
// token que emite no puede incluir el sello hd_ok de habeas data y el
// middleware no lo bloquea. Reactivar solo cuando ese flujo cargue el
// tenant antes de generar el token.
// ===================================================================
define('WEBAUTHN_ACTIVO', false);