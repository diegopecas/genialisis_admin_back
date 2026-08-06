<?php

// COLABORADORES
Flight::route('GET /colaboradores', [Colaboradores::class, 'getAll']);
Flight::route('GET /colaboradores-filtros', [Colaboradores::class, 'getPorFiltros']);
Flight::route('GET /colaboradores/@id', [Colaboradores::class, 'getById']);
Flight::route('POST /colaboradores', [Colaboradores::class, 'new']);
Flight::route('PUT /colaboradores', [Colaboradores::class, 'replace']);
Flight::route('DELETE /colaboradores', [Colaboradores::class, 'delete']);
Flight::route('GET /colaboradores-x-persona/@id_persona', [Colaboradores::class, 'getByIdPersona']);
Flight::route('POST /colaboradores/verificar-duplicados', [Colaboradores::class, 'verificarDuplicados']);

// ROLES COLABORADOR
Flight::route('GET /roles-colaborador', [RolesColaborador::class, 'getAll']);

// CASAS COLABORADORES

// PUNTOS CASAS COLABORADORES

// ACTIVIDADES COLABORADORES
Flight::route('GET /actividades-colaboradores/pendientes-aprobar', [ActividadesColaboradores::class, 'getPendientesAprobar']);
Flight::route('GET /actividades-colaboradores/colaborador/@id_colaborador', [ActividadesColaboradores::class, 'getByColaborador']);
Flight::route('GET /actividades-colaboradores/balance/@id_colaborador', [ActividadesColaboradores::class, 'getBalanceColaborador']);
Flight::route('GET /actividades-colaboradores/resumen-pendientes', [ActividadesColaboradores::class, 'getResumenColaboradoresPendientes']);
Flight::route('GET /actividades-colaboradores', [ActividadesColaboradores::class, 'getAll']);
Flight::route('GET /actividades-colaboradores/@id', [ActividadesColaboradores::class, 'getById']);
Flight::route('POST /actividades-colaboradores', [ActividadesColaboradores::class, 'new']);
Flight::route('POST /actividades-colaboradores/aprobar-multiple', [ActividadesColaboradores::class, 'aprobarMultiple']);
Flight::route('DELETE /actividades-colaboradores', [ActividadesColaboradores::class, 'delete']);
Flight::route('GET /actividades-colaboradores-aprobadas', [ActividadesColaboradores::class, 'getAprobadas']);
Flight::route('GET /actividades-colaboradores-historial', [ActividadesColaboradores::class, 'getHistorialPorColaborador']);
Flight::route('GET /colaboradores/activos', [ActividadesColaboradores::class, 'getColaboradoresActivos']);


// TIPOS ACTIVIDADES COLABORADORES
Flight::route('GET /tipos-actividades-colaboradores', [TiposActividadesColaboradores::class, 'getAll']);
Flight::route('GET /tipos-actividades-colaboradores/@id', [TiposActividadesColaboradores::class, 'getById']);
Flight::route('GET /tipos-actividades-colaboradores/categoria/@id_categoria', [TiposActividadesColaboradores::class, 'getByCategoria']);

// MOTIVOS RETIRO
Flight::route('GET /motivos-retiro', [MotivosRetiro::class, 'getAll']);
Flight::route('GET /motivos-retiro/@id', [MotivosRetiro::class, 'getById']);

// CATEGORÍAS DE ACTIVIDADES
Flight::route('GET /categorias-actividades', [CategoriasActividades::class, 'getAll']);
Flight::route('GET /categorias-actividades/@id', [CategoriasActividades::class, 'getById']);
Flight::route('POST /categorias-actividades', [CategoriasActividades::class, 'new']);
Flight::route('PUT /categorias-actividades', [CategoriasActividades::class, 'replace']);
Flight::route('DELETE /categorias-actividades', [CategoriasActividades::class, 'delete']);

// ESTADOS DE ACTIVIDADES
Flight::route('GET /estados-actividades', [EstadosActividades::class, 'getAll']);
Flight::route('GET /estados-actividades/@id', [EstadosActividades::class, 'getById']);

// TIPOS DE CONTABILIZACIÓN
Flight::route('GET /tipos-contabilizacion', [TiposContabilizacion::class, 'getAll']);
Flight::route('GET /tipos-contabilizacion/@id', [TiposContabilizacion::class, 'getById']);

