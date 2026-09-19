<?php

/**
 * Rutas del flujo publico del taller. TODAS cuelgan del prefijo
 * /taller-publico/, que es el que index.php exime de la sesion. Cualquier
 * ruta nueva del modulo que no deba ser publica NO va en este archivo.
 */

Flight::route('GET /taller-publico/visita/@token', [VisitasPublico::class, 'getVisita']);

Flight::route('POST /taller-publico/identificar', [VisitasPublico::class, 'identificar']);

Flight::route('POST /taller-publico/participante', [VisitasPublico::class, 'guardarParticipante']);
Flight::route('GET /taller-publico/participante/@token/@idParticipante', [VisitasPublico::class, 'getParticipante']);

Flight::route('GET /taller-publico/encuesta/@token/@idParticipante', [VisitasPublico::class, 'getEncuesta']);
Flight::route('POST /taller-publico/encuesta', [VisitasPublico::class, 'guardarEncuesta']);

Flight::route('GET /taller-publico/ideas/@token/@idParticipante', [VisitasPublico::class, 'getIdeas']);
Flight::route('POST /taller-publico/ideas', [VisitasPublico::class, 'guardarIdeas']);

Flight::route('GET /taller-publico/caracter/@token/@idParticipante', [VisitasPublico::class, 'getCaracter']);
Flight::route('POST /taller-publico/caracter', [VisitasPublico::class, 'guardarCaracter']);

Flight::route('GET /taller-publico/calificacion/@token', [VisitasPublico::class, 'getCalificacion']);
Flight::route('POST /taller-publico/calificacion', [VisitasPublico::class, 'guardarCalificacion']);
