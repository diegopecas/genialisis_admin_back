<?php

// Visitas
Flight::route('GET /visitas', [Visitas::class, 'getAll']);
Flight::route('GET /visitas-clientes', [Visitas::class, 'clientesDisponibles']);
Flight::route('GET /visitas/@id', [Visitas::class, 'getById']);
Flight::route('GET /visitas/@id/resultados', [Visitas::class, 'resultados']);
Flight::route('POST /visitas', [Visitas::class, 'new']);
Flight::route('PUT /visitas', [Visitas::class, 'replace']);
Flight::route('DELETE /visitas', [Visitas::class, 'delete']);

// VisitasColaboradores
Flight::route('GET /visitas-colaboradores/@idVisita', [VisitasColaboradores::class, 'getByVisita']);

// VisitasParticipantes
Flight::route('GET /visitas-participantes/@idVisita', [VisitasParticipantes::class, 'getByVisita']);

// Catalogos (solo lectura)
Flight::route('GET /visitas-encuesta-items/@rol', [VisitasEncuestaItems::class, 'getByRol']);
Flight::route('GET /visitas-caracter-items', [VisitasCaracterItems::class, 'getAll']);
Flight::route('GET /visitas-calificacion-items', [VisitasCalificacionItems::class, 'getAll']);
