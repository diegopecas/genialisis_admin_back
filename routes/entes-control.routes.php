<?php

// EntesControl
Flight::route('GET /entes-control', [EntesControl::class, 'getAll']);
Flight::route('GET /entes-control/@id', [EntesControl::class, 'getById']);
Flight::route('GET /entes-control-duplicados/@idPersona', [EntesControl::class, 'verificarDuplicados']);
Flight::route('POST /entes-control', [EntesControl::class, 'new']);
Flight::route('PUT /entes-control', [EntesControl::class, 'replace']);
Flight::route('DELETE /entes-control', [EntesControl::class, 'delete']);

// EntesControlRecursos
Flight::route('GET /entes-control-recursos/@idEnteControl', [EntesControlRecursos::class, 'getByEnte']);
Flight::route('GET /entes-control-recursos/@idEnteControl/disponibles', [EntesControlRecursos::class, 'getDisponibles']);
Flight::route('POST /entes-control-recursos/sincronizar', [EntesControlRecursos::class, 'sincronizar']);
Flight::route('GET /entes-control-recursos/@idEnteControl/resolver', [EntesControlRecursos::class, 'resolver']);
Flight::route('POST /entes-control-recursos', [EntesControlRecursos::class, 'new']);
Flight::route('DELETE /entes-control-recursos', [EntesControlRecursos::class, 'delete']);
