<?php

use App\Models\User;
use App\Models\Role;
use function Pest\Laravel\getJson;
use function Pest\Laravel\actingAs;

it('rechaza la petición con error 403 si el usuario no tiene el rol requerido', function () {
    $user = User::factory()->create();

    // Agrega $this-> antes de actingAs
    $this->actingAs($user)
         ->getJson('/api/roles')
         ->assertStatus(403);
});

it('permite el acceso y devuelve los datos si el usuario tiene el rol correcto', function () {
    $user = User::factory()->create();

    $rolAdminDeportivo = Role::factory()->create([
        'nombre_rol' => 'adminDeportivo'
    ]);

    $user->roles()->attach($rolAdminDeportivo->id_rol, ['contexto' => 'deportivo']);

    // Agrega $this-> antes de actingAs
    $this->actingAs($user)
         ->getJson('/api/roles')
         ->assertStatus(200)
        ->assertJsonStructure([
            '*' => ['id_rol', 'nombre_rol'] 
        ]);
});

it('solo permite al super administrador cambiar los roles de otros usuarios', function () {
    $usuario = userWithRole('participante', 'deportivo');
    $rolCliente = Role::firstOrCreate(['nombre_rol' => 'cliente']);
    $rolParticipante = Role::where('nombre_rol', 'participante')->firstOrFail();

    foreach ([
        userWithRole('adminDeportivo', 'deportivo'),
        userWithRole('adminSocial', 'social'),
    ] as $admin) {
        $this->actingAs($admin)
            ->postJson("/api/users/{$usuario->id_u}/roles", [
                'nombre_rol' => 'cliente',
                'contexto' => 'social',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->deleteJson("/api/users/{$usuario->id_u}/roles/{$rolParticipante->id_rol}?contexto=deportivo")
            ->assertForbidden();
    }

    expect($usuario->fresh()->roles()->where('nombre_rol', 'cliente')->exists())->toBeFalse()
        ->and($usuario->fresh()->roles()->where('nombre_rol', 'participante')->exists())->toBeTrue();

    $superAdmin = userWithRole('adminDeportivo', 'deportivo');
    $superAdmin->roles()->attach(
        Role::firstOrCreate(['nombre_rol' => 'adminSocial'])->id_rol,
        ['contexto' => 'social']
    );

    $this->actingAs($superAdmin)
        ->postJson("/api/users/{$usuario->id_u}/roles", [
            'nombre_rol' => 'cliente',
            'contexto' => 'social',
        ])
        ->assertCreated();

    $otroAdmin = userWithRole('adminDeportivo', 'deportivo');
    $this->actingAs($superAdmin)
        ->postJson("/api/users/{$otroAdmin->id_u}/roles", [
            'nombre_rol' => 'cliente',
            'contexto' => 'social',
        ])
        ->assertCreated();

    $this->actingAs($superAdmin)
        ->deleteJson("/api/users/{$otroAdmin->id_u}/roles/{$rolCliente->id_rol}?contexto=social")
        ->assertOk();

    $rolAdminDeportivo = Role::where('nombre_rol', 'adminDeportivo')->firstOrFail();
    $this->actingAs($superAdmin)
        ->deleteJson("/api/users/{$otroAdmin->id_u}/roles/{$rolAdminDeportivo->id_rol}?contexto=deportivo")
        ->assertOk();

    expect($usuario->fresh()->roles()->where('nombre_rol', 'cliente')->exists())->toBeTrue()
        ->and($otroAdmin->fresh()->roles()->where('nombre_rol', 'cliente')->exists())->toBeFalse()
        ->and($otroAdmin->fresh()->roles()->where('nombre_rol', 'adminDeportivo')->exists())->toBeFalse();
});

it('no permite a un administrador crear una cuenta con rol de administrador', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $rolAdmin = Role::firstOrCreate(['nombre_rol' => 'adminDeportivo']);

    $this->actingAs($admin)
        ->postJson('/api/users', [
            'tipo_documento_u' => 'cedula_ciudadania',
            'documento_u' => 1012345678,
            'nombre_u' => 'Nuevo',
            'apellido_u' => 'Admin',
            'correo_u' => 'nuevo.admin@example.com',
            'telefono_u' => '3109998877',
            'fecha_nacimiento_u' => '1995-05-05',
            'rh_u' => 'O+',
            'contrasena_u' => CLAVE_VALIDA,
            'contrasena_u_confirmation' => CLAVE_VALIDA,
            'id_rol' => $rolAdmin->id_rol,
            'contexto' => 'deportivo',
        ])
        ->assertForbidden();
});