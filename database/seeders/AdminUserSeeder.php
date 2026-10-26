<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea administradores por dominio y un super admin con acceso a
     * ambos dominios y los paneles de cliente y participante.
     */
    public function run(): void
    {
        $admins = [
            [
                'correo_u' => 'admindeportivo@skyed.test',
                'documento_u' => 100000001,
                'nombre_u' => 'Admin Deportivo',
                'apellido_u' => 'SkyEd',
                'telefono_u' => '3000000001',
                'roles' => [
                    'adminDeportivo' => 'deportivo',
                ],
            ],
            [
                'correo_u' => 'adminsocial@skyed.test',
                'documento_u' => 100000002,
                'nombre_u' => 'Admin Social',
                'apellido_u' => 'SkyEd',
                'telefono_u' => '3000000002',
                'roles' => [
                    'adminSocial' => 'social',
                ],
            ],
            [
                'correo_u' => 'superadmin@skyed.test',
                'documento_u' => 100000003,
                'nombre_u' => 'Super Admin',
                'apellido_u' => 'SkyEd',
                'telefono_u' => '3000000003',
                'roles' => [
                    'adminDeportivo' => 'deportivo',
                    'adminSocial' => 'social',
                    'participante' => 'deportivo',
                    'cliente' => 'social',
                ],
            ],
        ];

        foreach ($admins as $adminData) {
            $admin = User::firstOrNew(['correo_u' => $adminData['correo_u']]);

            if (! $admin->exists) {
                $admin->fill([
                    'tipo_documento_u' => 'CC',
                    'documento_u' => $this->availableDocument($adminData['documento_u']),
                    'nombre_u' => $adminData['nombre_u'],
                    'apellido_u' => $adminData['apellido_u'],
                    'rh_u' => 'O+',
                    'telefono_u' => $this->availablePhone($adminData['telefono_u']),
                    'fecha_nacimiento_u' => '1990-01-01',
                    'estado_u' => 'activo',
                ]);
            }

            $admin->contrasena_u = Hash::make('password123');
            $admin->estado_u = 'activo';
            $admin->save();

            $roles = [];
            foreach ($adminData['roles'] as $roleName => $context) {
                $role = Role::where('nombre_rol', $roleName)->firstOrFail();
                $roles[$role->id_rol] = ['contexto' => $context];
            }

            $admin->roles()->sync($roles);
        }
    }

    private function availablePhone(string $phone): string
    {
        while (User::where('telefono_u', $phone)->exists()) {
            $phone = (string) ((int) $phone + 1);
        }

        return $phone;
    }

    private function availableDocument(int $document): int
    {
        while (User::where('documento_u', $document)->exists()) {
            $document++;
        }

        return $document;
    }
}
