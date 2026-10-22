<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Crea usuarios administradores independientes para los dominios
     * deportivo y social.
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
                'rol' => 'adminDeportivo',
                'contexto' => 'deportivo',
            ],
            [
                'correo_u' => 'adminsocial@skyed.test',
                'documento_u' => 100000002,
                'nombre_u' => 'Admin Social',
                'apellido_u' => 'SkyEd',
                'telefono_u' => '3000000002',
                'rol' => 'adminSocial',
                'contexto' => 'social',
            ],
        ];

        foreach ($admins as $adminData) {
            $role = Role::where('nombre_rol', $adminData['rol'])->firstOrFail();
            $admin = User::firstOrNew(['correo_u' => $adminData['correo_u']]);

            if (!$admin->exists) {
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

            $admin->roles()->sync([
                $role->id_rol => ['contexto' => $adminData['contexto']],
            ]);
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
