<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos del formulario de inscripción que antes se perdían: categoría elegida,
 * si pidió kit, condiciones médicas (dato sensible) y teléfono de contacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->agregarColumna('id_cc', 'bigint REFERENCES categoria_competencia(id_cc) ON DELETE SET NULL');
        $this->agregarColumna('desea_kit_i', 'boolean NOT NULL DEFAULT false');
        $this->agregarColumna('condiciones_medicas_i', 'text');
        $this->agregarColumna('telefono_contacto_i', 'varchar(15)');

        // Las inscripciones anteriores que tenían talla de camiseta sí pidieron kit.
        DB::statement('UPDATE inscripcion SET desea_kit_i = true WHERE talla_camiseta_i IS NOT NULL AND desea_kit_i = false');
    }

    private function agregarColumna(string $columna, string $definicion): void
    {
        if (!Schema::hasColumn('inscripcion', $columna)) {
            DB::statement("ALTER TABLE inscripcion ADD COLUMN {$columna} {$definicion}");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS telefono_contacto_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS condiciones_medicas_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS desea_kit_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS id_cc');
    }
};