<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Roles base del sistema. 'contexto' se maneja aparte (por usuario,
     * en la tabla pivote usuario_contexto_rol), acá solo se crean los
     * nombres de rol que existen en el catálogo.
     */
    public function run(): void
    {
        $roles = [
            'cliente',
            'participante',
            'adminDeportivo',
            'adminSocial',
        ];

        foreach ($roles as $nombre) {
            Role::firstOrCreate(['nombre_rol' => $nombre]);
        }
    }
}
