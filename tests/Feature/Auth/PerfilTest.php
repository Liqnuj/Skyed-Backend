<?php

use App\Models\User;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/*
|--------------------------------------------------------------------------
| Perfil del usuario (Participant.tsx / ProfilePage.tsx)
|--------------------------------------------------------------------------
*/

it('pide iniciar sesión para editar el perfil', function () {
    putJson('/api/perfil', ['nombre_u' => 'Ana'])->assertStatus(401);
});

it('actualiza los datos propios', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/perfil', ['nombre_u' => '  María   José ', 'ciudad_u' => 'Sogamoso, Boyacá'])
        ->assertOk()
        ->assertJsonPath('user.nombre_u', 'María José')
        ->assertJsonPath('user.ciudad_u', 'Sogamoso, Boyacá');
});

it('aplica las mismas reglas que el registro', function (string $campo, mixed $valor) {
    $this->actingAs(User::factory()->create())
        ->putJson('/api/perfil', [$campo => $valor])
        ->assertStatus(422)
        ->assertJsonValidationErrors($campo);
})->with([
    'nombre con números' => ['nombre_u', 'Ana2'],
    'teléfono con letras' => ['telefono_u', '300abc4567'],
    'documento con decimales' => ['documento_u', '1234567.5'],
    'correo inválido' => ['correo_u', 'no-es-correo'],
    'nombre vacío' => ['nombre_u', ''],
]);

it('no deja usar el correo o el teléfono de otra persona', function () {
    $otro = User::factory()->create(['correo_u' => 'otro@example.com']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/perfil', ['correo_u' => 'OTRO@example.com'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('correo_u');

    $this->actingAs($user)
        ->putJson('/api/perfil', ['telefono_u' => $otro->telefono_u])
        ->assertStatus(422)
        ->assertJsonValidationErrors('telefono_u');
});

it('permite volver a guardar su propio correo y teléfono', function () {
    $user = User::factory()->create(['correo_u' => 'ana@example.com']);

    $this->actingAs($user)
        ->putJson('/api/perfil', ['correo_u' => 'ANA@example.com', 'telefono_u' => $user->telefono_u])
        ->assertOk()
        ->assertJsonPath('user.correo_u', 'ana@example.com');
});

it('cambia la contraseña cuando la actual es correcta', function () {
    $user = User::factory()->create(['correo_u' => 'ana@example.com']);

    $this->actingAs($user)
        ->putJson('/api/cambiar-contrasena', [
            'contrasena_actual' => 'password',
            'nueva_contrasena' => CLAVE_VALIDA,
            'nueva_contrasena_confirmation' => CLAVE_VALIDA,
        ])
        ->assertOk();

    postJson('/api/login', ['correo_u' => 'ana@example.com', 'contrasena_u' => CLAVE_VALIDA])->assertOk();
});

it('no cambia la contraseña si la actual es incorrecta', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson('/api/cambiar-contrasena', [
            'contrasena_actual' => 'no-es-esta',
            'nueva_contrasena' => CLAVE_VALIDA,
            'nueva_contrasena_confirmation' => CLAVE_VALIDA,
        ])
        ->assertStatus(400);
});

it('rechaza una contraseña nueva débil o igual a la actual', function (string $nueva) {
    $user = User::factory()->create(['contrasena_u' => bcrypt(CLAVE_VALIDA)]);

    $this->actingAs($user)
        ->putJson('/api/cambiar-contrasena', [
            'contrasena_actual' => CLAVE_VALIDA,
            'nueva_contrasena' => $nueva,
            'nueva_contrasena_confirmation' => $nueva,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nueva_contrasena');
})->with([
    'débil' => ['clave1234'],
    'igual a la actual' => [CLAVE_VALIDA],
]);

it('desactivar la cuenta cierra todas las sesiones', function () {
    $user = User::factory()->create();
    $user->createToken('celular');
    $user->createToken('computador');

    $this->actingAs($user)->deleteJson('/api/perfil')->assertOk();

    expect($user->fresh()->estado_u)->toBe('inactivo')
        ->and($user->tokens()->count())->toBe(0);
});
