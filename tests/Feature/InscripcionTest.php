<?php

use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\QrEntrada;

function crearEventoParaInscripcion(array $overrides = []): EventoDeportivo
{
    return EventoDeportivo::create(array_merge([
        'nombre_e' => 'Ciclo Ruta Boyacá',
        'categoria_e' => 'ciclismo',
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Parque principal, Tunja',
        'descripcion_e' => 'Recorrido de integración.',
        'requisitos_e' => 'Casco obligatorio.',
        'imagen_e' => 'eventos/ciclo-ruta.jpg',
        'cupos_disponibles_e' => 2,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ], $overrides));
}

function datosInscripcion(array $overrides = []): array
{
    return array_merge([
        'cupo_i' => 1,
        'precio_pagado_i' => 50000,
        'contacto_emergencia_nombre' => 'María Pérez',
        'contacto_emergencia_telefono' => '3001234567',
        'contacto_emergencia_parentesco' => 'Madre',
        'metodo_pago_p' => 'transferencia',
    ], $overrides);
}

it('permite a un usuario autenticado inscribirse a un evento con cupos', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion(['cupos_disponibles_e' => 5]);

    $response = $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->assertStatus(201);

    expect(Inscripcion::count())->toBe(1);
    expect($evento->fresh()->cupos_disponibles_e)->toBe(4);

    // La transacción también debe haber creado el pago y el QR asociados.
    $inscripcionId = $response->json('inscripcion.id');
    expect(Pago::where('id_i', $inscripcionId)->exists())->toBeTrue();
    expect(QrEntrada::where('id_i', $inscripcionId)->exists())->toBeTrue();
});

it('no permite inscribirse dos veces al mismo evento', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion(['cupos_disponibles_e' => 5]);

    $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->assertStatus(201);

    $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->assertStatus(409);
});

it('no permite inscribirse si no hay cupos disponibles', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion(['cupos_disponibles_e' => 0]);

    $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->assertStatus(422);
});

it('no permite inscribirse a un evento que no está activo', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion(['estado_e' => 'inactivo']);

    $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->assertStatus(422);
});

it('el dueño de una inscripción puede verla', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion();

    $inscripcionId = $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->json('inscripcion.id');

    $this->actingAs($user)
        ->getJson("/api/inscripciones/{$inscripcionId}")
        ->assertStatus(200)
        ->assertJsonPath('inscripcion.id', $inscripcionId)
        // Al ser el dueño, sí debe ver su contacto de emergencia.
        ->assertJsonPath('inscripcion.contacto_emergencia.nombre', 'María Pérez');
});

it('un usuario ajeno no puede ver la inscripción de otro (Policy)', function () {
    $dueno = userWithRole('participante');
    $otro = userWithRole('participante');
    $evento = crearEventoParaInscripcion();

    $inscripcionId = $this->actingAs($dueno)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->json('inscripcion.id');

    $this->actingAs($otro)
        ->getJson("/api/inscripciones/{$inscripcionId}")
        ->assertStatus(403);
});

it('un adminDeportivo sí puede ver la inscripción de cualquier usuario', function () {
    $dueno = userWithRole('participante');
    $admin = userWithRole('adminDeportivo');
    $evento = crearEventoParaInscripcion();

    $inscripcionId = $this->actingAs($dueno)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->json('inscripcion.id');

    $this->actingAs($admin)
        ->getJson("/api/inscripciones/{$inscripcionId}")
        ->assertStatus(200);
});

it('el dueño puede actualizar sus datos de contacto de emergencia', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion();

    $inscripcionId = $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->json('inscripcion.id');

    $this->actingAs($user)
        ->putJson("/api/inscripciones/{$inscripcionId}", [
            'contacto_emergencia_nombre' => 'Otro Contacto',
            'contacto_emergencia_telefono' => '3009999999',
            'contacto_emergencia_parentesco' => 'Hermano',
        ])
        ->assertStatus(200)
        ->assertJsonPath('inscripcion.contacto_emergencia.nombre', 'Otro Contacto');
});

it('cancelar una inscripción repone el cupo del evento', function () {
    $user = userWithRole('participante');
    $evento = crearEventoParaInscripcion(['cupos_disponibles_e' => 5]);

    $inscripcionId = $this->actingAs($user)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", datosInscripcion())
        ->json('inscripcion.id');

    expect($evento->fresh()->cupos_disponibles_e)->toBe(4);

    $this->actingAs($user)
        ->deleteJson("/api/inscripciones/{$inscripcionId}")
        ->assertStatus(200);

    expect($evento->fresh()->cupos_disponibles_e)->toBe(5);
    expect(Inscripcion::find($inscripcionId)->estado_i)->toBe('cancelada');
});
