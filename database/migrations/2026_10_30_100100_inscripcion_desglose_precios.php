<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Desglose del precio pagado: queda guardado aunque después cambie el precio
 * del evento o del kit. precio_pagado_i sigue siendo el total.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE inscripcion ADD COLUMN IF NOT EXISTS precio_inscripcion_i numeric(10,2)');
        DB::statement('ALTER TABLE inscripcion ADD COLUMN IF NOT EXISTS precio_kit_i numeric(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE inscripcion ADD COLUMN IF NOT EXISTS precio_invitado_i numeric(10,2) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE inscripcion ADD COLUMN IF NOT EXISTS precio_kit_invitado_i numeric(10,2) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_kit_invitado_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_invitado_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_kit_i');
        DB::statement('ALTER TABLE inscripcion DROP COLUMN IF EXISTS precio_inscripcion_i');
    }
};