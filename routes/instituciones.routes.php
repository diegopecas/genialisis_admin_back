<?php
// ENTES DE CONTROL

// INSTITUCIÓN (única por tenant)
Flight::route('GET /instituciones', [Instituciones::class, 'getByTenant']);
Flight::route('POST /instituciones', [Instituciones::class, 'new']);