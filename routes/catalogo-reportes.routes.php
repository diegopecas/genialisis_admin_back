<?php
// TIPOS DE REPORTES
Flight::route('GET /tipos-reportes', [TiposReportes::class, 'getAll']);

// CATÁLOGO DE REPORTES
Flight::route('GET /catalogo-reportes', [CatalogoReportes::class, 'getAll']);
Flight::route('GET /catalogo-reportes/entes-control', [CatalogoReportes::class, 'getParaEntesControl']);
Flight::route('GET /catalogo-reportes/@id', [CatalogoReportes::class, 'getById']);
Flight::route('POST /catalogo-reportes', [CatalogoReportes::class, 'new']);
Flight::route('PUT /catalogo-reportes', [CatalogoReportes::class, 'replace']);
Flight::route('DELETE /catalogo-reportes', [CatalogoReportes::class, 'delete']);

// RECURSOS POR ENTE DE CONTROL