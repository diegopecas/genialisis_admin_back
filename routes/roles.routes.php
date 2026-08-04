<?php
Flight::route('GET /roles', [Roles::class, 'getAll']);
Flight::route('GET /roles/@id', [Roles::class, 'getById']);
Flight::route('POST /roles', [Roles::class, 'new']);
Flight::route('PUT /roles', [Roles::class, 'replace']);
Flight::route('DELETE /roles', [Roles::class, 'delete']);