// CONTABILIZACIONES COLABORADORES
Flight::route('GET /contabilizaciones', [Contabilizaciones::class, 'getAll']);
Flight::route('GET /contabilizaciones/reporte', [Contabilizaciones::class, 'getAllConFiltrosAvanzados']);
Flight::route('GET /contabilizaciones/@id', [Contabilizaciones::class, 'getById']);
Flight::route('GET /contabilizaciones/@id_contabilizacion/detalle', [Contabilizaciones::class, 'getDetalle']);
Flight::route('POST /contabilizaciones/cruzar', [Contabilizaciones::class, 'cruzarActividadesColaboradores']);
Flight::route('POST /contabilizaciones/cruzar-colaboradores', [Contabilizaciones::class, 'cruzarActividadesColaboradores']);
Flight::route('POST /contabilizaciones/cruzar-multiples', [Contabilizaciones::class, 'cruzarMultiplesColaboradores']);
Flight::route('POST /contabilizaciones/nomina', [Contabilizaciones::class, 'contabilizarParaNominaColaboradores']);
Flight::route('POST /contabilizaciones/nomina-colaboradores', [Contabilizaciones::class, 'contabilizarParaNominaColaboradores']);
Flight::route('DELETE /contabilizaciones', [Contabilizaciones::class, 'delete']);


Flight::route('GET /tipos-contrato', [TiposContrato::class, 'getAll']);
Flight::route('GET /tipos-contrato/@id', [TiposContrato::class, 'getById']);
Flight::route('GET /tipos-contrato-activos', [TiposContrato::class, 'getActivos']);
Flight::route('POST /tipos-contrato', [TiposContrato::class, 'new']);
Flight::route('PUT /tipos-contrato', [TiposContrato::class, 'replace']);
Flight::route('DELETE /tipos-contrato', [TiposContrato::class, 'delete']);

// CALENDARIO DE COLABORADORES
Flight::route('GET /actividades-colaboradores-calendario', [ActividadesColaboradores::class, 'getActividadesPorMes']);
Flight::route('GET /colaboradores-calendario', [ActividadesColaboradores::class, 'getColaboradoresParaCalendario']);
Flight::route('GET /planes-calendario', [ActividadesColaboradores::class, 'getPlanesParaCalendario']);

// HORARIOS COLABORADORES
Flight::route('GET /horarios-colaboradores/@id_colaborador', [HorariosColaboradores::class, 'getByColaborador']);
Flight::route('POST /horarios-colaboradores', [HorariosColaboradores::class, 'guardarTodos']);

// REGISTROS ASISTENCIA
Flight::route('GET /registros-asistencia-colaboradores/colaborador/@id_colaborador', [RegistrosAsistenciaColaboradores::class, 'getByColaborador']);
Flight::route('GET /registros-asistencia-colaboradores/hoy/@id_colaborador', [RegistrosAsistenciaColaboradores::class, 'getRegistrosHoy']);
Flight::route('GET /registros-asistencia-colaboradores/tipos', [RegistrosAsistenciaColaboradores::class, 'getTiposRegistro']);
Flight::route('GET /registros-asistencia-colaboradores/estados', [RegistrosAsistenciaColaboradores::class, 'getEstadosRegistro']);
Flight::route('GET /registros-asistencia-colaboradores/configuracion-geofence', [RegistrosAsistenciaColaboradores::class, 'getConfiguracionGeofence']);
Flight::route('POST /registros-asistencia-colaboradores', [RegistrosAsistenciaColaboradores::class, 'registrar']);
Flight::route('DELETE /registros-asistencia-colaboradores', [RegistrosAsistenciaColaboradores::class, 'delete']);

Flight::route('GET /registros-asistencia-colaboradores/reporte', [RegistrosAsistenciaColaboradores::class, 'getReporte']);

// ESTADOS TAREAS COLABORADORES
Flight::route('GET /estados-tareas-colaboradores', [EstadosTareasColaboradores::class, 'getAll']);
Flight::route('GET /estados-tareas-colaboradores/@id', [EstadosTareasColaboradores::class, 'getById']);

// TIPOS TAREAS COLABORADORES
Flight::route('GET /tipos-tareas-colaboradores', [TiposTareasColaboradores::class, 'getAll']);
Flight::route('GET /tipos-tareas-colaboradores/@id', [TiposTareasColaboradores::class, 'getById']);

// TAREAS COLABORADORES
Flight::route('GET /tareas-colaboradores', [TareasColaboradores::class, 'getAll']);
Flight::route('GET /tareas-colaboradores-calendario', [TareasColaboradores::class, 'getTareasPorMes']);
Flight::route('GET /tareas-colaboradores/@id', [TareasColaboradores::class, 'getById']);
Flight::route('GET /tareas-colaboradores/colaborador/@id_colaborador', [TareasColaboradores::class, 'getByColaborador']);
Flight::route('POST /tareas-colaboradores', [TareasColaboradores::class, 'new']);
Flight::route('POST /tareas-colaboradores/masivo', [TareasColaboradores::class, 'crearMasivo']);
Flight::route('PUT /tareas-colaboradores', [TareasColaboradores::class, 'replace']);
Flight::route('PUT /tareas-colaboradores/estado', [TareasColaboradores::class, 'cambiarEstado']);
Flight::route('DELETE /tareas-colaboradores', [TareasColaboradores::class, 'delete']);