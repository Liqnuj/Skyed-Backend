<?php

use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Invitado;
use App\Models\User;
use Illuminate\Support\Facades\Http;

/*
| Pruebas de las vulnerabilidades corregidas en la auditoría de octubre:
| cada una falla si alguien vuelve a abrir el hueco en un merge.
*/

function eventoConPrecio(float $precio): EventoDeportivo
{
    return EventoDeportivo::create([
        'nombre_e' => 'Vuelta de prueba',
        'categoria_e' => 'ruta',
        'precio_e' => $precio,
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Sogamoso',
        'descripcion_e' => 'Evento de prueba.',
        'requisitos_e' => 'Casco obligatorio.',
        'imagen_e' => 'eventos/prueba.jpg',
        'cupos_disponibles_e' => 10,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ]);
}

function invitadoDePrueba(): Invitado
{
    return Invitado::create([
        'tipo_documento' => 'CC',
        'documento_inv' => 1234567890,
        'nombre_inv' => 'Ana',
        'apellido_inv' => 'Prueba',
        'rh_inv' => 'O+',
        'telefono_inv' => '3001234567',
        'fecha_nacimiento_inv' => '1995-05-05',
    ]);
}

it('no deja que un participante vea, edite o borre los invitados de otros', function () {
    $invitado = invitadoDePrueba();
    $participante = userWithRole('participante');

    $this->actingAs($participante)->getJson('/api/invitados')->assertForbidden();
    $this->actingAs($participante)->getJson("/api/invitados/{$invitado->id_inv}")->assertForbidden();
    $this->actingAs($participante)->putJson("/api/invitados/{$invitado->id_inv}", ['nombre_inv' => 'X'])->assertForbidden();
    $this->actingAs($participante)->deleteJson("/api/invitados/{$invitado->id_inv}")->assertForbidden();

    expect($invitado->fresh()->nombre_inv)->toBe('Ana');
});

it('deja que un administrador consulte los invitados', function () {
    invitadoDePrueba();

    $this->actingAs(userWithRole('adminDeportivo'))
        ->getJson('/api/invitados')
        ->assertOk();
});

it('cobra el precio del evento aunque el navegador envie otro', function () {
    Http::fake(); // el correo pide la imagen del QR a un servicio externo
    $evento = eventoConPrecio(90000);

    $this->actingAs(userWithRole('participante'))
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", [
            'cupo_i' => 1,
            'precio_pagado_i' => 1,
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3001234567',
            'contacto_emergencia_parentesco' => 'Madre',
            'metodo_pago_p' => 'efectivo',
        ])
        ->assertCreated();

    $inscripcion = Inscripcion::with('pago')->first();
    expect((float) $inscripcion->precio_pagado_i)->toBe(90000.0);
    expect((float) $inscripcion->pago->monto_p)->toBe(90000.0);
});

it('responde 201 aunque falle el servicio externo del QR del correo', function () {
    Http::fake(['*qrserver*' => Http::failedConnection()]);
    $evento = eventoConPrecio(50000);

    $this->actingAs(userWithRole('participante'))
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", [
            'cupo_i' => 1,
            'contacto_emergencia_nombre' => 'María Pérez',
            'contacto_emergencia_telefono' => '3001234567',
            'contacto_emergencia_parentesco' => 'Madre',
        ])
        ->assertCreated();
});

it('no deja iniciar sesion a una cuenta desactivada', function () {
    $user = User::factory()->create(['estado_u' => 'inactivo']);

    $this->postJson('/api/login', ['correo_u' => $user->correo_u, 'contrasena_u' => 'password'])
        ->assertForbidden()
        ->assertJsonMissingPath('token');
});

it('anula el codigo de recuperacion tras 5 intentos fallidos', function () {
    $user = User::factory()->create();
    $user->forceFill(['codigo' => '482915', 'codigo_expira_at' => now()->addMinutes(15)])->save();

    // Los 4 primeros fallan normalmente...
    for ($i = 0; $i < 4; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"]) // otra IP en cada intento
            ->postJson('/api/verificar-codigo', ['correo_u' => $user->correo_u, 'token' => '00000' . $i])
            ->assertStatus(400);
    }

    // ...el quinto anula el código...
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->postJson('/api/verificar-codigo', ['correo_u' => $user->correo_u, 'token' => '000009'])
        ->assertStatus(429);

    // ...y ni siquiera el código correcto sirve ya.
    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
        ->postJson('/api/verificar-codigo', ['correo_u' => $user->correo_u, 'token' => '482915'])
        ->assertStatus(400);

    expect($user->fresh()->codigo)->toBeNull();
});

it('acepta el codigo de recuperacion correcto', function () {
    $user = User::factory()->create();
    $user->forceFill(['codigo' => '482915', 'codigo_expira_at' => now()->addMinutes(15)])->save();

    $this->postJson('/api/verificar-codigo', ['correo_u' => $user->correo_u, 'token' => '482915'])
        ->assertOk();
});

it('desactiva un usuario con DELETE /users/{id} en vez de responder 500', function () {
    $admin = userWithRole('adminSocial');
    $user = User::factory()->create();
    $user->createToken('sesion');

    $this->actingAs($admin)->deleteJson("/api/users/{$user->id_u}")->assertOk();

    expect($user->fresh()->estado_u)->toBe('inactivo');
    expect($user->tokens()->count())->toBe(0);

    $this->actingAs($admin)->deleteJson("/api/users/{$admin->id_u}")->assertStatus(422);
});

it('chatear con SKAI no bloquea el inicio de sesion (limites separados)', function () {
    $user = User::factory()->create();

    // Sin prefijo propio, todos los throttle compartían un contador por IP:
    // 3 mensajes a SKAI dejaban al visitante sin poder iniciar sesión.
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/asistente', ['message' => ''])->assertStatus(422);
    }

    $this->postJson('/api/login', ['correo_u' => $user->correo_u, 'contrasena_u' => 'password'])
        ->assertOk();
});

it('responde 404 y no 500 cuando el id de la ruta no es numerico', function () {
    $this->actingAs(userWithRole('participante'))
        ->getJson('/api/inscripciones/abc')
        ->assertNotFound();
});
