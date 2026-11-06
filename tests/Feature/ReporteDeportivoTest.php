<?php

it('genera PDF y Excel para todas las tablas del panel deportivo', function () {
    $admin = userWithRole('adminDeportivo');
    $reportes = [
        'eventos' => ['categoria' => 'ruta', 'estado' => 'activo', 'fecha' => 'proximos', 'cupos' => 'con'],
        'inscripciones-pagos' => ['estado' => 'pendiente', 'evento' => '1', 'metodo' => 'transferencia'],
        'resultados' => ['evento' => '1', 'estado' => 'oficial'],
        'kits' => ['stock' => 'bajo', 'lugar' => 'Tunja'],
        'entregas-kits' => ['estado' => 'entregado', 'evento' => '1', 'rol' => 'titular'],
        'patrocinadores' => [],
        'rutas' => ['evento' => '1'],
        'estaciones' => ['evento' => '1'],
        'usuarios' => ['rol' => 'participante', 'estado' => 'activo'],
    ];

    foreach ($reportes as $reporte => $filtros) {
        foreach (['pdf', 'xlsx'] as $formato) {
            $this->actingAs($admin)
                ->get('/api/reportes/deportivo/' . $reporte . '/' . $formato . '?' . http_build_query(array_merge(['q' => 'prueba'], $filtros)))
                ->assertOk();
        }
    }
});
