<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TipoEventoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        $tipos = [
            [
                'nombre_tipo_eves' => 'Boda',
                'descripcion_eves' => 'Celebración de matrimonio y recepción.',
                'modulo_tipo_eves' => 'social',
            ],
            [
                'nombre_tipo_eves' => 'Quinceañera',
                'descripcion_eves' => 'Fiesta de celebración de 15 años.',
                'modulo_tipo_eves' => 'social',
            ],
            [
                'nombre_tipo_eves' => 'Cumpleaños',
                'descripcion_eves' => 'Celebración general de aniversario de nacimiento.',
                'modulo_tipo_eves' => 'social',
            ],
            [
                'nombre_tipo_eves' => 'Corporativo',
                'descripcion_eves' => 'Evento empresarial, fiesta de fin de año o institucional.',
                'modulo_tipo_eves' => 'social',
            ],
            [
                'nombre_tipo_eves' => 'Baby shower',
                'descripcion_eves' => 'Celebración previa a la llegada de un bebé.',
                'modulo_tipo_eves' => 'social',
            ],
            [
                'nombre_tipo_eves' => 'Otro',
                'descripcion_eves' => 'Cualquier otro evento social no listado.',
                'modulo_tipo_eves' => 'social',
            ]
        ];

        // updateOrInsert: si el tipo ya existe (mismo nombre y módulo) solo se
        // actualiza, así correr "db:seed" varias veces no lo duplica.
        foreach ($tipos as $tipo) {
            DB::table('tipo_evento')->updateOrInsert(
                ['nombre_tipo_eves' => $tipo['nombre_tipo_eves'], 'modulo_tipo_eves' => $tipo['modulo_tipo_eves']],
                ['descripcion_eves' => $tipo['descripcion_eves'], 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}