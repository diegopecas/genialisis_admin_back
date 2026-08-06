<?php
// PROCESOS DE LIMPIEZA - Rutas
// Tipos de procesos de limpieza
Flight::route('GET /tipos-proceso-limpieza', ['TiposProcesosLimpieza', 'getAll']);
Flight::route('GET /tipos-proceso-limpieza/@id', ['TiposProcesosLimpieza', 'getById']);

// Periodicidad
Flight::route('GET /periodicidad', ['Periodicidad', 'getAll']);
Flight::route('GET /periodicidad/@id', ['Periodicidad', 'getById']);

// Procesos de limpieza por área (parte del dominio de áreas físicas)
Flight::route('GET /areas-fisicas-procesos/@id_area', ['AreasFisicas', 'getProcesosLimpieza']);
Flight::route('POST /areas-fisicas-procesos', ['AreasFisicas', 'asignarProcesoLimpieza']);
Flight::route('PUT /areas-fisicas-procesos', ['AreasFisicas', 'actualizarProcesoLimpieza']);
Flight::route('DELETE /areas-fisicas-procesos/@id', ['AreasFisicas', 'eliminarProcesoLimpieza']);
Flight::route('PUT /areas-fisicas-procesos/inactivar/@id', ['AreasFisicas', 'inactivarProcesoLimpieza']);
Flight::route('GET /areas-fisicas-carga-trabajo/@id_area', ['AreasFisicas', 'getCargaTrabajo']);
// En procesos-limpieza.routes.php
Flight::route('PUT /areas-fisicas-procesos/activar/@id', ['AreasFisicas', 'activarProcesoLimpieza']);