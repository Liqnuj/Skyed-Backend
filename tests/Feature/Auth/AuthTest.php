<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
|--------------------------------------------------------------------------
| Registro e inicio de sesión
|--------------------------------------------------------------------------
| CLAVE_VALIDA (definida en tests/Pest.php) cumple la regla real: 8+
| caracteres, mayúscula, minúscula y número, como el formulario de registro.
*/

function datosRegistro(array $overrides = []): array
{
    return array_merge([
        'tipo_documento_u' => 'cedula_ciudadania',
        'documento_u' => fake()->unique()->randomNumber(8, true),
        'nombre_u' => 'Ana',
        'apellido_u' => 'Gómez',
        'telefono_u' => fake()->unique()->numerify('3#########'),
        'correo_u' => fake()->unique()->safeEmail(),
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
        'fecha_nacimiento_u' => '2000-01-01',
    ], $overrides);
}

/* ---------------------------------------------------------------- REGISTRO */

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

it('el token del registro sirve para consultar /me', function () {
    $token = postJson('/api/register', datosRegistro(['correo_u' => 'nuevo@example.com']))
        ->assertStatus(201)
        ->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.correo_u', 'nuevo@example.com');
});

it('asigna el rol participante por defecto (contexto deportivo)', function () {
    $response = postJson('/api/register', datosRegistro());

    $roles = $response->json('user.roles');

    expect($roles)->toHaveCount(1)
        ->and($roles[0]['nombre_rol'])->toBe('participante')
        ->and($roles[0]['contexto'])->toBe('deportivo');
});

it('asigna el rol cliente cuando el contexto es social', function () {
    $response = postJson('/api/register', datosRegistro(['contexto' => 'social']));

    expect($response->json('user.roles.0.nombre_rol'))->toBe('cliente');
});

it('guarda el correo en minúsculas y sin espacios', function () {
    postJson('/api/register', datosRegistro(['correo_u' => '  Ana.Gomez@Example.COM ']))
        ->assertStatus(201)
        ->assertJsonPath('user.correo_u', 'ana.gomez@example.com');
});

