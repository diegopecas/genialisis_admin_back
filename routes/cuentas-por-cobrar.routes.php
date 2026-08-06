<?php
// CATEGORIA PRODUCTOS SERVICIOS
Flight::route('GET /categoria-productos-servicios', [CategoriaProductosServicios::class, 'getAll']);
Flight::route('GET /categoria-productos-servicios/@id', [CategoriaProductosServicios::class, 'getById']);
Flight::route('POST /categoria-productos-servicios', [CategoriaProductosServicios::class, 'new']);
Flight::route('PUT /categoria-productos-servicios', [CategoriaProductosServicios::class, 'replace']);
Flight::route('DELETE /categoria-productos-servicios', [CategoriaProductosServicios::class, 'delete']);

Flight::route('GET /cuentas-por-cobrar', [CuentasPorCobrar::class, 'getAll']);
Flight::route('GET /cuentas-por-cobrar/persona/@id', [CuentasPorCobrar::class, 'getByPersona']);
Flight::route('GET /cuentas-por-cobrar/reporte-anual/@anio', [CuentasPorCobrar::class, 'getReporteAnual']);
Flight::route('GET /cuentas-por-cobrar/reporte-cobros-anual/@anio', [CuentasPorCobrar::class, 'getReporteCobrosAnual']);
Flight::route('GET /cuentas-por-cobrar/reporte-cartera-clientes/@anio', [CuentasPorCobrar::class, 'getReporteCarteraClientes']);
Flight::route('GET /cuentas-por-cobrar/reporte-cartera-clientes/@anio/@idCliente', [CuentasPorCobrar::class, 'getReporteCarteraClientes']);
Flight::route('GET /cuentas-por-cobrar/@id', [CuentasPorCobrar::class, 'getById']);
Flight::route('POST /cuentas-por-cobrar', [CuentasPorCobrar::class, 'new']);
Flight::route('PUT /cuentas-por-cobrar', [CuentasPorCobrar::class, 'replace']);
Flight::route('PUT /cuentas-por-cobrar/anular', [CuentasPorCobrar::class, 'anular']);
Flight::route('DELETE /cuentas-por-cobrar', [CuentasPorCobrar::class, 'delete']);

Flight::route('POST /cuentas-por-cobrar/verificar-duplicados', [CuentasPorCobrar::class, 'verificarDuplicados']);
Flight::route('POST /cuentas-por-cobrar/generar-desde-contrato', [CuentasPorCobrar::class, 'generarDesdeContrato']);


// Rutas para cuentas por cobrar con detalle
Flight::route('GET /cuentas-por-cobrar/detalle', [CuentasPorCobrar::class, 'getAllConDetalle']);
Flight::route('GET /cuentas-por-cobrar/resumen', [CuentasPorCobrar::class, 'getResumenCartera']);
Flight::route('GET /cuentas-por-cobrar/detalle-anual/@anio', [CuentasPorCobrar::class, 'getAllConDetalleAnual']);
Flight::route('GET /cuentas-por-cobrar/resumen-anual/@anio', [CuentasPorCobrar::class, 'getResumenCarteraAnual']);
Flight::route('POST /cuentas-por-cobrar/multiple', [CuentasPorCobrar::class, 'getByMultipleIds']);

// CONCEPTOS-PAGO
Flight::route('GET /conceptos-pago', [ConceptosPago::class, 'getAll']);



// Rutas para PagosRecibidos
Flight::route('GET /pagos-recibidos', [PagosRecibidos::class, 'getAll']);
Flight::route('POST /pagos-recibidos', [PagosRecibidos::class, 'new']);
Flight::route('PUT /pagos-recibidos', [PagosRecibidos::class, 'replace']);
Flight::route('PUT /pagos-recibidos/anular', [PagosRecibidos::class, 'anular']);
Flight::route('PUT /pagos-recibidos/contabilizar', [PagosRecibidos::class, 'contabilizar']);
Flight::route('DELETE /pagos-recibidos', [PagosRecibidos::class, 'delete']);
Flight::route('GET /pagos-recibidos/pendientes-contabilizar', [PagosRecibidos::class, 'getPendientesContabilizar']);
Flight::route('PUT /pagos-recibidos/contabilizar-multiple', [PagosRecibidos::class, 'contabilizarMultiple']);
Flight::route('GET /pagos-recibidos/datos-registro-rapido', [PagosRecibidos::class, 'getDatosRegistroRapido']);
Flight::route('POST /pagos-recibidos/analizar-comprobante', [PagosRecibidos::class, 'analizarComprobante']);
Flight::route('POST /pagos-recibidos/registrar-masivo', [PagosRecibidos::class, 'registrarMasivo']);
Flight::route('POST /pagos-recibidos/verificar-duplicado', [PagosRecibidos::class, 'verificarDuplicado']);
Flight::route('GET /pagos-recibidos/@id', [PagosRecibidos::class, 'getById']);
Flight::route('GET /pagos-recibidos/cliente/@id', [PagosRecibidos::class, 'getByCliente']);
Flight::route('GET /pagos-recibidos/comprobante/@id_pago_recibido', [PagosRecibidos::class, 'obtenerDatosComprobante']);
Flight::route('GET /pagos-recibidos-colaborador/@id', [PagosRecibidos::class, 'getByColaborador']);
Flight::route('GET /pagos-recibidos-comprobante-colaborador/@id', [PagosRecibidos::class, 'obtenerDatosComprobanteColaborador']);

// Rutas para CuentaPagada

Flight::route('GET /cuenta-pagada', [CuentaPagada::class, 'getAll']);
Flight::route('GET /cuenta-pagada/@id', [CuentaPagada::class, 'getById']);
Flight::route('GET /cuenta-pagada/pago-recibido/@id_pago_recibido', [CuentaPagada::class, 'getByPagoRecibido']);
Flight::route('GET /cuenta-pagada/cuenta-por-cobrar/@id_cuenta', [CuentaPagada::class, 'getByCuentaPorCobrar']);
Flight::route('POST /cuenta-pagada', [CuentaPagada::class, 'new']);
Flight::route('PUT /cuenta-pagada', [CuentaPagada::class, 'replace']);
Flight::route('DELETE /cuenta-pagada', [CuentaPagada::class, 'delete']);
Flight::route('POST /cuenta-pagada/batch', [CuentaPagada::class, 'createBatch']);

Flight::route('GET /tipos-pagos', [TiposPagos::class, 'getAll']);
Flight::route('GET /tipos-pagos/@id', [TiposPagos::class, 'getById']);
Flight::route('POST /tipos-pagos', [TiposPagos::class, 'new']);
Flight::route('PUT /tipos-pagos', [TiposPagos::class, 'replace']);
Flight::route('DELETE /tipos-pagos', [TiposPagos::class, 'delete']);

// Historial de recordatorios de pago
Flight::route('GET /historial-recordatorios-pago', [HistorialRecordatoriosPago::class, 'getAll']);
Flight::route('GET /historial-recordatorios-pago/cliente/@id', [HistorialRecordatoriosPago::class, 'getByCliente']);
Flight::route('POST /historial-recordatorios-pago', [HistorialRecordatoriosPago::class, 'new']);
Flight::route('PUT /historial-recordatorios-pago', [HistorialRecordatoriosPago::class, 'replace']);