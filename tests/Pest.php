<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Http;

uses(
    Tests\TestCase::class,
        Illuminate\Foundation\Testing\RefreshDatabase::class,
)
    // Ninguna prueba sale a internet (Gemini, el servicio del QR, etc.):
    // así dan el mismo resultado en cualquier computador, con o sin
    // conexión, y no tardan esperando timeouts. La prueba que necesite
    // una respuesta externa la simula con Http::fake().
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/**
 * Contraseña de prueba que cumple la regla real (8+ caracteres, mayúscula,
 * minúscula y número). Los usuarios del factory usan "password".
 */
const CLAVE_VALIDA = 'Clave2026x';

/**
 * Crea un usuario y le asigna un rol en un contexto dado.
 * Se usa en (casi) todos los tests de Feature que necesitan
 * autenticarse como cliente/participante/adminDeportivo/adminSocial,
 * así que vive acá en vez de repetirse en cada archivo.
 *
 * Uso: $admin = userWithRole('adminDeportivo');
 */
function userWithRole(string $rol, string $contexto = 'general'): User
{
    $user = User::factory()->create();

    $role = Role::firstOrCreate(['nombre_rol' => $rol]);

    $user->roles()->attach($role->id_rol, ['contexto' => $contexto]);

    return $user;
}