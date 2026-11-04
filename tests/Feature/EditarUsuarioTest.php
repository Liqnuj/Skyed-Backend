<?php

use App\Models\User;

it('un administrador edita los datos de un usuario de su panel', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $participante = userWithRole('participante', 'deportivo');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$participante->id_u}", [
            'nombre_u' => 'Camila',
            'apellido_u' => 'Rojas Pérez',
            'correo_u' => ' Camila.Rojas@Correo.COM ',
            'telefono_u' => '310 123 4567',
            'rh_u' => 'O+',
        ])
        ->assertOk()
        ->assertJsonPath('user.nombre_u', 'Camila');

    $guardado = $participante->fresh();
    expect($guardado->apellido_u)->toBe('Rojas Pérez')
        ->and($guardado->correo_u)->toBe('camila.rojas@correo.com')
        ->and($guardado->telefono_u)->toBe('3101234567')
        ->and($guardado->rh_u)->toBe('O+');
});

it('no deja usar el correo o documento de otra persona', function () {
    $admin = userWithRole('adminSocial', 'social');
    $uno = userWithRole('cliente', 'social');
    $otro = userWithRole('cliente', 'social');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$uno->id_u}", ['correo_u' => $otro->correo_u])
        ->assertStatus(422)
        ->assertJsonValidationErrors('correo_u');
});

it('un admin de módulo no puede editar a otro administrador', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $otroAdmin = userWithRole('adminDeportivo', 'deportivo');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$otroAdmin->id_u}", ['correo_u' => 'tomado@correo.com'])
        ->assertForbidden();

    expect($otroAdmin->fresh()->correo_u)->not->toBe('tomado@correo.com');
});

it('el super administrador sí puede editar a un administrador', function () {
    $super = userWithRole('adminSocial', 'social');
    $super->roles()->attach(\App\Models\Role::firstOrCreate(['nombre_rol' => 'adminDeportivo'])->id_rol, ['contexto' => 'deportivo']);
    $admin = userWithRole('adminDeportivo', 'deportivo');

    $this->actingAs($super, 'sanctum')
        ->putJson("/api/users/{$admin->id_u}", ['nombre_u' => 'Laura'])
        ->assertOk();

    expect($admin->fresh()->nombre_u)->toBe('Laura');
});

it('al desactivar a un usuario se cierran sus sesiones', function () {
    $admin = userWithRole('adminSocial', 'social');
    $cliente = userWithRole('cliente', 'social');
    $cliente->createToken('sesion');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$cliente->id_u}", ['estado_u' => 'inactivo'])
        ->assertOk();

    expect($cliente->fresh()->estado_u)->toBe('inactivo')
        ->and($cliente->tokens()->count())->toBe(0);
});

it('un administrador no puede desactivarse a sí mismo desde aquí', function () {
    $admin = userWithRole('adminSocial', 'social');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/users/{$admin->id_u}", ['estado_u' => 'inactivo'])
        ->assertStatus(422);

    expect($admin->fresh()->estado_u)->not->toBe('inactivo');
});

it('un participante no puede editar usuarios', function () {
    $participante = userWithRole('participante', 'deportivo');
    $otro = userWithRole('participante', 'deportivo');

    $this->actingAs($participante, 'sanctum')
        ->putJson("/api/users/{$otro->id_u}", ['nombre_u' => 'Hacker'])
        ->assertForbidden();

    expect(User::find($otro->id_u)->nombre_u)->not->toBe('Hacker');
});
