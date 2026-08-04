<?php
// productos-servicios
Flight::route('GET /productos-servicios', [ProductosServicios::class, 'getAll']);
Flight::route('GET /productos-servicios/catalogo-disponibles', [ProductosServicios::class, 'getCatalogoDisponibles']);
Flight::route('GET /productos-servicios/@id', [ProductosServicios::class, 'getById']);
Flight::route('GET /productos-servicios/clasificacion/@id', [ProductosServicios::class, 'getByClasificacion']);
Flight::route('POST /productos-servicios', [ProductosServicios::class, 'new']);
Flight::route('PUT /productos-servicios', [ProductosServicios::class, 'replace']);
Flight::route('DELETE /productos-servicios', [ProductosServicios::class, 'delete']);

// clasificacion-productos-servicios
Flight::route('GET /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'getAll']);
Flight::route('GET /clasificacion-productos-servicios/@id', [ClasificacionProductosServicios::class, 'getById']);
Flight::route('POST /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'new']);
Flight::route('PUT /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'replace']);
Flight::route('DELETE /clasificacion-productos-servicios', [ClasificacionProductosServicios::class, 'delete']);

// categoria-productos-servicios
Flight::route('GET /categoria-productos-servicios', [CategoriaProductosServicios::class, 'getAll']);
Flight::route('GET /categoria-productos-servicios/@id', [CategoriaProductosServicios::class, 'getById']);
Flight::route('POST /categoria-productos-servicios', [CategoriaProductosServicios::class, 'new']);
Flight::route('PUT /categoria-productos-servicios', [CategoriaProductosServicios::class, 'replace']);
Flight::route('DELETE /categoria-productos-servicios', [CategoriaProductosServicios::class, 'delete']);

// periodicidad-cobro
Flight::route('GET /periodicidad-cobro', [PeriodicidadCobro::class, 'getAll']);
Flight::route('GET /periodicidad-cobro/@id', [PeriodicidadCobro::class, 'getById']);
Flight::route('POST /periodicidad-cobro', [PeriodicidadCobro::class, 'new']);
Flight::route('PUT /periodicidad-cobro', [PeriodicidadCobro::class, 'replace']);
Flight::route('DELETE /periodicidad-cobro', [PeriodicidadCobro::class, 'delete']);

// horarios-alimentacion

// ONCES
// ONCES-PERSONAS