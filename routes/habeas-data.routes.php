<?php
// AUTORIZACIONES HABEAS DATA
// El id_usuario ya no viaja en la URL: sale del JWT.
Flight::route('GET /autorizaciones-habeas-data/verificar', [AutorizacionesHabeasData::class, 'verificar']);
Flight::route('GET /autorizaciones-habeas-data/plantilla', [AutorizacionesHabeasData::class, 'getPlantilla']);
Flight::route('POST /autorizaciones-habeas-data', [AutorizacionesHabeasData::class, 'new']);