<?php

define('DB_HOST', '92.205.2.161');
define('DB_NAME', 'jean-piaget-prod');
define('DB_USERNAME', 'usr-jean-piaget-prod');
define('DB_PASSWORD', '2S[M96%?+inR');
define('DB_CHARSET', 'utf8mb4');
define('DB_TYPE', 'mysql');
define('DB_DSN', DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// ========================================
// ID NUMÉRICO DEL TENANT (= tenants.id en la BD maestra)
// Lo lee TenantContext::id() para aislar las filas por id_tenant.
// ========================================
define('TENANT_ID', 2);

// =============================================
// CONFIGURACIÓN VAPID - PUSH NOTIFICATIONS
// =============================================
define('VAPID_PUBLIC_KEY', 'BObkU8JSPUs8tC4Hk3m31gc_yfV9bPkrVPWxJPL9qpFd3wSnL8q4kDBTcnrYWn4ll9CUv5rcyebb8jU5o9ZL1vQ');
define('VAPID_PRIVATE_KEY', 'HnpvQ11fiBrnqyQOs_mFNvEdMeiE-RM1-WtxCoQ2B6o');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');