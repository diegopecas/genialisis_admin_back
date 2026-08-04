<?php

// ========================================
// CONFIGURACIÓN BD PRINCIPAL (GENIALISIS)
// ========================================
//define('DB_HOST', '92.205.2.161');
//define('DB_NAME', 'lumen_academico_prod');
//define('DB_USERNAME', 'liceo_lumen_prod');
//define('DB_PASSWORD', 'lVuAT1xn2Q-j');
define('DB_HOST', '132.148.181.209');
define('DB_NAME', 'g_lumen_prod');
define('DB_USERNAME', 'usr_g_lumen_prod');
define('DB_PASSWORD', 'NPv;(Fp#(zPmr{QH');
define('DB_CHARSET', 'utf8mb4');
define('DB_TYPE', 'mysql');
define('DB_DSN', DB_TYPE . ':host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET);

// ========================================
// CONFIGURACIÓN BD PORTAL (OPCIONAL)
// Solo para tenants que tienen portal web
// ========================================
define('DB_PORTAL_HOST', '92.205.2.161');
define('DB_PORTAL_NAME', 'lumen');
define('DB_PORTAL_USERNAME', 'lumen-admin');
define('DB_PORTAL_PASSWORD', 'm_deEi1U2Bjl');
define('DB_PORTAL_CHARSET', 'utf8mb4');
define('DB_PORTAL_TYPE', 'mysql');
define('DB_PORTAL_DSN', DB_PORTAL_TYPE . ':host=' . DB_PORTAL_HOST . ';dbname=' . DB_PORTAL_NAME . ';charset=' . DB_PORTAL_CHARSET);

// ========================================
// ID NUMÉRICO DEL TENANT (= tenants.id en la BD maestra)
// Lo lee TenantContext::id() para aislar las filas por id_tenant.
// ========================================
define('TENANT_ID', 1);


// =============================================
// CONFIGURACIÓN VAPID - PUSH NOTIFICATIONS
// =============================================
define('VAPID_PUBLIC_KEY', 'BObkU8JSPUs8tC4Hk3m31gc_yfV9bPkrVPWxJPL9qpFd3wSnL8q4kDBTcnrYWn4ll9CUv5rcyebb8jU5o9ZL1vQ');
define('VAPID_PRIVATE_KEY', 'HnpvQ11fiBrnqyQOs_mFNvEdMeiE-RM1-WtxCoQ2B6o');
define('VAPID_SUBJECT', 'mailto:contacto@genialisis.com');