<?php
// CLIENTES
Flight::route('GET /clientes', [Clientes::class, 'getAll']);
Flight::route('GET /clientes/@id', [Clientes::class, 'getById']);
Flight::route('POST /clientes', [Clientes::class, 'new']);
Flight::route('PUT /clientes', [Clientes::class, 'replace']);
Flight::route('DELETE /clientes', [Clientes::class, 'delete']);
Flight::route('POST /clientes/verificar-duplicados', [Clientes::class, 'verificarDuplicados']);
Flight::route('POST /clientes/actualizacion-masiva', [Clientes::class, 'actualizacionMasiva']);
Flight::route('POST /clientes/registro-rapido', [Clientes::class, 'registroRapido']);
Flight::route('POST /clientes/analizar-registro-civil', [Clientes::class, 'analizarRegistroCivil']);
Flight::route('POST /clientes/registro-rapido-completo', [Clientes::class, 'registroRapidoCompleto']);
Flight::route('GET /clientes-reporte-completo', [Clientes::class, 'getReporteCompleto']);
Flight::route('GET /clientes-reporte-recordatorios', [Clientes::class, 'getReporteRecordatorios']);

// HISTORIAL RECORDATORIOS GENERALES

Flight::route('GET /clientes-x-planes', [ClientesXPlanes::class, 'getAll']);
Flight::route('GET /clientes-x-planes-activos', [ClientesXPlanes::class, 'getActivos']);
Flight::route('GET /clientes-x-planes/@id_plan', [ClientesXPlanes::class, 'getByPlan']);
Flight::route('GET /clientes-x-planes/cliente/@id', [ClientesXPlanes::class, 'getByCliente']);
Flight::route('POST /clientes-x-planes', [ClientesXPlanes::class, 'new']);
Flight::route('PUT /clientes-x-planes', [ClientesXPlanes::class, 'replace']);
Flight::route('POST /clientes-x-planes/cambio-plan-masivo', [ClientesXPlanes::class, 'cambioPlanMasivo']);


// OBSERVACIONES-CLIENTES

// TIPOS OBSERVACIONES CLIENTES

// Rutas para tipos_representante
Flight::route('GET /tipos-representante', [TiposRepresentante::class, 'getAll']);
Flight::route('GET /tipos-representante/@id', [TiposRepresentante::class, 'getById']);
Flight::route('POST /tipos-representante', [TiposRepresentante::class, 'new']);
Flight::route('PUT /tipos-representante', [TiposRepresentante::class, 'replace']);
Flight::route('DELETE /tipos-representante', [TiposRepresentante::class, 'delete']);

// Rutas para representantes
Flight::route('GET /representantes', [Representantes::class, 'getAll']);
Flight::route('GET /representantes/@id', [Representantes::class, 'getById']);
Flight::route('GET /representantes/cliente/@id', [Representantes::class, 'getByCliente']);
Flight::route('POST /representantes', [Representantes::class, 'new']);
Flight::route('PUT /representantes', [Representantes::class, 'replace']);
Flight::route('DELETE /representantes/@id', [Representantes::class, 'delete']);
Flight::route('POST /representantes/verificar-duplicados', [Representantes::class, 'verificarDuplicados']);
// Ruta para obtener clientes de un representante específico
Flight::route('GET /representantes/mis-clientes/@id_persona', [Representantes::class, 'getClientesByRepresentante']);
Flight::route('GET /representantes/mis-clientes-ids/@id_persona', [Representantes::class, 'getClientesIdsOnly']);


// Tipos de necesidades especiales

// TARIFAS POR PLANES
Flight::route('GET /tarifas-planes', [TarifasPlanes::class, 'getAll']);
Flight::route('GET /tarifas-planes/@id', [TarifasPlanes::class, 'getById']);
Flight::route('GET /tarifas-planes/plan/@id_plan', [TarifasPlanes::class, 'getByPlan']);
Flight::route('GET /tarifas-planes/plan/@id_plan/anio/@anio', [TarifasPlanes::class, 'getByPlanAnio']);
Flight::route('GET /tarifas-planes/anio/@anio', [TarifasPlanes::class, 'getByAnio']);
Flight::route('POST /tarifas-planes', [TarifasPlanes::class, 'new']);
Flight::route('PUT /tarifas-planes', [TarifasPlanes::class, 'replace']);
Flight::route('DELETE /tarifas-planes', [TarifasPlanes::class, 'delete']);

// CONTRATOS DE MATRÍCULA
Flight::route('GET /contratos-cliente', [ContratosCliente::class, 'getAll']);
Flight::route('GET /contratos-cliente/@id', [ContratosCliente::class, 'getById']);
Flight::route('GET /contratos-cliente/cliente/@id_cliente', [ContratosCliente::class, 'getByCliente']);
Flight::route('GET /contratos-cliente/anio/@anio', [ContratosCliente::class, 'getByAnio']);
Flight::route('GET /contratos-cliente/@id/representantes', [ContratosCliente::class, 'getRepresentantesByContrato']);
Flight::route('GET /contratos-cliente/@id/datos-completos', [ContratosCliente::class, 'getDatosContrato']);
Flight::route('POST /contratos-cliente', [ContratosCliente::class, 'new']);
Flight::route('POST /contratos-cliente/verificar-existente', [ContratosCliente::class, 'verificarExistente']);
Flight::route('PUT /contratos-cliente', [ContratosCliente::class, 'replace']);
Flight::route('PUT /contratos-cliente/marcar-firmado', [ContratosCliente::class, 'marcarFirmado']);
Flight::route('PUT /contratos-cliente/desmarcar-firmado', [ContratosCliente::class, 'desmarcarFirmado']);
Flight::route('PUT /contratos-cliente/anular', [ContratosCliente::class, 'anular']);
Flight::route('DELETE /contratos-cliente', [ContratosCliente::class, 'delete']);

// CONTRATOS DE MATRÍCULA - VALORES
Flight::route('GET /contratos-cliente-valores/contrato/@id', [ContratosClienteValores::class, 'getByContrato']);
Flight::route('POST /contratos-cliente-valores', [ContratosClienteValores::class, 'guardarValores']);
Flight::route('POST /contratos-cliente-valores/generar-defecto', [ContratosClienteValores::class, 'generarValoresPorDefecto']);

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

// DATOS MÉDICOS POR CLIENTE

// TIPOS DATOS ADICIONALES

// DATOS ADICIONALES

// DATOS ADICIONALES POR CLIENTE

// HORARIOS CLIENTE


// HISTORIAL INFORMES CLIENTES