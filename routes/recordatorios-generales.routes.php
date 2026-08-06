<?php

// HistorialRecordatoriosGenerales
Flight::route('GET /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'getAll']);
Flight::route('GET /historial-recordatorios-generales/cliente/@id', [HistorialRecordatoriosGenerales::class, 'getByCliente']);
Flight::route('POST /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'new']);
Flight::route('PUT /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'replace']);
