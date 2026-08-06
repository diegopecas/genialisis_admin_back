<?php
// PRODUCTOS LIMPIEZA
Flight::route('GET /productos-limpieza', ['ProductosLimpieza', 'getAll']);
Flight::route('GET /productos-limpieza/@id', ['ProductosLimpieza', 'getById']);
Flight::route('POST /productos-limpieza', ['ProductosLimpieza', 'new']);
Flight::route('PUT /productos-limpieza', ['ProductosLimpieza', 'replace']);
Flight::route('DELETE /productos-limpieza', ['ProductosLimpieza', 'delete']);
Flight::route('GET /productos-limpieza-disponibles-limpieza', ['ProductosLimpieza', 'getProductosDisponiblesParaLimpieza']);

// TIPOS PRODUCTO LIMPIEZA
Flight::route('GET /tipos-producto-limpieza', ['TiposProductoLimpieza', 'getAll']);