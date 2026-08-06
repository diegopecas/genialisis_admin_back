<?php
// ELEMENTOS FÍSICOS
Flight::route('GET /elementos-fisicos', ['ElementosFisicos', 'getAll']);
Flight::route('GET /elementos-fisicos/@id', ['ElementosFisicos', 'getById']);
Flight::route('POST /elementos-fisicos', ['ElementosFisicos', 'new']);
Flight::route('PUT /elementos-fisicos', ['ElementosFisicos', 'replace']);
Flight::route('DELETE /elementos-fisicos', ['ElementosFisicos', 'delete']);
Flight::route('GET /elementos-fisicos-unidades-medida', ['ElementosFisicos', 'getUnidadesMedida']);

// PROCESOS DE LIMPIEZA PARA ELEMENTOS FÍSICOS
Flight::route('GET /elementos-fisicos/@id/procesos-limpieza', ['ElementosFisicos', 'getProcesosLimpiezaAsignados']);
Flight::route('GET /elementos-fisicos-productos-limpieza-disponibles', ['ElementosFisicos', 'getProductosLimpiezaDisponibles']);
Flight::route('POST /elementos-fisicos/asignar-proceso-limpieza', ['ElementosFisicos', 'asignarProcesoLimpieza']);
Flight::route('PUT /elementos-fisicos/actualizar-proceso-limpieza', ['ElementosFisicos', 'actualizarProcesoLimpieza']);
Flight::route('DELETE /elementos-fisicos/proceso-limpieza/@id', ['ElementosFisicos', 'eliminarProcesoLimpieza']);