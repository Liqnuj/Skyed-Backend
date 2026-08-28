<?php

use App\Models\User;
use function Pest\Laravel\postJson;

function datosRegistro(array $overrides = []): array
{
    return array_merge([
        'tipo_documento_u' => 'CC',
        'documento_u' => fake()->unique()->randomNumber(8, true),
        'nombre_u' => 'Ana',
        'apellido_u' => 'Gómez',
        'rh_u' => 'O+',
        'telefono_u' => fake()->unique()->numerify('3#########'),
        'correo_u' => fake()->unique()->safeEmail(),
        'contrasena_u' => 'password123',
        'contrasena_u_confirmation' => 'password123',
        'fecha_nacimiento_u' => '2000-01-01',
    ], $overrides);
}

it('registra un usuario nuevo y devuelve un token', function () {
    postJson('/api/register', datosRegistro())
        ->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'token',
            'user' => ['id_u', 'nombre_u', 'apellido_u', 'correo_u', 'roles'],
        ]);

    expect(User::count())->toBe(1);
});

it('asigna el rol participante por defecto (contexto deportivo)', function () {
    $response = postJson('/api/register', datosRegistro());

    $roles = $response->json('user.roles');

    expect($roles)->toHaveCount(1)
        ->and($roles[0]['nombre_rol'])->toBe('participante');
});

it('asigna el rol cliente cuando el contexto es social', function () {
    $response = postJson('/api/register', datosRegistro(['contexto' => 'social']));

    expect($response->json('user.roles.0.nombre_rol'))->toBe('cliente');
});

it('rechaza el registro con un correo ya usado', function () {
    $datos = datosRegistro();
    postJson('/api/register', $datos)->assertStatus(201);

    // mismo correo, pero con documento/teléfono distintos
    $segundo = datosRegistro([
        'correo_u' => $datos['correo_u'],
    ]);

    postJson('/api/register', $segundo)
        ->assertStatus(422)
        ->assertJsonValidationErrors('correo_u');
});

it('rechaza el registro si las contraseñas no coinciden', function () {
    postJson('/api/register', datosRegistro([
        'contrasena_u_confirmation' => 'otra-distinta',
    ]))->assertStatus(422)->assertJsonValidationErrors('contrasena_u');
});

it('limita a 5 intentos de registro por minuto (throttle)', function () {
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/register', datosRegistro())->assertStatus(201);
    }

    // El sexto intento en el mismo minuto debe ser bloqueado.
    postJson('/api/register', datosRegistro())->assertStatus(429);
});

it('permite iniciar sesión con credenciales correctas', function () {
    postJson('/api/register', datosRegistro([
        'correo_u' => 'ana@example.com',
        'contrasena_u' => 'password123',
        'contrasena_u_confirmation' => 'password123',
    ]))->assertStatus(201);

    postJson('/api/login', [
        'correo_u' => 'ana@example.com',
        'contrasena_u' => 'password123',
    ])->assertStatus(200)->assertJsonStructure(['token', 'user']);
});

it('rechaza el login con contraseña incorrecta', function () {
    postJson('/api/register', datosRegistro([
        'correo_u' => 'ana@example.com',
    ]))->assertStatus(201);

    postJson('/api/login', [
        'correo_u' => 'ana@example.com',
        'contrasena_u' => 'no-es-esta',
    ])->assertStatus(401);
});

it('cierra sesión e invalida el token actual', function () {
    $user = User::factory()->create();
    $nuevoToken = $user->createToken('test');
    $tokenId = $nuevoToken->accessToken->id;

    $this->withHeader('Authorization', "Bearer {$nuevoToken->plainTextToken}")
        ->postJson('/api/logout')
        ->assertStatus(200);

    // Verificamos en la base de datos, no con una segunda petición:
    // Sanctum cachea el usuario resuelto en el guard durante el mismo
    // test, así que una segunda petición con el mismo token seguiría
    // "pasando" aunque el token ya esté borrado.
    expect(\Laravel\Sanctum\PersonalAccessToken::find($tokenId))->toBeNull();
});
