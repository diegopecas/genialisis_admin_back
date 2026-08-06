<?php
// ÁREAS FÍSICAS
Flight::route('GET /areas-fisicas', ['AreasFisicas', 'getAll']);
Flight::route('GET /areas-fisicas-activas', ['AreasFisicas', 'getActivas']);
Flight::route('GET /areas-fisicas/@id', ['AreasFisicas', 'getById']);
Flight::route('POST /areas-fisicas', ['AreasFisicas', 'new']);
Flight::route('PUT /areas-fisicas', ['AreasFisicas', 'replace']);
Flight::route('DELETE /areas-fisicas', ['AreasFisicas', 'delete']);
Flight::route('GET /areas-fisicas-mobiliario/@id_area', ['AreasFisicas', 'getMobiliarioAsignado']);

// ELEMENTOS FÍSICOS EN ÁREAS
Flight::route('GET /areas-fisicas-elementos/@id_area', ['AreasFisicas', 'getElementosFisicosAsignados']);
Flight::route('POST /areas-fisicas-elementos', ['AreasFisicas', 'asignarElementoFisico']);
Flight::route('PUT /areas-fisicas-elementos', ['AreasFisicas', 'actualizarAsignacionElemento']);
Flight::route('DELETE /areas-fisicas-elementos/@id', ['AreasFisicas', 'eliminarAsignacionElemento']);
Flight::route('GET /condiciones-elemento', ['AreasFisicas', 'getCondicionesElemento']);
Flight::route('GET /elementos-fisicos-disponibles/@id_area', ['AreasFisicas', 'getElementosFisicosDisponibles']);