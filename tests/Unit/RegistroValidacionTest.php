<?php

uses(Tests\TestCase::class);

use Illuminate\Support\Facades\Validator;

// Reglas base del validador/FormRequest de registro en Skyed
function obtenerReglasRegistro(array $correosExistentes = []): array {
    return [
        'nombre'   => ['required', 'string', 'max:100'],
        'email'    => [
            'required',
            'string',
            'email',
            function ($attribute, $value, $fail) use ($correosExistentes) {
                if (in_array(strtolower($value), array_map('strtolower', $correosExistentes))) {
                    $fail('El correo electrónico ya ha sido registrado.');
                }
            },
        ],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];
}

test('CP-UNI-VAL-001: aprueba estructura de datos válida para registro de nuevo usuario', function () {
    $datos = [
        'nombre'                => 'Sara Lopez',
        'email'                 => 'sara.nuevo@skyed.com',
        'password'              => 'Skyed2026*',
        'password_confirmation' => 'Skyed2026*',
    ];

    $validator = Validator::make($datos, obtenerReglasRegistro());

    expect($validator->passes())->toBeTrue();
});

test('CP-UNI-VAL-001: rechaza formato de correo inválido o campos vacíos', function () {
    $datos = [
        'nombre'                => '',
        'email'                 => 'correo-sin-formato-valido',
        'password'              => '123',
        'password_confirmation' => '456',
    ];

    $validator = Validator::make($datos, obtenerReglasRegistro());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('email'))->toBeTrue()
        ->and($validator->errors()->has('nombre'))->toBeTrue()
        ->and($validator->errors()->has('password'))->toBeTrue();
});

test('CP-UNI-VAL-001: rechaza el registro cuando el correo ya existe (correo duplicado)', function () {
    $correosEnBaseDeDatos = ['registrado@skyed.com', 'admin@skyed.com'];

    $datosDuplicados = [
        'nombre'                => 'Usuario Duplicado',
        'email'                 => 'registrado@skyed.com',
        'password'              => 'Skyed2026*',
        'password_confirmation' => 'Skyed2026*',
    ];

    $validator = Validator::make($datosDuplicados, obtenerReglasRegistro($correosEnBaseDeDatos));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('email'))->toContain('ya ha sido registrado');
});