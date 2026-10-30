<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Desglose del precio pagado: queda guardado aunque después cambie el precio
 * del evento o del kit. precio_pagado_i sigue siendo el total.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->agregarColumna('precio_inscripcion_i', 'numeric(10,2)');
        $this->agregarColumna('precio_kit_i', 'numeric(10,2) NOT NULL DEFAULT 0');
        $this->agregarColumna('precio_invitado_i', 'numeric(10,2) NOT NULL DEFAULT 0');
        $this->agregarColumna('precio_kit_invitado_i', 'numeric(10,2) NOT NULL DEFAULT 0');
    }

    private function agregarColumna(string $columna, string $definicion): void
    {
        if (!Schema::hasColumn('inscripcion', $columna)) {
            DB::statement("ALTER TABLE inscripcion ADD COLUMN {$columna} {$definicion}");
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_kit_invitado_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_invitado_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_kit_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_inscripcion_i');
    }
};