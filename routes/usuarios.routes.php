<?php
Flight::route('GET /usuarios', [Usuarios::class, 'getAll']);
Flight::route('POST /usuarios-auth', [Usuarios::class, 'getUsuarioByUsuarioAndClave']);
Flight::route('POST /usuarios/cambiar-clave', [Usuarios::class, 'cambiarClave']);
Flight::route('POST /usuarios/restablecer-clave', [Usuarios::class, 'restablecerClave']);
Flight::route('GET /usuarios/persona/@id', [Usuarios::class, 'getByPersona']);

Flight::route('POST /usuarios', [Usuarios::class, 'new']);
Flight::route('PUT /usuarios', [Usuarios::class, 'replace']);
Flight::route('DELETE /usuarios', [Usuarios::class, 'delete']);

// Accesos Rápidos
Flight::route('POST /accesos-rapidos/sincronizar', [AccesosRapidos::class, 'sincronizar']);
Flight::route('GET /accesos-rapidos', [AccesosRapidos::class, 'getTop']);
Flight::route('PUT /accesos-rapidos/toggle-fijo', [AccesosRapidos::class, 'toggleFijo']);
Flight::route('POST /accesos-rapidos/fijar', [AccesosRapidos::class, 'fijar']);