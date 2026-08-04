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

// GRADOS X GRUPO


// GRUPOS
Flight::route('GET /grupos', [Grupos::class, 'getAll']);
Flight::route('GET /grupos/@id', [Grupos::class, 'getById']);
Flight::route('POST /grupos', [Grupos::class, 'new']);
Flight::route('PUT /grupos', [Grupos::class, 'replace']);
Flight::route('DELETE /grupos', [Grupos::class, 'delete']);

// ESTUDIANTES X GRUPOS - LISTADO FILTRADO
Flight::route('GET /estudiantes-x-grupos-filtros', [EstudiantesXGrupos::class, 'getPorFiltros']);

// ÁREAS ACADÉMICAS

// AMBIENTES

// MATERIALES X ACTIVIDAD

// CURSOS EXTRACURRICULARES

// ESTUDIANTES X CURSOS EXTRA

// DOCENTES X CURSOS EXTRA

// PROVEEDORES X CURSOS EXTRA

// HORARIOS CURSOS EXTRA

// TARIFAS CURSOS EXTRA

// CUENTAS COBRAR X CURSO EXTRA