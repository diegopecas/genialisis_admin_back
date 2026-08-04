<?php
// PERSONAS
Flight::route('GET /personas', [Personas::class, 'getAll']);
Flight::route('GET /personas/@id', [Personas::class, 'getById']);
Flight::route('POST /personas', [Personas::class, 'new']);
Flight::route('PUT /personas', [Personas::class, 'replace']);
Flight::route('DELETE /personas', [Personas::class, 'delete']);
Flight::route('GET /personas-x-identificacion/@id_tipo_identificacion/@numero_identificacion', [Personas::class, 'getByIdentificacion']);
Flight::route('POST /personas/@id/foto', [Personas::class, 'uploadFoto']);
Flight::route('DELETE /personas/@id/foto', [Personas::class, 'deleteFoto']);
Flight::route('GET /personas/@id/foto', [Personas::class, 'getFoto']);
Flight::route('GET /personas-cumpleanos-hoy', [Personas::class, 'getCumpleanosHoy']);

// DIRECCIONES-PERSONAS
Flight::route('GET /direcciones-personas', [DireccionesPersonas::class, 'getAll']);
Flight::route('GET /direcciones-personas/@id', [DireccionesPersonas::class, 'getById']);
Flight::route('POST /direcciones-personas', [DireccionesPersonas::class, 'new']);
Flight::route('PUT /direcciones-personas', [DireccionesPersonas::class, 'replace']);
Flight::route('DELETE /direcciones-personas', [DireccionesPersonas::class, 'delete']);

// TIPOS DE DOCUMENTOS
Flight::route('GET /tipos-documentos', [TiposDocumentos::class, 'getAll']);
Flight::route('GET /tipos-documentos/tipo-persona/@codigo', [TiposDocumentos::class, 'getByTipoPersona']);
Flight::route('GET /tipos-documentos/@id', [TiposDocumentos::class, 'getById']);
Flight::route('POST /tipos-documentos', [TiposDocumentos::class, 'new']);
Flight::route('PUT /tipos-documentos', [TiposDocumentos::class, 'replace']);
Flight::route('DELETE /tipos-documentos', [TiposDocumentos::class, 'delete']);

// DOCUMENTOS PERSONAS
Flight::route('GET /documentos-personas/persona/@idPersona', [DocumentosPersonas::class, 'getByPersona']);
Flight::route('GET /documentos-personas/persona/@idPersona/tipo/@idTipo', [DocumentosPersonas::class, 'getByPersonaTipoDoc']);
Flight::route('GET /documentos-personas/vencimientos/@dias', [DocumentosPersonas::class, 'getVencimientoProximo']);
Flight::route('POST /documentos-personas/upload', [DocumentosPersonas::class, 'upload']);
Flight::route('PUT /documentos-personas', [DocumentosPersonas::class, 'update']);
Flight::route('DELETE /documentos-personas', [DocumentosPersonas::class, 'delete']);
Flight::route('GET /documentos-personas/download-token/@id', [DocumentosPersonas::class, 'generarTokenDescarga']);
Flight::route('GET /documentos-personas/download/@id', [DocumentosPersonas::class, 'download']);

// FIRMA DIGITAL
Flight::route('POST /documentos-personas/@id/enviar-firma', [FirmaDigital::class, 'enviarAFirmar']);
Flight::route('GET /documentos-personas/@id/estado-firma', [FirmaDigital::class, 'consultarEstado']);
Flight::route('POST /documentos-personas/@id/descargar-firmado', [FirmaDigital::class, 'descargarFirmado']);
Flight::route('POST /documentos-personas/@id/reenviar-firma', [FirmaDigital::class, 'reenviarCorreoFirma']);

// HISTORIAL DE CAMBIOS PERSONA
Flight::route('GET /historial-cambios-persona/@id_persona', [HistorialCambiosPersona::class, 'getByPersona']);
Flight::route('POST /historial-cambios-persona', [HistorialCambiosPersona::class, 'new']);

// TIPOS DE PERSONAS
Flight::route('GET /tipos-personas', [TiposPersonas::class, 'getAll']);

// TIPOS PERSONAS DOCUMENTOS (asociaciones)
Flight::route('GET /tipos-personas-documentos/tipo-documento/@id', [TiposPersonasDocumentos::class, 'getByTipoDocumento']);
Flight::route('POST /tipos-personas-documentos', [TiposPersonasDocumentos::class, 'save']);