<?php

it('la lista de usuarios del panel viene ordenada por ID', function () {
    $admin = userWithRole('adminSocial', 'social');
    foreach (range(1, 4) as $i) {
        userWithRole('cliente', 'social');
    }
    // Uno de los primeros se actualiza: en PostgreSQL eso lo movía al final de la lista.
    \App\Models\User::orderBy('id_u')->skip(1)->first()->update(['nombre_u' => 'Cambiado']);

    $ids = collect($this->actingAs($admin, 'sanctum')->getJson('/api/users')->assertOk()->json('data'))->pluck('id_u')->all();

    expect($ids)->toBe(collect($ids)->sort()->values()->all());
});

it('la lista de usuarios del panel trae la foto de perfil', function () {
    $admin = userWithRole('adminSocial', 'social');
    $conFoto = userWithRole('cliente', 'social');
    $conFoto->forceFill(['foto_u' => 'fotos_perfil/prueba.jpg'])->save();

    $usuarios = collect($this->actingAs($admin, 'sanctum')->getJson('/api/users')->assertOk()->json('data'))->keyBy('id_u');

    expect($usuarios[$conFoto->id_u]['foto_url'])->toEndWith('/storage/fotos_perfil/prueba.jpg')
        ->and($usuarios[$admin->id_u]['foto_url'])->toBeNull()
        ->and($usuarios[$conFoto->id_u])->not->toHaveKey('contrasena_u');
});
