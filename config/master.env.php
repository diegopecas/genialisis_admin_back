<?php
define('DB_MASTER_HOST', '92.205.2.161');
define('DB_MASTER_NAME', 'genialisis-master-prod');
define('DB_MASTER_USERNAME', 'usr-genialisis-master-prod');
define('DB_MASTER_PASSWORD', 'NhUIXGy#KYS5');
define('DB_MASTER_CHARSET', 'utf8mb4');
define('DB_MASTER_DSN', 'mysql:host=' . DB_MASTER_HOST . ';dbname=' . DB_MASTER_NAME . ';charset=' . DB_MASTER_CHARSET);

define('META_APP_ID', '1992075778404246');
define('META_APP_SECRET', '563e29c7d4e31f9e4b77f81b85b76f2d');
define('META_CONFIG_ID', '1009442331644081');
define('META_GRAPH_VERSION', 'v23.0');

// Secreto para firmar las URLs temporales que se entregan a Meta al publicar
// en Instagram (HMAC-SHA256). NO es la clave de la app de Instagram.
// Genera uno aleatorio y reemplaza el valor de abajo, por ejemplo:
//   php -r "echo bin2hex(random_bytes(32));"
define('IG_MEDIA_SIGN_SECRET', 'ea0eef10547fa2faa4104998f4c1171c2fed1b4e869007e39e833131bfc02e56');