<?php
/**
 * Configuracion del modulo de migracion.
 * Va en config/migracion.env.php, al lado de master.env.php y jwt.env.php.
 * NO se versiona.
 */

// Clave para cifrar las contrasenas de las conexiones destino.
// Si se cambia despues de haber guardado conexiones, hay que volver a
// capturarles la clave.
define('MIGRACION_CLAVE', 'CAMBIAR-POR-UNA-CADENA-LARGA-Y-ALEATORIA');

// Archivos que sube el equipo. Se borra su contenido al purgar la sesion.
define('MIGRACION_RUTA_ARCHIVOS', __DIR__ . '/../uploads/migracion');

// Zips del codigo de Genialisis producto.
define('MIGRACION_RUTA_CODIGO', __DIR__ . '/../uploads/migracion_codigo');

// Tope de tamano por archivo subido, en bytes.
define('MIGRACION_MAX_ARCHIVO', 52428800);
