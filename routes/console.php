<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| php artisan skyed:admin correo@ejemplo.com [--solo=deportivo|social]
|
| Da rol de administrador a una cuenta que ya existe, para poder entrar a
| /deportivo/admin y /social/admin sin tocar la base de datos a mano.
| Sin --solo asigna los dos paneles.
*/
Artisan::command('skyed:admin {correo} {--solo= : deportivo o social}', function (string $correo) {
    $solo = $this->option('solo');

    if ($solo !== null && !in_array($solo, ['deportivo', 'social'], true)) {
        $this->error('--solo debe ser "deportivo" o "social".');
        return 1;
    }

    $user = \App\Models\User::where('correo_u', $correo)->first();

    if (!$user) {
        $this->error("No existe una cuenta con el correo {$correo}. Regístrate primero en la página.");
        return 1;
    }

    $paneles = [
        'deportivo' => 'adminDeportivo',
        'social' => 'adminSocial',
    ];

    foreach ($paneles as $contexto => $nombreRol) {
        if ($solo !== null && $solo !== $contexto) {
            continue;
        }

        $rol = \App\Models\Role::firstOrCreate(['nombre_rol' => $nombreRol]);

        $yaLoTiene = $user->roles()
            ->where('nombre_rol', $nombreRol)
            ->wherePivot('contexto', $contexto)
            ->exists();

        if ($yaLoTiene) {
            $this->line("· {$correo} ya era {$nombreRol}.");
            continue;
        }

        $user->roles()->attach($rol->id_rol, ['contexto' => $contexto]);
        $this->info("✓ {$correo} ahora es {$nombreRol} (panel /{$contexto}/admin).");
    }

    $this->line('Cierra sesión y vuelve a entrar en la página para que se aplique.');

    return 0;
})->purpose('Da rol de administrador (deportivo y/o social) a una cuenta existente');

// Copia automática de todo. Se programa abajo; también se puede correr a mano.
Artisan::command('skyed:copia-seguridad {--conservar=14 : Cuántas copias automáticas conservar}', function () {
    $conjuntos = array_keys(\App\Support\CatalogoCopias::conjuntos());
    $copia = \App\Support\GestorCopias::crear($conjuntos, null, 'automatica');
    $borradas = \App\Support\GestorCopias::podarAutomaticas(max(1, (int) $this->option('conservar')));

    $this->info("✓ Copia creada: {$copia->archivo_cs} ({$copia->registros_cs} registros).");
    if ($borradas) {
        $this->line("· Se borraron {$borradas} copias automáticas antiguas.");
    }

    return 0;
})->purpose('Crea una copia de seguridad de todos los datos');

// Necesita que el servidor corra "php artisan schedule:run" cada minuto.
\Illuminate\Support\Facades\Schedule::command('skyed:copia-seguridad')->dailyAt('02:00')->timezone(\App\Support\GestorCopias::ZONA_HORARIA);
