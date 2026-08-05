<?php

// Campos parametrizables de las plantillas
Flight::route('GET /plantillas-campos', [PlantillasCampos::class, 'getAll']);
Flight::route('GET /plantillas-campos/por-clave/@clave', [PlantillasCampos::class, 'getPorClave']);
Flight::route('POST /plantillas-campos', [PlantillasCampos::class, 'new']);
Flight::route('PUT /plantillas-campos', [PlantillasCampos::class, 'replace']);
Flight::route('DELETE /plantillas-campos', [PlantillasCampos::class, 'delete']);

// Valores diligenciados por contrato
Flight::route('GET /contratos-campos/@idContrato', [ContratosCampos::class, 'getPorContrato']);
Flight::route('POST /contratos-campos', [ContratosCampos::class, 'guardar']);
