<?php
// PRODUCTOS MOBILIARIO
Flight::route('GET /productos-mobiliario', ['ProductosMobiliario', 'getAll']);
Flight::route('GET /productos-mobiliario/@id', ['ProductosMobiliario', 'getById']);
Flight::route('POST /productos-mobiliario', ['ProductosMobiliario', 'new']);
Flight::route('PUT /productos-mobiliario', ['ProductosMobiliario', 'replace']);
Flight::route('DELETE /productos-mobiliario', ['ProductosMobiliario', 'delete']);
Flight::route('GET /productos-mobiliario-disponibles-mobiliario', ['ProductosMobiliario', 'getProductosDisponiblesParaMobiliario']);

// Nuevas rutas para gestión de áreas
Flight::route('GET /productos-mobiliario-con-stock', ['ProductosMobiliario', 'getMobiliarioConStock']);
Flight::route('POST /productos-mobiliario-procesar-devolucion-asignacion', ['ProductosMobiliario', 'procesarDevolucionAsignacion']);
Flight::route('GET /productos-mobiliario-conceptos-devolucion', ['ProductosMobiliario', 'getConceptosDevolucion']);
Flight::route('POST /productos-mobiliario-guardar-asignacion', ['ProductosMobiliario', 'guardarAsignacionArea']);

// TIPOS PRODUCTO MOBILIARIO
Flight::route('GET /tipos-producto-mobiliario', ['TiposProductoMobiliario', 'getAll']);

// PRODUCTOS DE LIMPIEZA PARA MOBILIARIO (simplificado)
Flight::route('GET /productos-mobiliario/@id/productos-limpieza', ['ProductosMobiliario', 'getProductosLimpiezaAsignados']);
Flight::route('GET /productos-mobiliario/@id/productos-limpieza-disponibles', ['ProductosMobiliario', 'getProductosLimpiezaDisponibles']);
Flight::route('POST /productos-mobiliario/asignar-productos-limpieza', ['ProductosMobiliario', 'asignarProductosLimpieza']); // Múltiple
Flight::route('DELETE /productos-mobiliario/producto-limpieza/@id', ['ProductosMobiliario', 'eliminarAsignacionLimpieza']);

// PROCESOS DE LIMPIEZA PARA MOBILIARIO (actualizadas para múltiples productos)
Flight::route('GET /productos-mobiliario/@id/procesos-limpieza', ['ProductosMobiliario', 'getProcesosLimpiezaAsignados']);
Flight::route('GET /productos-mobiliario/@id/productos-para-proceso', ['ProductosMobiliario', 'getProductosLimpiezaParaProceso']);
Flight::route('POST /productos-mobiliario/asignar-proceso-limpieza', ['ProductosMobiliario', 'asignarProcesoLimpieza']);
Flight::route('PUT /productos-mobiliario/actualizar-proceso-limpieza', ['ProductosMobiliario', 'actualizarProcesoLimpieza']);
Flight::route('DELETE /productos-mobiliario/proceso-limpieza/@id', ['ProductosMobiliario', 'eliminarProcesoLimpieza']);

// NUEVAS RUTAS para gestión de productos en procesos
Flight::route('POST /productos-mobiliario/proceso-agregar-producto', ['ProductosMobiliario', 'agregarProductoAProceso']);
Flight::route('DELETE /productos-mobiliario/proceso-producto/@id', ['ProductosMobiliario', 'eliminarProductoDeProceso']);