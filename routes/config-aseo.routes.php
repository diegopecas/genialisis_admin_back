<?php
// CONFIGURACIÓN DE ASEO (asignación masiva de procesos a áreas)
Flight::route('GET /config-aseo/configuracion', ['AreasFisicasXProcesosLimpiezaConfig', 'getConfiguracion']);
Flight::route('POST /config-aseo/asignar-lote', ['AreasFisicasXProcesosLimpiezaConfig', 'asignarLote']);
Flight::route('POST /config-aseo/quitar-lote', ['AreasFisicasXProcesosLimpiezaConfig', 'quitarLote']);