<?php

// ACTIVIDADES


// LOGROS
// Análisis de logros

// INDICADORES DE LOGROS

// EJES CURRICULARES

// COMPETENCIAS COGNITIVAS

// ESTANDARES BASICOS

// CORTES ACADEMICOS

// ACTIVIDADES ACADÉMICAS X INDICADORES LOGROS

// GRADOS

// GRADOS X PLAN


// PLANES
Flight::route('GET /planes', [Planes::class, 'getAll']);
Flight::route('GET /planes/@id', [Planes::class, 'getById']);
Flight::route('POST /planes', [Planes::class, 'new']);
Flight::route('PUT /planes', [Planes::class, 'replace']);
Flight::route('DELETE /planes', [Planes::class, 'delete']);

// CLIENTES X PLANES - LISTADO FILTRADO
Flight::route('GET /clientes-x-planes-filtros', [ClientesXPlanes::class, 'getPorFiltros']);

// ÁREAS ACADÉMICAS

// AMBIENTES

// MATERIALES X ACTIVIDAD

// CURSOS EXTRACURRICULARES

// CLIENTES X CURSOS EXTRA

// DOCENTES X CURSOS EXTRA

// PROVEEDORES X CURSOS EXTRA

// HORARIOS CURSOS EXTRA

// TARIFAS CURSOS EXTRA

// CUENTAS COBRAR X CURSO EXTRA