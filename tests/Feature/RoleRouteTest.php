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

    $user->roles()->attach($rolAdminDeportivo->id_rol, ['contexto' => 'general']);

    // Agrega $this-> antes de actingAs
    $this->actingAs($user)
         ->getJson('/api/roles')
         ->assertStatus(200)
        ->assertJsonStructure([
            '*' => ['id_rol', 'nombre_rol'] 
        ]);
});