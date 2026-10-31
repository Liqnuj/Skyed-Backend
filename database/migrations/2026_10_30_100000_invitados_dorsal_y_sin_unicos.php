<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Dorsal propio del invitado.
 * - Quita los únicos de documento/teléfono/correo: la misma persona puede
 *   ser invitada en eventos distintos (cada inscripción tiene su propia fila).
 *
 * Es idempotente: se puede correr aunque los cambios ya se hayan hecho a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->agregarColumna('numero_dorsal_inv', 'integer');

        foreach (['documento_inv', 'telefono_inv', 'correo_inv'] as $col) {
            $nombre = "invitados_{$col}_unique";
            if (DB::getDriverName() !== 'sqlite') {
                DB::statement("ALTER TABLE invitados DROP CONSTRAINT IF EXISTS {$nombre}");
            }
            DB::statement("DROP INDEX IF EXISTS {$nombre}");
        }

        DB::statement('CREATE INDEX IF NOT EXISTS invitados_documento_inv_idx ON invitados (documento_inv)');
    }

    private function agregarColumna(string $columna, string $definicion): void
    {
        if (!Schema::hasColumn('invitados', $columna)) {
            DB::statement("ALTER TABLE invitados ADD COLUMN {$columna} {$definicion}");
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invitados_documento_inv_idx');
        DB::statement('ALTER TABLE invitados DROP COLUMN IF EXISTS numero_dorsal_inv');
        // Los únicos no se restauran: ya puede haber invitados repetidos.
    }
};