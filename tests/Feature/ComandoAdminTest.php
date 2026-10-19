<?php

use App\Models\User;

it('vuelve administradora de los dos paneles a una cuenta existente', function () {
    $user = userWithRole('participante', 'deportivo');

    $this->artisan('skyed:admin', ['correo' => $user->correo_u])->assertExitCode(0);

    expect($user->hasRoleInContext('adminDeportivo', 'deportivo'))->toBeTrue();
    expect($user->hasRoleInContext('adminSocial', 'social'))->toBeTrue();
    // Conserva el rol que ya tenía.
    expect($user->hasRole('participante'))->toBeTrue();
});

it('con --solo asigna un solo panel y no duplica el rol', function () {
    $user = User::factory()->create();

    $this->artisan('skyed:admin', ['correo' => $user->correo_u, '--solo' => 'deportivo'])->assertExitCode(0);
    $this->artisan('skyed:admin', ['correo' => $user->correo_u, '--solo' => 'deportivo'])->assertExitCode(0);

    expect($user->roles()->where('nombre_rol', 'adminDeportivo')->count())->toBe(1);
    expect($user->hasRole('adminSocial'))->toBeFalse();
});

it('avisa si el correo no existe', function () {
    $this->artisan('skyed:admin', ['correo' => 'nadie@ejemplo.com'])->assertExitCode(1);
});
