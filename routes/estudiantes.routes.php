<?php
// ESTUDIANTES
Flight::route('GET /estudiantes', [Estudiantes::class, 'getAll']);
Flight::route('GET /estudiantes/@id', [Estudiantes::class, 'getById']);
Flight::route('POST /estudiantes', [Estudiantes::class, 'new']);
Flight::route('PUT /estudiantes', [Estudiantes::class, 'replace']);
Flight::route('DELETE /estudiantes', [Estudiantes::class, 'delete']);
Flight::route('POST /estudiantes/verificar-duplicados', [Estudiantes::class, 'verificarDuplicados']);
Flight::route('POST /estudiantes/actualizacion-masiva', [Estudiantes::class, 'actualizacionMasiva']);
Flight::route('POST /estudiantes/registro-rapido', [Estudiantes::class, 'registroRapido']);
Flight::route('POST /estudiantes/analizar-registro-civil', [Estudiantes::class, 'analizarRegistroCivil']);
Flight::route('POST /estudiantes/registro-rapido-completo', [Estudiantes::class, 'registroRapidoCompleto']);
Flight::route('GET /estudiantes-reporte-completo', [Estudiantes::class, 'getReporteCompleto']);
Flight::route('GET /estudiantes-reporte-recordatorios', [Estudiantes::class, 'getReporteRecordatorios']);

// HISTORIAL RECORDATORIOS GENERALES

