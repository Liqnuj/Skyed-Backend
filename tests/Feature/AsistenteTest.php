<?php

use App\Models\Asistente;
use App\Models\User;

it('requires an authenticated administrator to list assistants', function () {
    $this->getJson('/api/asistentes?modulo=social')->assertUnauthorized();

    $user = userWithRole('cliente', 'social');
    $this->actingAs($user)
        ->getJson('/api/asistentes?modulo=social')
        ->assertForbidden();
});

it('lists only assistants owned by the authenticated admin in the requested module', function () {
    $admin = userWithRole('adminSocial', 'social');
    $otherAdmin = userWithRole('adminSocial', 'social');
    $asistente = User::factory()->create();
    $otroAsistente = User::factory()->create();

    Asistente::create([
        'id_admin' => $admin->id_u,
        'id_usuario' => $asistente->id_u,
        'modulo' => 'social',
        'permisos' => ['eventos' => 'editar'],
        'activo' => true,
    ]);
    Asistente::create([
        'id_admin' => $otherAdmin->id_u,
        'id_usuario' => $otroAsistente->id_u,
        'modulo' => 'social',
        'permisos' => ['pqr' => 'ver'],
        'activo' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/asistentes?modulo=social')
        ->assertOk()
        ->assertJsonPath('limite', 10)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nombre', $asistente->nombre_u)
        ->assertJsonPath('data.0.permisos.eventos', 'editar');
});

it('creates an assistant account with module-scoped permissions', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');

    $this->actingAs($admin)
        ->postJson('/api/asistentes?modulo=deportivo', [
            'tipo_documento_u' => 'CC',
            'documento_u' => 345678912,
            'nombre_u' => 'Ana María',
            'apellido_u' => 'Pérez Gómez',
            'telefono_u' => '3012345678',
            'correo_u' => 'ana.asistente@example.test',
            'fecha_nacimiento_u' => '1990-05-12',
            'contrasena_u' => 'Asistente2026',
            'contrasena_u_confirmation' => 'Asistente2026',
            'permisos' => ['eventos' => 'editar', 'acceso' => 'ver'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.correo', 'ana.asistente@example.test')
        ->assertJsonPath('data.modulo', 'deportivo')
        ->assertJsonPath('data.permisos.acceso', 'ver');

    $this->assertDatabaseHas('asistente', [
        'id_admin' => $admin->id_u,
        'modulo' => 'deportivo',
        'activo' => true,
    ]);
    $this->assertDatabaseHas('usuario', [
        'correo_u' => 'ana.asistente@example.test',
        'tipo_documento_u' => 'CC',
    ]);
});

it('rejects duplicate accounts and permissions from another module', function () {
    $admin = userWithRole('adminSocial', 'social');
    $existing = User::factory()->create(['correo_u' => 'ocupado@example.test']);
    $data = [
        'tipo_documento_u' => 'CC',
        'documento_u' => 345678913,
        'nombre_u' => 'Carlos',
        'apellido_u' => 'Prueba',
        'telefono_u' => '3012345679',
        'correo_u' => $existing->correo_u,
        'fecha_nacimiento_u' => '1990-05-12',
        'contrasena_u' => 'Asistente2026',
        'contrasena_u_confirmation' => 'Asistente2026',
        'permisos' => ['eventos' => 'editar'],
    ];

    $this->actingAs($admin)
        ->postJson('/api/asistentes?modulo=social', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('correo_u');

    $data['correo_u'] = 'nuevo@example.test';
    $data['permisos'] = ['inscripciones' => 'ver'];
    $this->actingAs($admin)
        ->postJson('/api/asistentes?modulo=social', $data)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permisos');
});

it('prevents an administrator from managing assistants in another module', function () {
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin)
        ->getJson('/api/asistentes?modulo=deportivo')
        ->assertForbidden();
});

it('limits assistant actions to the exact level granted for each section', function () {
    $asistente = userWithRole('asistente', 'social');
    Asistente::create([
        'id_admin' => userWithRole('adminSocial', 'social')->id_u,
        'id_usuario' => $asistente->id_u,
        'modulo' => 'social',
        'permisos' => ['eventos' => 'eliminar', 'usuarios' => 'ver', 'reservas' => 'ver', 'pqr' => 'ver'],
        'activo' => true,
    ]);

    $this->actingAs($asistente)
        ->getJson('/api/users')
        ->assertOk();

    $this->actingAs($asistente)
        ->getJson('/api/reservas/resumen')
        ->assertOk();

    $this->actingAs($asistente)
        ->postJson('/api/pqr/999999/abrir')
        ->assertForbidden();

    $this->actingAs($asistente)
        ->postJson('/api/eventos-sociales', [])
        ->assertForbidden();

    $this->actingAs($asistente)
        ->deleteJson('/api/eventos-sociales/999999')
        ->assertNotFound();

    $this->actingAs($asistente)
        ->postJson('/api/ambientes', [])
        ->assertForbidden();
});

it('allows editing without granting deletion', function () {
    $asistente = userWithRole('asistente', 'social');
    Asistente::create([
        'id_admin' => userWithRole('adminSocial', 'social')->id_u,
        'id_usuario' => $asistente->id_u,
        'modulo' => 'social',
        'permisos' => ['eventos' => 'editar'],
        'activo' => true,
    ]);

    $this->actingAs($asistente)
        ->postJson('/api/eventos-sociales', [])
        ->assertUnprocessable();

    $this->actingAs($asistente)
        ->deleteJson('/api/eventos-sociales/999999')
        ->assertForbidden();
});

it('updates, deactivates, reactivates and removes an assistant without deleting the account', function () {
    $admin = userWithRole('adminSocial', 'social');
    $asistente = User::factory()->create();
    $registro = Asistente::create([
        'id_admin' => $admin->id_u,
        'id_usuario' => $asistente->id_u,
        'modulo' => 'social',
        'permisos' => ['eventos' => 'ver'],
        'activo' => true,
    ]);

    $this->actingAs($admin)
        ->putJson("/api/asistentes/{$registro->id_asistente}?modulo=social", [
            'nombre_u' => 'Nombre Actualizado',
            'apellido_u' => $asistente->apellido_u,
            'correo_u' => $asistente->correo_u,
            'telefono_u' => $asistente->telefono_u,
            'permisos' => ['eventos' => 'editar'],
        ])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Nombre Actualizado')
        ->assertJsonPath('data.permisos.eventos', 'editar');

    $this->actingAs($admin)
        ->patchJson("/api/asistentes/{$registro->id_asistente}/estado?modulo=social", ['activo' => false])
        ->assertOk()
        ->assertJsonPath('data.activo', false);

    $this->actingAs($admin)
        ->patchJson("/api/asistentes/{$registro->id_asistente}/estado?modulo=social", ['activo' => true])
        ->assertOk()
        ->assertJsonPath('data.activo', true);

    $this->actingAs($admin)
        ->deleteJson("/api/asistentes/{$registro->id_asistente}?modulo=social")
        ->assertOk();

    $this->assertDatabaseMissing('asistente', ['id_asistente' => $registro->id_asistente]);
    $this->assertDatabaseHas('usuario', ['id_u' => $asistente->id_u]);
});
