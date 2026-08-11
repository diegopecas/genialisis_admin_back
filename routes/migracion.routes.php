<?php

// ===================================================================
// MODULO DE MIGRACION
// index.php carga con glob() todo services/*.service.php y
// routes/*.routes.php, asi que no hay que registrar nada mas.
// ===================================================================

// CATALOGO DE BLOQUES
Flight::route('GET /migracion-catalogo-bloques', [MigracionCatalogoBloques::class, 'getAll']);
Flight::route('GET /migracion-catalogo-bloques/@id', [MigracionCatalogoBloques::class, 'getById']);
Flight::route('POST /migracion-catalogo-bloques', [MigracionCatalogoBloques::class, 'new']);
Flight::route('PUT /migracion-catalogo-bloques', [MigracionCatalogoBloques::class, 'replace']);
Flight::route('DELETE /migracion-catalogo-bloques', [MigracionCatalogoBloques::class, 'delete']);

// TABLAS DE CADA BLOQUE
Flight::route('GET /migracion-catalogo-bloques-tablas', [MigracionCatalogoBloquesTablas::class, 'getAll']);
Flight::route('GET /migracion-catalogo-bloques-tablas/@id', [MigracionCatalogoBloquesTablas::class, 'getById']);
Flight::route('POST /migracion-catalogo-bloques-tablas', [MigracionCatalogoBloquesTablas::class, 'new']);
Flight::route('PUT /migracion-catalogo-bloques-tablas', [MigracionCatalogoBloquesTablas::class, 'replace']);
Flight::route('DELETE /migracion-catalogo-bloques-tablas', [MigracionCatalogoBloquesTablas::class, 'delete']);

// CONEXIONES DESTINO
Flight::route('POST /migracion-conexiones/probar', [MigracionConexiones::class, 'probar']);
Flight::route('GET /migracion-conexiones', [MigracionConexiones::class, 'getAll']);
Flight::route('GET /migracion-conexiones/@id', [MigracionConexiones::class, 'getById']);
Flight::route('POST /migracion-conexiones', [MigracionConexiones::class, 'new']);
Flight::route('PUT /migracion-conexiones', [MigracionConexiones::class, 'replace']);
Flight::route('DELETE /migracion-conexiones', [MigracionConexiones::class, 'delete']);

// SESIONES
Flight::route('POST /migracion-sesiones/cambiar-destino', [MigracionSesiones::class, 'cambiarDestino']);
Flight::route('POST /migracion-sesiones/purgar', [MigracionSesiones::class, 'purgar']);
Flight::route('GET /migracion-sesiones/detalle/@id', [MigracionSesiones::class, 'getDetalle']);
Flight::route('GET /migracion-sesiones', [MigracionSesiones::class, 'getAll']);
Flight::route('GET /migracion-sesiones/@id', [MigracionSesiones::class, 'getById']);
Flight::route('POST /migracion-sesiones', [MigracionSesiones::class, 'new']);
Flight::route('PUT /migracion-sesiones', [MigracionSesiones::class, 'replace']);
Flight::route('DELETE /migracion-sesiones', [MigracionSesiones::class, 'delete']);

// BLOQUES DE LA SESION
Flight::route('POST /migracion-bloques/validar', [MigracionBloques::class, 'validar']);
Flight::route('GET /migracion-bloques', [MigracionBloques::class, 'getAll']);
Flight::route('GET /migracion-bloques/@id', [MigracionBloques::class, 'getById']);
Flight::route('POST /migracion-bloques', [MigracionBloques::class, 'new']);
Flight::route('PUT /migracion-bloques', [MigracionBloques::class, 'replace']);
Flight::route('DELETE /migracion-bloques', [MigracionBloques::class, 'delete']);

// ARCHIVOS DEL CLIENTE
Flight::route('GET /migracion-archivos', [MigracionArchivos::class, 'getAll']);
Flight::route('GET /migracion-archivos/@id', [MigracionArchivos::class, 'getById']);
Flight::route('POST /migracion-archivos', [MigracionArchivos::class, 'new']);
Flight::route('PUT /migracion-archivos', [MigracionArchivos::class, 'replace']);
Flight::route('DELETE /migracion-archivos', [MigracionArchivos::class, 'delete']);

