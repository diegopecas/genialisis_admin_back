<?php
// DASHBOARD GERENCIAL
Flight::route('GET /dashboard-gerencial/resumen', [DashboardGerencial::class, 'getResumen']);
Flight::route('GET /dashboard-gerencial/colaboradores-detalle', [DashboardGerencial::class, 'getColaboradoresDetalle']);
Flight::route('GET /dashboard-gerencial/cartera-resumen', [DashboardGerencial::class, 'getCarteraResumen']);
Flight::route('GET /dashboard-gerencial/cartera-detalle', [DashboardGerencial::class, 'getCarteraDetalle']);
Flight::route('GET /dashboard-gerencial/recaudo-resumen', [DashboardGerencial::class, 'getRecaudoResumen']);
Flight::route('GET /dashboard-gerencial/recaudo-detalle', [DashboardGerencial::class, 'getRecaudoDetalle']);
Flight::route('GET /dashboard-gerencial/movimientos-resumen', [DashboardGerencial::class, 'getMovimientosResumen']);
Flight::route('GET /dashboard-gerencial/movimientos-detalle', [DashboardGerencial::class, 'getMovimientosDetalle']);