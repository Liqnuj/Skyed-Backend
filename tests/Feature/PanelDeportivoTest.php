<?php

use App\Models\EventoDeportivo;
use App\Models\Estacion;
use App\Models\Inscripcion;
use App\Models\Pago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
| Endpoints y reglas que usa el panel de administración deportivo
| (/deportivo/admin en el frontend).
*/

function eventoDelPanel(array $overrides = []): EventoDeportivo
{
    return EventoDeportivo::create(array_merge([
        'nombre_e' => 'Gran Fondo Boyacá',
        'categoria_e' => 'ruta',
        'precio_e' => 80000,
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '06:30',
        'ubicacion_e' => 'Sogamoso',
        'descripcion_e' => 'Evento de prueba.',
        'requisitos_e' => 'Casco obligatorio.',
        'imagen_e' => 'deportivo/prueba.jpg',
        'cupos_disponibles_e' => 5,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ], $overrides));
}

function inscribirEnEvento(EventoDeportivo $evento): Inscripcion
{
    Http::fake(); // el correo pide la imagen del QR a un servicio externo

    test()->actingAs(userWithRole('participante'))
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", [
            'cupo_i' => 1,
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3001234567',
            'contacto_emergencia_parentesco' => 'Madre',
            'metodo_pago_p' => 'transferencia',
        ])
        ->assertCreated();

    return Inscripcion::latest('id_i')->first();
}

it('deja al admin deportivo subir la imagen de un evento y devuelve la ruta', function () {
    Storage::fake('public');

    $res = $this->actingAs(userWithRole('adminDeportivo'))
        ->post('/api/deportivo/imagenes', [
            'imagen' => UploadedFile::fake()->image('carrera.jpg', 800, 450),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonStructure(['url', 'path']);

    expect($res->json('path'))->toStartWith('deportivo/');
    Storage::disk('public')->assertExists($res->json('path'));
});

it('valida el kilómetro de una estación contra la distancia del evento y admite ambulancia', function () {
    $evento = eventoDelPanel(['distancia_e' => '80 km']);
    $admin = userWithRole('adminDeportivo');
    $base = [
        'nombre_est' => 'Ambulancia km 12',
        'tipo_est' => 'ambulancia',
    ];

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/estaciones", $base)
        ->assertStatus(422)
        ->assertJsonValidationErrors('kilometro_est');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/estaciones", $base + ['kilometro_est' => 80.1])
        ->assertStatus(422)
        ->assertJsonValidationErrors('kilometro_est');

    $response = $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/estaciones", $base + ['kilometro_est' => 12.5])
        ->assertCreated();

    expect(Estacion::find($response->json('estacion.id_est'))->tipo_est)->toBe('ambulancia')
        ->and((float) $response->json('estacion.kilometro_est'))->toBe(12.5);
});

it('no deja subir imagenes deportivas a un participante', function () {
    Storage::fake('public');

    $this->actingAs(userWithRole('participante'))
        ->post('/api/deportivo/imagenes', [
            'imagen' => UploadedFile::fake()->image('carrera.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertForbidden();
});

it('no elimina un evento que ya tiene inscritos', function () {
    $evento = eventoDelPanel();
    inscribirEnEvento($evento);

    $this->actingAs(userWithRole('adminDeportivo'))
        ->deleteJson("/api/eventos/{$evento->id_e}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Este evento conserva 1 inscripción en su historial (1 activa). Inhabilítalo en lugar de eliminarlo para conservar el historial.');

    expect(EventoDeportivo::find($evento->id_e))->not->toBeNull();
    expect(Inscripcion::where('id_e', $evento->id_e)->count())->toBe(1);
});

it('aclara que las inscripciones canceladas permanecen en el historial del evento', function () {
    $evento = eventoDelPanel();
    $inscripcion = inscribirEnEvento($evento);

    $this->actingAs($inscripcion->usuario)
        ->deleteJson("/api/inscripciones/{$inscripcion->id_i}")
        ->assertOk();

    $this->actingAs(userWithRole('adminDeportivo'))
        ->deleteJson("/api/eventos/{$evento->id_e}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'Este evento conserva 1 inscripción en su historial (0 activas). Inhabilítalo en lugar de eliminarlo para conservar el historial.');
});

it('devuelve el cupo al rechazar un pago pendiente', function () {
    $evento = eventoDelPanel(['cupos_disponibles_e' => 5]);
    $inscripcion = inscribirEnEvento($evento);
    expect($evento->fresh()->cupos_disponibles_e)->toBe(4);

    $pago = Pago::where('id_i', $inscripcion->id_i)->first();

    $this->actingAs(userWithRole('adminDeportivo'))
        ->patchJson("/api/pagos/{$pago->id_pago}/estado", ['estado_p' => 'rechazado'])
        ->assertOk();

    expect($evento->fresh()->cupos_disponibles_e)->toBe(5);
    expect($inscripcion->fresh()->estado_i)->toBe('cancelada');
});

it('al cancelar una inscripcion su pago pendiente queda cancelado', function () {
    $evento = eventoDelPanel();
    $inscripcion = inscribirEnEvento($evento);
    $pago = Pago::where('id_i', $inscripcion->id_i)->first();

    $this->actingAs($inscripcion->usuario)
        ->deleteJson("/api/inscripciones/{$inscripcion->id_i}")
        ->assertOk();

    expect($pago->fresh()->estado_p)->toBe('cancelado');

    // Y ya no se puede confirmar desde el panel.
    $this->actingAs(userWithRole('adminDeportivo'))
        ->patchJson("/api/pagos/{$pago->id_pago}/estado", ['estado_p' => 'confirmado'])
        ->assertStatus(422);
    expect($inscripcion->fresh()->estado_i)->toBe('cancelada');
});

it('confirma un pago y deja el QR listo para el control de acceso', function () {
    $evento = eventoDelPanel();
    $inscripcion = inscribirEnEvento($evento);
    $pago = Pago::where('id_i', $inscripcion->id_i)->first();
    $admin = userWithRole('adminDeportivo');

    $this->actingAs($admin)
        ->patchJson("/api/pagos/{$pago->id_pago}/estado", ['estado_p' => 'confirmado'])
        ->assertOk();

    $codigo = $inscripcion->fresh()->qr->codigo_qr;

    $this->actingAs($admin)->postJson('/api/qr/validar', ['codigo_qr' => $codigo])->assertOk();
    // Un segundo intento con el mismo QR ya no debe pasar.
    $this->actingAs($admin)->postJson('/api/qr/validar', ['codigo_qr' => $codigo])->assertStatus(422);
});
