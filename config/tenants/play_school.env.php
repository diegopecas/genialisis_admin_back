<?php

// ========================================
// CONFIGURACIÓN BD PRINCIPAL (GENIALISIS)
// ========================================
define('DB_HOST', '132.148.181.209');
define('DB_NAME', 'g_fundadores_prod');
define('DB_USERNAME', 'usr_g_fundadores_prod');
define('DB_PASSWORD', 'G8mdj!$2UqmF=XIh');
define('DB_CHARSET', 'utf8mb4');
define('DB_TYPE', 'mysql');
define('DB_DSN', DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// ========================================
// ID NUMÉRICO DEL TENANT (= tenants.id en la BD maestra)
// Lo lee TenantContext::id() para aislar las filas por id_tenant.
// ========================================
define('TENANT_ID', 5);


// =============================================
// CONFIGURACIÓN VAPID - PUSH NOTIFICATIONS
// =============================================
define('VAPID_PUBLIC_KEY', 'BObkU8JSPUs8tC4Hk3m31gc_yfV9bPkrVPWxJPL9qpFd3wSnL8q4kDBTcnrYWn4ll9CUv5rcyebb8jU5o9ZL1vQ');
define('VAPID_PRIVATE_KEY', 'HnpvQ11fiBrnqyQOs_mFNvEdMeiE-RM1-WtxCoQ2B6o');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');