// REGISTROS DEL EXPEDIENTE
Flight::route('GET /migracion-registros', [MigracionRegistros::class, 'getAll']);
Flight::route('GET /migracion-registros/@id', [MigracionRegistros::class, 'getById']);
Flight::route('POST /migracion-registros', [MigracionRegistros::class, 'new']);
Flight::route('PUT /migracion-registros', [MigracionRegistros::class, 'replace']);
Flight::route('DELETE /migracion-registros', [MigracionRegistros::class, 'delete']);

// PREGUNTAS
Flight::route('GET /migracion-preguntas', [MigracionPreguntas::class, 'getAll']);
Flight::route('GET /migracion-preguntas/@id', [MigracionPreguntas::class, 'getById']);
Flight::route('POST /migracion-preguntas', [MigracionPreguntas::class, 'new']);
Flight::route('PUT /migracion-preguntas', [MigracionPreguntas::class, 'replace']);
Flight::route('DELETE /migracion-preguntas', [MigracionPreguntas::class, 'delete']);

// MENSAJES DEL ASISTENTE
Flight::route('GET /migracion-mensajes/consumo', [MigracionMensajes::class, 'getConsumo']);
Flight::route('GET /migracion-mensajes', [MigracionMensajes::class, 'getAll']);
Flight::route('GET /migracion-mensajes/@id', [MigracionMensajes::class, 'getById']);
Flight::route('POST /migracion-mensajes', [MigracionMensajes::class, 'new']);
Flight::route('DELETE /migracion-mensajes', [MigracionMensajes::class, 'delete']);

// SCRIPTS
Flight::route('GET /migracion-scripts/previsualizacion', [MigracionScripts::class, 'getPrevisualizacion']);
Flight::route('POST /migracion-scripts/aprobar', [MigracionScripts::class, 'aprobar']);
Flight::route('POST /migracion-scripts/ejecutar', [MigracionScripts::class, 'ejecutar']);
Flight::route('GET /migracion-scripts', [MigracionScripts::class, 'getAll']);
Flight::route('GET /migracion-scripts/@id', [MigracionScripts::class, 'getById']);
Flight::route('POST /migracion-scripts', [MigracionScripts::class, 'new']);
Flight::route('PUT /migracion-scripts', [MigracionScripts::class, 'replace']);
Flight::route('DELETE /migracion-scripts', [MigracionScripts::class, 'delete']);

// BITACORA Y DESHACER
Flight::route('POST /migracion-ejecuciones/deshacer', [MigracionEjecuciones::class, 'deshacer']);
Flight::route('GET /migracion-ejecuciones', [MigracionEjecuciones::class, 'getAll']);
Flight::route('GET /migracion-ejecuciones/@id', [MigracionEjecuciones::class, 'getById']);

// ESQUEMA Y CATALOGOS DEL DESTINO
Flight::route('GET /migracion-esquema-cache/esquema', [MigracionEsquemaCache::class, 'getEsquema']);
Flight::route('GET /migracion-esquema-cache/catalogos', [MigracionEsquemaCache::class, 'getCatalogos']);
Flight::route('GET /migracion-esquema-cache/verificacion', [MigracionEsquemaCache::class, 'getVerificacion']);
Flight::route('GET /migracion-esquema-cache', [MigracionEsquemaCache::class, 'getAll']);
Flight::route('GET /migracion-esquema-cache/@id', [MigracionEsquemaCache::class, 'getById']);
Flight::route('DELETE /migracion-esquema-cache', [MigracionEsquemaCache::class, 'delete']);

// CODIGO DE GENIALISIS PRODUCTO
Flight::route('GET /migracion-codigo-indice/archivo', [MigracionCodigoIndice::class, 'getArchivo']);
Flight::route('GET /migracion-codigo-indice/busqueda', [MigracionCodigoIndice::class, 'getBusqueda']);
Flight::route('GET /migracion-codigo-indice', [MigracionCodigoIndice::class, 'getAll']);
Flight::route('GET /migracion-codigo-indice/@id', [MigracionCodigoIndice::class, 'getById']);
Flight::route('POST /migracion-codigo-indice', [MigracionCodigoIndice::class, 'new']);
Flight::route('DELETE /migracion-codigo-indice', [MigracionCodigoIndice::class, 'delete']);
