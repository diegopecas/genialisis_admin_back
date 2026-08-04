<?php

// GENEROS
Flight::route('GET /generos', [Generos::class, 'getAll']);


Flight::route('GET /niveles-escolaridad', [NivelesEscolaridad::class, 'getAll']);

Flight::route('GET /dias-semana', [DiasSemana::class, 'getAll']);

// PAISES
Flight::route('GET /paises', [Paises::class, 'getAll']);

// CIUDADES
Flight::route('GET /ciudades', [Ciudades::class, 'getAll']);

// DEPARTAMENTOS
Flight::route('GET /departamentos', [Departamentos::class, 'getAll']);

// TIPOS IDENTIFICACIÓN
Flight::route('GET /tipos-identificacion', [TiposIdentificacion::class, 'getAll']);

// CARGOS
Flight::route('GET /cargos', [Cargos::class, 'getAll']);
Flight::route('GET /cargos/@id', [Cargos::class, 'getById']);
Flight::route('POST /cargos', [Cargos::class, 'new']);
Flight::route('PUT /cargos', [Cargos::class, 'replace']);
Flight::route('DELETE /cargos', [Cargos::class, 'delete']);


// CALENDARIOS

// CALENDARIOS EVENTOS


// CONFIGURACIÓN GLOBAL
Flight::route('GET /configuracion-global', [ConfiguracionGlobal::class, 'getAll']);
Flight::route('GET /configuracion-global/@id', [ConfiguracionGlobal::class, 'getById']);
Flight::route('GET /configuracion-global/clave/@clave', [ConfiguracionGlobal::class, 'getByClave']);
Flight::route('POST /configuracion-global/multiples', [ConfiguracionGlobal::class, 'getMultiples']);
Flight::route('POST /configuracion-global', [ConfiguracionGlobal::class, 'new']);
Flight::route('PUT /configuracion-global', [ConfiguracionGlobal::class, 'replace']);
Flight::route('PUT /configuracion-global/clave', [ConfiguracionGlobal::class, 'updateByClave']);
Flight::route('DELETE /configuracion-global', [ConfiguracionGlobal::class, 'delete']);


// AYUDA
Flight::route('GET /ayuda/modulos', [Ayuda::class, 'getModulos']);