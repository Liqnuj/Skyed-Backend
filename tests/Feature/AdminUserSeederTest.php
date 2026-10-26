<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;

it('creates a super admin with access to every panel', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $superAdmin = User::where('correo_u', 'superadmin@skyed.test')->firstOrFail();

    expect($superAdmin->hasRoleInContext('adminDeportivo', 'deportivo'))->toBeTrue();
    expect($superAdmin->hasRoleInContext('adminSocial', 'social'))->toBeTrue();
    expect($superAdmin->hasRoleInContext('participante', 'deportivo'))->toBeTrue();
    expect($superAdmin->hasRoleInContext('cliente', 'social'))->toBeTrue();

    $this->seed(AdminUserSeeder::class);

    expect($superAdmin->roles()->count())->toBe(4);
});
