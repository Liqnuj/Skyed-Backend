<?php

use App\Models\EventoDeportivo;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function datosEvento(array $overrides = []): array
{
    return array_merge([
        'nombre_e' => 'Ciclo Ruta Boyacá',
        'categoria_e' => 'ciclismo',
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Parque principal, Tunja',
        'descripcion_e' => 'Recorrido de integración ciclística.',
        'requisitos_e' => 'Casco obligatorio.',
        'imagen_e' => 'eventos/ciclo-ruta.jpg',
        'cupos_disponibles_e' => 50,
    ], $overrides);
}

/**
 * EventoDeportivo no tiene factory (el modelo no usa HasFactory),
 * así que lo creamos directo con create(), igual que hace el
 * propio controller.
 */
function crearEvento(array $overrides = []): EventoDeportivo
{
    return EventoDeportivo::create(array_merge(
        datosEvento(),
        ['estado_e' => 'activo', 'creado_e' => now()],
        $overrides
    ));
}

it('lista eventos públicamente, paginados y con el formato de EventoDeportivoResource', function () {
    crearEvento(['nombre_e' => 'Evento 1']);
    crearEvento(['nombre_e' => 'Evento 2']);
    crearEvento(['nombre_e' => 'Evento 3']);

    getJson('/api/eventos')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'nombre', 'categoria', 'imagen_url', 'cupos_disponibles', 'estado'],
            ],
            'links',
            'meta',
        ]);
});

it('muestra 404 si el evento no existe', function () {
    getJson('/api/eventos/999')->assertStatus(404);
});

it('no permite crear un evento sin autenticación', function () {
    postJson('/api/eventos', datosEvento())->assertStatus(401);
});

it('no permite crear un evento a un usuario sin el rol adminDeportivo', function () {
    $user = userWithRole('participante');

    $this->actingAs($user)
        ->postJson('/api/eventos', datosEvento())
        ->assertStatus(403);
});

it('permite a un adminDeportivo crear un evento', function () {
    $admin = userWithRole('adminDeportivo');

    $response = $this->actingAs($admin)
        ->postJson('/api/eventos', datosEvento())
        ->assertStatus(201);

    expect(EventoDeportivo::count())->toBe(1);
    expect($response->json('evento.estado'))->toBe('activo');
});

it('rechaza una categoría de evento inválida', function () {
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->postJson('/api/eventos', datosEvento(['categoria_e' => 'natacion']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('categoria_e');
});

it('permite a un adminDeportivo actualizar un evento', function () {
    $admin = userWithRole('adminDeportivo');
    $evento = crearEvento(['nombre_e' => 'Nombre viejo']);

    $this->actingAs($admin)
        ->putJson("/api/eventos/{$evento->id_e}", datosEvento(['nombre_e' => 'Nombre nuevo']))
        ->assertStatus(200)
        ->assertJsonPath('evento.nombre', 'Nombre nuevo');
});

it('permite cambiar el estado de un evento', function () {
    $admin = userWithRole('adminDeportivo');
    $evento = crearEvento(['estado_e' => 'activo']);

    $this->actingAs($admin)
        ->patchJson("/api/eventos/{$evento->id_e}/estado", ['estado_e' => 'inactivo'])
        ->assertStatus(200)
        ->assertJsonPath('evento.estado', 'inactivo');
});

it('permite a un adminDeportivo eliminar un evento', function () {
    $admin = userWithRole('adminDeportivo');
    $evento = crearEvento();

    $this->actingAs($admin)
        ->deleteJson("/api/eventos/{$evento->id_e}")
        ->assertStatus(200);

    expect(EventoDeportivo::find($evento->id_e))->toBeNull();
});
