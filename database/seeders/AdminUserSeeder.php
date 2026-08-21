<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea un usuario Administrador con acceso a ambos dominios
     * (deportivo y social) para poder probar las rutas protegidas
     * desde Postman. Cambia el correo/contraseña si lo vas a dejar
     * en un ambiente compartido.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['correo_u' => 'admin@skyed.test'],
            [
                'tipo_documento_u' => 'CC',
                'documento_u' => 100000000,
                'nombre_u' => 'Admin',
                'apellido_u' => 'SkyEd',
                'rh_u' => 'O+',
                'telefono_u' => '3000000000',
                'fecha_nacimiento_u' => '1990-01-01',
                'contrasena_u' => Hash::make('password123'),
                'estado_u' => 'activo',
            ]
        );

        $rolAdminDeportivo = Role::where('nombre_rol', 'adminDeportivo')->first();
        $rolAdminSocial = Role::where('nombre_rol', 'adminSocial')->first();

        if ($rolAdminDeportivo) {
            $admin->roles()->syncWithoutDetaching([
                $rolAdminDeportivo->id_rol => ['contexto' => 'deportivo'],
            ]);
        }

        if ($rolAdminSocial) {
            $admin->roles()->syncWithoutDetaching([
                $rolAdminSocial->id_rol => ['contexto' => 'social'],
            ]);
        }
    }
}