Flight::route('GET /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'getAll']);
Flight::route('GET /estudiantes-x-grupos-activos', [EstudiantesXGrupos::class, 'getActivos']);
Flight::route('GET /estudiantes-x-grupos/@id_grupo', [EstudiantesXGrupos::class, 'getByGrupo']);
Flight::route('GET /estudiantes-x-grupos/estudiante/@id', [EstudiantesXGrupos::class, 'getByEstudiante']);
Flight::route('POST /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'new']);
Flight::route('PUT /estudiantes-x-grupos', [EstudiantesXGrupos::class, 'replace']);
Flight::route('POST /estudiantes-x-grupos/cambio-grupo-masivo', [EstudiantesXGrupos::class, 'cambioGrupoMasivo']);


// OBSERVACIONES-ESTUDIANTES

// TIPOS OBSERVACIONES ESTUDIANTES

// Rutas para tipos_acudiente
Flight::route('GET /tipos-acudiente', [TiposAcudiente::class, 'getAll']);
Flight::route('GET /tipos-acudiente/@id', [TiposAcudiente::class, 'getById']);
Flight::route('POST /tipos-acudiente', [TiposAcudiente::class, 'new']);
Flight::route('PUT /tipos-acudiente', [TiposAcudiente::class, 'replace']);
Flight::route('DELETE /tipos-acudiente', [TiposAcudiente::class, 'delete']);

// Rutas para acudientes
Flight::route('GET /acudientes', [Acudientes::class, 'getAll']);
Flight::route('GET /acudientes/@id', [Acudientes::class, 'getById']);
Flight::route('GET /acudientes/estudiante/@id', [Acudientes::class, 'getByEstudiante']);
Flight::route('POST /acudientes', [Acudientes::class, 'new']);
Flight::route('PUT /acudientes', [Acudientes::class, 'replace']);
Flight::route('DELETE /acudientes/@id', [Acudientes::class, 'delete']);
Flight::route('POST /acudientes/verificar-duplicados', [Acudientes::class, 'verificarDuplicados']);
// Ruta para obtener estudiantes de un acudiente específico
Flight::route('GET /acudientes/mis-estudiantes/@id_persona', [Acudientes::class, 'getEstudiantesByAcudiente']);
Flight::route('GET /acudientes/mis-estudiantes-ids/@id_persona', [Acudientes::class, 'getEstudiantesIdsOnly']);


// Tipos de necesidades especiales

// TARIFAS POR GRUPOS
Flight::route('GET /tarifas-grupos', [TarifasGrupos::class, 'getAll']);
Flight::route('GET /tarifas-grupos/@id', [TarifasGrupos::class, 'getById']);
Flight::route('GET /tarifas-grupos/grupo/@id_grupo', [TarifasGrupos::class, 'getByGrupo']);
Flight::route('GET /tarifas-grupos/grupo/@id_grupo/anio/@anio', [TarifasGrupos::class, 'getByGrupoAnio']);
Flight::route('GET /tarifas-grupos/anio/@anio', [TarifasGrupos::class, 'getByAnio']);
Flight::route('POST /tarifas-grupos', [TarifasGrupos::class, 'new']);
Flight::route('PUT /tarifas-grupos', [TarifasGrupos::class, 'replace']);
Flight::route('DELETE /tarifas-grupos', [TarifasGrupos::class, 'delete']);

// CONTRATOS DE MATRÍCULA
Flight::route('GET /contratos-matricula', [ContratosMatricula::class, 'getAll']);
Flight::route('GET /contratos-matricula/@id', [ContratosMatricula::class, 'getById']);
Flight::route('GET /contratos-matricula/estudiante/@id_estudiante', [ContratosMatricula::class, 'getByEstudiante']);
Flight::route('GET /contratos-matricula/anio/@anio', [ContratosMatricula::class, 'getByAnio']);
Flight::route('GET /contratos-matricula/@id/acudientes', [ContratosMatricula::class, 'getAcudientesByContrato']);
Flight::route('GET /contratos-matricula/@id/datos-completos', [ContratosMatricula::class, 'getDatosContrato']);
Flight::route('POST /contratos-matricula', [ContratosMatricula::class, 'new']);
Flight::route('POST /contratos-matricula/verificar-existente', [ContratosMatricula::class, 'verificarExistente']);
Flight::route('PUT /contratos-matricula', [ContratosMatricula::class, 'replace']);
Flight::route('PUT /contratos-matricula/marcar-firmado', [ContratosMatricula::class, 'marcarFirmado']);
Flight::route('PUT /contratos-matricula/desmarcar-firmado', [ContratosMatricula::class, 'desmarcarFirmado']);
Flight::route('PUT /contratos-matricula/anular', [ContratosMatricula::class, 'anular']);
Flight::route('DELETE /contratos-matricula', [ContratosMatricula::class, 'delete']);

// CONTRATOS DE MATRÍCULA - VALORES
Flight::route('GET /contratos-matricula-valores/contrato/@id', [ContratosMatriculaValores::class, 'getByContrato']);
Flight::route('POST /contratos-matricula-valores', [ContratosMatriculaValores::class, 'guardarValores']);
Flight::route('POST /contratos-matricula-valores/generar-defecto', [ContratosMatriculaValores::class, 'generarValoresPorDefecto']);

// TIPOS DE PLANTILLAS
Flight::route('GET /tipos-plantillas', [TiposPlantillas::class, 'getAll']);
Flight::route('GET /tipos-plantillas/@id', [TiposPlantillas::class, 'getById']);
Flight::route('GET /tipos-plantillas/codigo/@codigo', [TiposPlantillas::class, 'getByCodigo']);
Flight::route('POST /tipos-plantillas', [TiposPlantillas::class, 'new']);
Flight::route('PUT /tipos-plantillas', [TiposPlantillas::class, 'replace']);
Flight::route('DELETE /tipos-plantillas', [TiposPlantillas::class, 'delete']);

// PLANTILLAS
Flight::route('GET /plantillas', [Plantillas::class, 'getAll']);
Flight::route('GET /plantillas/obtener-by-tipo-clave/@codigoTipo/@clavePlantilla', [Plantillas::class, 'getByTipoClave']);
Flight::route('GET /plantillas/obtener-by-tipo/@codigoTipo', [Plantillas::class, 'getByTipo']);
Flight::route('GET /plantillas/@id', [Plantillas::class, 'getById']);
Flight::route('PUT /plantillas', [Plantillas::class, 'replace']);

// TIPOS AUTORIZACION RECOGER

// AUTORIZADOS RECOGER

// AUTORIZADOS RECOGER HISTORIAL

// TIPOS DATOS MÉDICOS

// DATOS MÉDICOS

// DATOS MÉDICOS POR ESTUDIANTE

// TIPOS DATOS ADICIONALES

// DATOS ADICIONALES

// DATOS ADICIONALES POR ESTUDIANTE

// HORARIOS ESTUDIANTE


// HISTORIAL INFORMES ESTUDIANTES