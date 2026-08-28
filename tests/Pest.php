<?php

use App\Models\Role;
use App\Models\User;

uses(
    Tests\TestCase::class,
        Illuminate\Foundation\Testing\RefreshDatabase::class,
)->in('Feature');

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