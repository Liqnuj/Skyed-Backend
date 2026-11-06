<?php

use App\Models\EventoDeportivo;
use App\Models\Patrocinador;
use Illuminate\Support\Facades\DB;

function crearEventoParaCatalogosDeportivos(): EventoDeportivo
{
    return EventoDeportivo::create([
        'nombre_e' => 'Gran Fondo de prueba',
        'categoria_e' => 'ruta',
        'precio_e' => 50000,
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Tunja',
        'descripcion_e' => 'Evento para probar los catálogos.',
        'requisitos_e' => 'Casco obligatorio.',
        'imagen_e' => 'eventos/prueba.jpg',
        'cupos_disponibles_e' => 50,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ]);
}

it('permite al admin deportivo asignar, actualizar y desvincular un patrocinador de un evento', function () {
    $evento = crearEventoParaCatalogosDeportivos();
    $patrocinador = Patrocinador::create(['nombre_p' => 'Patrocinador de prueba']);
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->postJson("/api/patrocinadores/{$patrocinador->id_p}/eventos", [
            'evento_id_e' => $evento->id_e,
            'detalle' => 'Aporte inicial',
        ])
        ->assertOk()
        ->assertJsonPath('patrocinador.eventos.0.pivot.detalle', 'Aporte inicial');

    $this->actingAs($admin)
        ->postJson("/api/patrocinadores/{$patrocinador->id_p}/eventos", [
            'evento_id_e' => $evento->id_e,
            'detalle' => 'Aporte actualizado',
        ])
        ->assertOk()
        ->assertJsonPath('patrocinador.eventos.0.pivot.detalle', 'Aporte actualizado');

    expect(DB::table('evento_patrocinador')
        ->where('patrocinador_id_p', $patrocinador->id_p)
        ->where('evento_id_e', $evento->id_e)
        ->count())->toBe(1);

    $this->actingAs($admin)
        ->deleteJson("/api/patrocinadores/{$patrocinador->id_p}/eventos/{$evento->id_e}")
        ->assertOk();

    expect(DB::table('evento_patrocinador')
        ->where('patrocinador_id_p', $patrocinador->id_p)
        ->where('evento_id_e', $evento->id_e)
        ->exists())->toBeFalse();
});

it('permite al admin deportivo crear rutas y estaciones asociadas a un evento', function () {
    $evento = crearEventoParaCatalogosDeportivos();
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/rutas", [
            'nombre_re' => 'Ruta corta',
            'distancia_re' => '25 km',
            'desnivel_re' => '500 m',
            'archivo_gpx_re' => 'https://example.test/ruta.gpx',
        ])
        ->assertCreated()
        ->assertJsonPath('ruta.id_e', (string) $evento->id_e)
        ->assertJsonPath('ruta.nombre_re', 'Ruta corta');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/estaciones", [
            'nombre_est' => 'Punto de hidratación',
            'tipo_est' => 'hidratacion',
            'kilometro_est' => '10 km',
            'latitud_est' => 5.5353,
            'longitud_est' => -73.3678,
            'estado_est' => 'activa',
        ])
        ->assertCreated()
        ->assertJsonPath('estacion.id_e', (string) $evento->id_e)
        ->assertJsonPath('estacion.nombre_est', 'Punto de hidratación');
});
