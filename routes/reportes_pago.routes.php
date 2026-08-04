<?php
// REPORTES DE PAGO (Portal de Padres)

// Rutas específicas primero (antes de la genérica con @id)
Flight::route('GET /reportes-pago/pendientes', [ReportesPago::class, 'getPendientes']);
Flight::route('GET /reportes-pago/estudiante/@id', [ReportesPago::class, 'getByEstudiante']);
Flight::route('GET /reportes-pago/persona/@id', [ReportesPago::class, 'getByPersonaReporta']);
Flight::route('GET /reportes-pago/pendientes-colaborador/@id', [ReportesPago::class, 'getPendientesByColaborador']);
Flight::route('GET /reportes-pago/pendientes-estudiante/@id', [ReportesPago::class, 'getPendientesByEstudiante']);
Flight::route('GET /reportes-pago/pago-recibido/@id', [ReportesPago::class, 'getByPagoRecibido']);
Flight::route('GET /reportes-pago/tipos-pago-portal', [ReportesPago::class, 'getTiposPagoPortal']);
Flight::route('GET /reportes-pago/colaboradores-activos', [ReportesPago::class, 'getColaboradoresActivos']);

// Rutas genéricas al final
Flight::route('GET /reportes-pago', [ReportesPago::class, 'getAll']);
Flight::route('GET /reportes-pago/@id', [ReportesPago::class, 'getById']);

// POST, PUT, DELETE
Flight::route('POST /reportes-pago', [ReportesPago::class, 'new']);
Flight::route('PUT /reportes-pago/asociar', [ReportesPago::class, 'asociarPago']);
Flight::route('PUT /reportes-pago/documento', [ReportesPago::class, 'actualizarDocumento']);
Flight::route('DELETE /reportes-pago/@id', [ReportesPago::class, 'delete']);