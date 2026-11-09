<?php

use App\Models\User;

it('los usuarios normales salen en los dos paneles y el personal solo en el suyo', function () {
    $participante = userWithRole('participante', 'deportivo');
    $cliente = userWithRole('cliente', 'social');
    $asistenteSocial = userWithRole('asistente', 'social');
    $adminDeportivo = userWithRole('adminDeportivo', 'deportivo');
    $adminSocial = userWithRole('adminSocial', 'social');
    $sinRol = User::factory()->create();

    $social = User::delModulo('social')->pluck('id_u')->all();
    $deportivo = User::delModulo('deportivo')->pluck('id_u')->all();

    // El registro es uno solo: participantes y clientes están en ambos.
    expect($social)->toContain($participante->id_u, $cliente->id_u, $asistenteSocial->id_u, $adminSocial->id_u, $sinRol->id_u)
        ->not->toContain($adminDeportivo->id_u);

    expect($deportivo)->toContain($participante->id_u, $cliente->id_u, $adminDeportivo->id_u, $sinRol->id_u)
        ->not->toContain($asistenteSocial->id_u)
        ->not->toContain($adminSocial->id_u);
});

it('quien administra los dos módulos no sale en ninguna tabla de módulo', function () {
    $doble = userWithRole('adminDeportivo', 'deportivo');
    $doble->roles()->attach(\App\Models\Role::firstOrCreate(['nombre_rol' => 'adminSocial'])->id_rol, ['contexto' => 'social']);

    expect(User::delModulo('social')->pluck('id_u'))->not->toContain($doble->id_u)
        ->and(User::delModulo('deportivo')->pluck('id_u'))->not->toContain($doble->id_u);
});

it('quien es cliente y participante aparece en los dos paneles', function () {
    $ambos = userWithRole('participante', 'deportivo');
    $ambos->roles()->attach(\App\Models\Role::firstOrCreate(['nombre_rol' => 'cliente'])->id_rol, ['contexto' => 'social']);

    expect(User::delModulo('social')->pluck('id_u'))->toContain($ambos->id_u)
        ->and(User::delModulo('deportivo')->pluck('id_u'))->toContain($ambos->id_u);
});

it('un admin que crea un participante lo deja también como cliente', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $rol = \App\Models\Role::firstOrCreate(['nombre_rol' => 'participante']);

    $this->actingAs($admin)->postJson('/api/users', [
        'tipo_documento_u' => 'cedula_ciudadania', 'documento_u' => 1012345678,
        'nombre_u' => 'Laura', 'apellido_u' => 'Rojas', 'correo_u' => 'laura.rojas@example.com',
        'telefono_u' => '3109998877', 'fecha_nacimiento_u' => '1995-05-05', 'rh_u' => 'O+',
        'contrasena_u' => 'Clave#Segura2026', 'contrasena_u_confirmation' => 'Clave#Segura2026',
        'id_rol' => $rol->id_rol, 'contexto' => 'deportivo',
    ])->assertCreated();

    $roles = User::where('correo_u', 'laura.rojas@example.com')->first()->roles
        ->map(fn ($r) => $r->nombre_rol . '/' . $r->pivot->contexto)->sort()->values()->all();

    expect($roles)->toBe(['cliente/social', 'participante/deportivo']);
});

it('la migración completa los roles base de los usuarios que ya existían', function () {
    $soloParticipante = userWithRole('participante', 'deportivo');
    $soloAdmin = userWithRole('adminSocial', 'social');

    (require database_path('migrations/2026_11_09_000001_completar_roles_base_de_usuarios.php'))->up();

    expect($soloParticipante->fresh()->roles->pluck('nombre_rol')->sort()->values()->all())->toBe(['cliente', 'participante'])
        ->and($soloAdmin->fresh()->roles->pluck('nombre_rol')->all())->toBe(['adminSocial']);
});
