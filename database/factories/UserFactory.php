<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'tipo_documento_u' => fake()->randomElement(['CC', 'TI', 'CE']),
            'documento_u' => fake()->unique()->randomNumber(8, true),
            'nombre_u' => fake()->firstName(),
            'apellido_u' => fake()->lastName(),
            'rh_u' => fake()->randomElement(['O+', 'A+', 'B+', 'AB+', 'O-', 'A-', 'B-', 'AB-']),
            'telefono_u' => fake()->unique()->numerify('##########'),
            'correo_u' => fake()->unique()->safeEmail(),
            'fecha_nacimiento_u' => fake()->date('Y-m-d', '-18 years'), // Asegura que sean mayores de 18
            'codigo' => null, // Opcional según tu migración
            'contrasena_u' => static::$password ??= Hash::make('password'),
            'estado_u' => 'activo',
            'id_inv' => null, // Asumimos null por defecto para no complicar la factoría
        ];
    }
}