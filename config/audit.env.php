<?php
// ===================================================================
// Config de auditoría - GENIALISIS
// Por ahora comparte la misma BD de auditoría que Psyncronia.
// ===================================================================
define('DB_AUDIT_HOST', '132.148.181.209');
define('DB_AUDIT_NAME', 'g_auditoria_prod');
define('DB_AUDIT_USERNAME', 'usr_g_auditoria_prod');
define('DB_AUDIT_PASSWORD', 'HWKayBss0jGXw}J(');
define('DB_AUDIT_CHARSET', 'utf8mb4');
define('DB_AUDIT_DSN', 'mysql:host=' . DB_AUDIT_HOST . ';dbname=' . DB_AUDIT_NAME . ';charset=' . DB_AUDIT_CHARSET);

// ===================================================================
// Rutas que NO se auditan (coincidencia parcial por texto).
// Formato:
//   '/ruta'      -> excluye cualquier método
//   'GET /ruta'  -> excluye solo ese método; los POST/PUT/DELETE se auditan
// Aquí solo se excluyen GET de catálogos de lookup (listas para selects).
// ===================================================================
define('AUDIT_RUTAS_EXCLUIDAS', [
    // --- wa ---
    '/wa-conversaciones/panel',
    // --- Transversales / globales ---
    'GET /generos', 'GET /niveles-escolaridad', 'GET /tipos-dias',
    'GET /tipos-evento-calendario', 'GET /dias-semana', 'GET /paises',
    'GET /ciudades', 'GET /departamentos', 'GET /tipos-identificacion',
    'GET /esferas-desarrollo', 'GET /cargos',

    // --- Académico (catálogos) ---
    'GET /tipos-actividades-academicas', 'GET /competencias-cognitivas',
    'GET /ejes-curriculares', 'GET /estandares-basicos',

    // --- Colaboradores / RRHH (catálogos) ---
    'GET /roles-colaborador', 'GET /motivos-retiro', 'GET /categorias-actividades',
    'GET /estados-actividades', 'GET /tipos-contabilizacion', 'GET /tipos-contrato',
    'GET /tipos-actividades-colaboradores', 'GET /estados-tareas-colaboradores',
    'GET /tipos-tareas-colaboradores', 'GET /tipos-puntos',

    // --- CRM / Visitas (catálogos) ---
    'GET /tipos-contacto', 'GET /tipos-como-conocio', 'GET /tipos-parentesco',
    'GET /tipos-razones-busqueda', 'GET /tipos-nivel-interes', 'GET /tipos-urgencia',
    'GET /tipos-cuando-seguimiento', 'GET /tipos-quien-decide', 'GET /tipos-compromisos',
    'GET /tipos-objeciones', 'GET /tipos-resultado-visita', 'GET /servicios-jardin',
    'GET /tipos-importancia-detalle', 'GET /tipos-nivel-agradecimiento',
    'GET /servicios-faltantes', 'GET /tipos-importancia-servicio-faltante',
    'GET /aspectos-mejorar', 'GET /tipos-validez-feedback', 'GET /tipos-perfil-economico',
    'GET /tipos-nivel-exigencia', 'GET /tipos-semaforo-cliente',
    'GET /tipos-inclinacion-decision', 'GET /tipos-preferencias-seguimiento',
    'GET /protocolo-pasos', 'GET /parametros-disc',

    // --- Finanzas / Cuentas por cobrar (catálogos) ---
    'GET /tipos-medios-pago-financieros', 'GET /tipos-movimientos-financieros',
    'GET /categorias-movimientos-financieros', 'GET /conceptos-financieros',
    'GET /conceptos-pago', 'GET /tipos-pagos', 'GET /tipos-evento-cobro',
    'GET /periodicidad-cobro', 'GET /categoria-productos-servicios',
    'GET /clasificacion-productos-servicios',

    // --- Estudiantes (catálogos) ---
    'GET /tipos-observaciones-estudiantes', 'GET /tipos-acudiente',
    'GET /tipos-necesidades-especiales', 'GET /tipos-datos-medicos',
    'GET /tipos-datos-adicionales', 'GET /tipos-plantillas',
    'GET /tipos-autorizacion-recoger',

    // --- Préstamos (catálogos) ---
    'GET /tipos-prestamo', 'GET /tipos-descuento-prestamo', 'GET /estados-prestamo',
    'GET /estados-cuota-prestamo', 'GET /tipos-pago-prestamo',

    // --- Nómina (catálogos) ---
    'GET /conceptos-nomina', 'GET /estados-nomina',

    // --- Productos / Inventario (catálogos) ---
    'GET /tipos-producto', 'GET /unidades-medida',
    'GET /clasificacion-productos-alimentacion', 'GET /conceptos-movimiento',
    'GET /estados-movimientos-productos', 'GET /tipos-proveedor',

    // --- Limpieza / Infraestructura (catálogos) ---
    'GET /tipos-proceso-limpieza', 'GET /periodicidad', 'GET /condiciones-elemento',
    'GET /estados-registro-limpieza',

    // --- Menús (catálogos) ---
    'GET /porciones', 'GET /clasificacion-menus',

    // --- Tracking BLE (catálogos) ---
    'GET /tipos-eventos-tracking'
]);