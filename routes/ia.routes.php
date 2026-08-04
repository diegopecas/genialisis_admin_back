<?php

// IA-CHAT
Flight::route('POST /ia-chat/enviar', [IaChat::class, 'enviarMensaje']);
Flight::route('GET /ia-chat/conversaciones/@id_persona', [IaChat::class, 'listarConversaciones']);
Flight::route('GET /ia-chat/conversacion/@id_conversacion', [IaChat::class, 'obtenerConversacion']);
Flight::route('DELETE /ia-chat/conversacion/@id_conversacion', [IaChat::class, 'eliminarConversacion']);
Flight::route('GET /ia-chat/admin/log', [IaChat::class, 'obtenerLog']);
Flight::route('GET /ia-chat/acceso-institucional/@id_persona', [IaChat::class, 'verificarAccesoInstitucional']);
Flight::route('GET /ia-chat/acceso-padres/@id_persona', [IaChat::class, 'verificarAccesoPadres']);

// IA-MENSAJES
Flight::route('POST /ia/mensaje-personalizado', [IaMensajes::class, 'obtenerMensajePersonalizado']);
Flight::route('GET /ia/estadisticas', [IaMensajes::class, 'obtenerEstadisticas']);
Flight::route('PUT /ia/configuracion', [IaMensajes::class, 'actualizarConfiguracion']);

// IA-CONFIGURACION (CRUD admin)
Flight::route('GET /ia-configuracion', [IaConfiguracion::class, 'getAll']);
Flight::route('GET /ia-configuracion/@id', [IaConfiguracion::class, 'getById']);
Flight::route('PUT /ia-configuracion', [IaConfiguracion::class, 'replace']);

// GINI

// IA-COBERTURA CURRICULAR

// IA-MÁQUINA DE ACTIVIDADES

// IA-MÁQUINA DE ACTIVIDADES DE EVALUACIÓN (por logros del corte)

// IA-MEJORAR TEXTO
Flight::route('POST /ia-mejorar-texto/mejorar', [IaMejorarTexto::class, 'mejorar']);

// IA-TRANSCRIPCION AUDIO
Flight::route('POST /ia-transcripcion-audio/transcribir', [IaTranscripcionAudio::class, 'transcribir']);