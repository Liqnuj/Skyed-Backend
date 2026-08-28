<?php

use App\Models\Servicio;
use function Pest\Laravel\getJson;

it('requiere autenticación para listar servicios', function () {
    getJson('/api/servicios')->assertStatus(401);
});

it('lista servicios paginados a un usuario autenticado', function () {
    Servicio::create(['nombre_s' => 'Catering', 'descripcion_s' => 'Servicio de banquetes']);
    $user = userWithRole('cliente');

    $this->actingAs($user)
        ->getJson('/api/servicios')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['*' => ['id', 'nombre', 'descripcion']],
        ]);
});

it('no permite crear un servicio a un usuario sin el rol adminSocial', function () {
    $user = userWithRole('cliente');

    $this->actingAs($user)
        ->postJson('/api/servicios', ['nombre_s' => 'DJ'])
        ->assertStatus(403);
});

it('permite a un adminSocial crear un servicio', function () {
    $admin = userWithRole('adminSocial');

    $this->actingAs($admin)
        ->postJson('/api/servicios', [
            'nombre_s' => 'Decoración',
            'descripcion_s' => 'Ambientación de salones',
        ])
        ->assertStatus(201)
        ->assertJsonPath('servicio.nombre', 'Decoración');
});

it('rechaza crear un servicio sin nombre', function () {
    $admin = userWithRole('adminSocial');

    $this->actingAs($admin)
        ->postJson('/api/servicios', ['descripcion_s' => 'Sin nombre'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('nombre_s');
});

it('permite a un adminSocial eliminar un servicio', function () {
    $admin = userWithRole('adminSocial');
    $servicio = Servicio::create(['nombre_s' => 'Sonido']);

    $this->actingAs($admin)
        ->deleteJson("/api/servicios/{$servicio->id_s}")
        ->assertStatus(200);

    expect(Servicio::find($servicio->id_s))->toBeNull();
});
