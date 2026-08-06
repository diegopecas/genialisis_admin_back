<?php

// Calendarios
Flight::route('GET /calendarios', [Calendarios::class, 'getAll']);
Flight::route('GET /calendarios/mes/@anio/@mes', [Calendarios::class, 'getCalendarioMes']);
Flight::route('GET /calendarios/@id', [Calendarios::class, 'getById']);
Flight::route('POST /calendarios', [Calendarios::class, 'new']);
Flight::route('PUT /calendarios', [Calendarios::class, 'replace']);
Flight::route('DELETE /calendarios', [Calendarios::class, 'delete']);
Flight::route('GET /calendarios/habiles/@fecha_inicial/@fecha_final', [Calendarios::class, 'getDiasHabiles']);
Flight::route('GET /calendarios/rango/@fecha_inicial/@fecha_final', [Calendarios::class, 'getByRangoFechas']);

// CalendariosEventos
Flight::route('GET /calendarios-eventos', [CalendariosEventos::class, 'getAll']);
Flight::route('GET /calendarios-eventos/mes/@anio/@mes', [CalendariosEventos::class, 'getByMes']);
Flight::route('GET /calendarios-eventos/@id', [CalendariosEventos::class, 'getById']);
Flight::route('POST /calendarios-eventos', [CalendariosEventos::class, 'new']);
Flight::route('PUT /calendarios-eventos', [CalendariosEventos::class, 'replace']);
Flight::route('DELETE /calendarios-eventos', [CalendariosEventos::class, 'delete']);

// TiposDias
Flight::route('GET /tipos-dias', [TiposDias::class, 'getAll']);
