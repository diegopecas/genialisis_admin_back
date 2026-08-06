<?php

// HistorialRecordatoriosGenerales
Flight::route('GET /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'getAll']);
Flight::route('GET /historial-recordatorios-generales/estudiante/@id', [HistorialRecordatoriosGenerales::class, 'getByEstudiante']);
Flight::route('POST /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'new']);
Flight::route('PUT /historial-recordatorios-generales', [HistorialRecordatoriosGenerales::class, 'replace']);
