<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Todo el sistema usa el módulo del tipo de evento en minúscula
 * ('social' / 'deportivo': validaciones y filtros), pero TipoEventoSeeder
 * los guardaba como 'Social', así que el panel de Social no encontraba
 * ningún tipo. Además, el seeder no revisaba si ya existían y cada
 * "php artisan db:seed" los duplicaba (Boda, Boda, ...).
 *
 * Esta migración:
 *  1. pasa el módulo a minúscula;
 *  2. une los duplicados (mismo nombre y módulo): los eventos y reservas
 *     que usaban una copia pasan al tipo que se conserva, y la copia se borra.
 * No se pierde ningún evento ni reserva.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tipo_evento')->update([
            'modulo_tipo_eves' => DB::raw('LOWER(modulo_tipo_eves)'),
        ]);

        $tipos = DB::table('tipo_evento')->orderBy('id_tipo_eves')->get();
        $conservado = [];

        foreach ($tipos as $tipo) {
            $clave = mb_strtolower(trim($tipo->nombre_tipo_eves)) . '|' . $tipo->modulo_tipo_eves;

            if (! isset($conservado[$clave])) {
                $conservado[$clave] = $tipo->id_tipo_eves;
                continue;
            }

            foreach (['evento_realizado', 'reserva'] as $tabla) {
                if (Schema::hasColumn($tabla, 'id_tipo_eves')) {
                    DB::table($tabla)
                        ->where('id_tipo_eves', $tipo->id_tipo_eves)
                        ->update(['id_tipo_eves' => $conservado[$clave]]);
                }
            }

            DB::table('tipo_evento')->where('id_tipo_eves', $tipo->id_tipo_eves)->delete();
        }
    }

    public function down(): void
    {
        // Sin reversa: los duplicados no se recrean.
    }
};