it('guarda la contraseña cifrada, nunca en texto plano', function () {
    postJson('/api/register', datosRegistro(['correo_u' => 'ana@example.com']))->assertStatus(201);

    $user = User::first();
    expect($user->contrasena_u)->not->toBe(CLAVE_VALIDA)
        ->and(password_verify(CLAVE_VALIDA, $user->contrasena_u))->toBeTrue();
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

it('rechaza el correo repetido aunque cambien las mayúsculas', function () {
    postJson('/api/register', datosRegistro(['correo_u' => 'ana@example.com']))->assertStatus(201);

    postJson('/api/register', datosRegistro(['correo_u' => 'ANA@Example.com']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('correo_u');

    expect(User::count())->toBe(1);
});

it('rechaza un documento o un teléfono que ya están registrados', function () {
    $datos = datosRegistro();
    postJson('/api/register', $datos)->assertStatus(201);

    postJson('/api/register', datosRegistro(['documento_u' => $datos['documento_u']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('documento_u');

    postJson('/api/register', datosRegistro(['telefono_u' => $datos['telefono_u']]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('telefono_u');
});

it('acepta una cédula de 10 dígitos mayor a 2.147.483.647', function () {
    // En PostgreSQL la columna era integer y esto respondía 500.
    postJson('/api/register', datosRegistro(['documento_u' => 3456789012]))
        ->assertStatus(201);

    expect(User::first()->documento_u)->toEqual(3456789012);
});

it('rechaza nombres o apellidos con números o símbolos', function (string $campo, string $valor) {
    postJson('/api/register', datosRegistro([$campo => $valor]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($campo);
})->with([
    'nombre con números' => ['nombre_u', 'Ana123'],
    'nombre con símbolos' => ['nombre_u', 'Ana@#'],
    'apellido con números' => ['apellido_u', 'Gómez 2'],
]);

it('acepta el documento y el teléfono escritos con separadores', function () {
    postJson('/api/register', datosRegistro([
        'documento_u' => '1.052.345.678',
        'telefono_u' => '300 123-4567',
    ]))->assertStatus(201);

    $user = User::first();
    expect($user->documento_u)->toEqual(1052345678)
        ->and($user->telefono_u)->toBe('3001234567');
});

it('acepta nombres con tildes, ñ y espacios', function () {
    postJson('/api/register', datosRegistro(['nombre_u' => 'María José', 'apellido_u' => 'Peña Núñez']))
        ->assertStatus(201)
        ->assertJsonPath('user.nombre_u', 'María José');
});

it('rechaza teléfonos y documentos con un formato inválido', function (string $campo, string $valor) {
    postJson('/api/register', datosRegistro([$campo => $valor]))
        ->assertStatus(422)
        ->assertJsonValidationErrors($campo);
})->with([
    'teléfono con letras' => ['telefono_u', '300abc4567'],
    'teléfono muy corto' => ['telefono_u', '12345'],
    'teléfono muy largo' => ['telefono_u', '1234567890123456'],
    'documento con letras' => ['documento_u', '10AB345'],
    'documento muy corto' => ['documento_u', '1234'],
]);

it('rechaza contraseñas débiles', function (string $clave) {
    postJson('/api/register', datosRegistro([
        'contrasena_u' => $clave,
        'contrasena_u_confirmation' => $clave,
    ]))->assertStatus(422)->assertJsonValidationErrors('contrasena_u');
})->with([
    'muy corta' => ['Ab1'],
    'sin mayúscula' => ['clave2026x'],
    'sin minúscula' => ['CLAVE2026X'],
    'sin número' => ['ClaveSegura'],
]);

it('rechaza el registro si las contraseñas no coinciden', function () {
    postJson('/api/register', datosRegistro([
        'contrasena_u_confirmation' => 'otra-distinta',
    ]))->assertStatus(422)->assertJsonValidationErrors('contrasena_u');
});

it('rechaza a menores de 10 años y fechas de nacimiento en el futuro', function (string $fecha) {
    postJson('/api/register', datosRegistro(['fecha_nacimiento_u' => $fecha]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('fecha_nacimiento_u');
})->with([
    'menor de 10 años' => [now()->subYears(9)->toDateString()],
    'fecha futura' => [now()->addDay()->toDateString()],
]);

it('pide todos los campos obligatorios', function () {
    postJson('/api/register', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'tipo_documento_u', 'documento_u', 'nombre_u', 'apellido_u',
            'telefono_u', 'correo_u', 'contrasena_u', 'fecha_nacimiento_u',
        ]);
});

it('responde los errores de validación en español', function () {
    postJson('/api/register', datosRegistro(['telefono_u' => '12']))
        ->assertStatus(422)
        ->assertJsonPath('errors.telefono_u.0', 'El teléfono debe tener solo números, entre 7 y 15 dígitos.');
});

it('limita a 5 intentos de registro por minuto (throttle)', function () {
    for ($i = 0; $i < 5; $i++) {
        postJson('/api/register', datosRegistro())->assertStatus(201);
    }

    // El sexto intento en el mismo minuto debe ser bloqueado.
    postJson('/api/register', datosRegistro())->assertStatus(429);
});

/* ------------------------------------------------------------------- LOGIN */

it('permite iniciar sesión con credenciales correctas', function () {
    postJson('/api/register', datosRegistro([
        'correo_u' => 'ana@example.com',
    ]))->assertStatus(201);

    postJson('/api/login', [
        'correo_u' => 'ana@example.com',
        'contrasena_u' => CLAVE_VALIDA,
    ])->assertStatus(200)->assertJsonStructure(['token', 'user' => ['id_u', 'correo_u', 'roles']]);
});

it('inicia sesión aunque el correo se escriba con mayúsculas o espacios', function () {
    postJson('/api/register', datosRegistro(['correo_u' => 'ana@example.com']))->assertStatus(201);

    postJson('/api/login', [
        'correo_u' => '  ANA@Example.com ',
        'contrasena_u' => CLAVE_VALIDA,
    ])->assertOk();
});

it('inicia sesión con cuentas antiguas guardadas con mayúsculas', function () {
    // Cuentas creadas antes de normalizar el correo.
    $user = User::factory()->create(['correo_u' => 'Pedro.Ruiz@Example.com']);

    postJson('/api/login', [
        'correo_u' => 'pedro.ruiz@example.com',
        'contrasena_u' => 'password',
    ])->assertOk()->assertJsonPath('user.id_u', $user->id_u);
});

it('rechaza el login con contraseña incorrecta', function () {
    postJson('/api/register', datosRegistro([
        'correo_u' => 'ana@example.com',
    ]))->assertStatus(201);

    postJson('/api/login', [
        'correo_u' => 'ana@example.com',
        'contrasena_u' => 'no-es-esta',
    ])->assertStatus(401)->assertJsonMissingPath('token');
});

it('da el mismo mensaje si el correo no existe (no revela qué cuentas existen)', function () {
    $mensajeClaveMala = postJson('/api/login', [
        'correo_u' => User::factory()->create()->correo_u,
        'contrasena_u' => 'no-es-esta',
    ])->assertStatus(401)->json('message');

    $mensajeSinCuenta = postJson('/api/login', [
        'correo_u' => 'nadie@example.com',
        'contrasena_u' => 'no-es-esta',
    ])->assertStatus(401)->json('message');

    expect($mensajeSinCuenta)->toBe($mensajeClaveMala);
});

it('pide correo y contraseña para iniciar sesión', function () {
    postJson('/api/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['identificacion', 'contrasena_u']);
});

it('bloquea por un minuto tras 3 intentos de login', function () {
    $correo = User::factory()->create()->correo_u;

    for ($i = 0; $i < 3; $i++) {
        postJson('/api/login', ['correo_u' => $correo, 'contrasena_u' => 'mala'])->assertStatus(401);
    }

    postJson('/api/login', ['correo_u' => $correo, 'contrasena_u' => 'password'])->assertStatus(429);
});

it('no deja consultar /me sin iniciar sesión', function () {
    getJson('/api/me')->assertStatus(401);
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
    expect(PersonalAccessToken::find($tokenId))->toBeNull();
});
