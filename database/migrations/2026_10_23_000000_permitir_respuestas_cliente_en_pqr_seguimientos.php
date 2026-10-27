<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El cliente también escribe en el historial de una PQR:
 *  - esos registros no tienen administrador (id_admin queda vacío);
 *  - tipo_evento puede ser 'respuesta_cliente';
 *  - comentario_publico guarda mensajes completos (texto largo).
 *
 * No borra ni modifica datos existentes. Pensada para PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Solo PostgreSQL: en SQLite (pruebas automáticas) la tabla ya se crea
        // así y este SQL no existe.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE pqr_seguimientos ALTER COLUMN id_admin DROP NOT NULL');

        // Si tipo_evento se creó como enum, Laravel lo guarda con un CHECK
        // que no aceptaría 'respuesta_cliente'. La validación sigue en el controlador.
        DB::statement('ALTER TABLE pqr_seguimientos DROP CONSTRAINT IF EXISTS pqr_seguimientos_tipo_evento_check');

        DB::statement('ALTER TABLE pqr_seguimientos ALTER COLUMN comentario_publico TYPE TEXT');
    }

    public function down(): void
    {
        // Sin reversa: volver a exigir id_admin o acortar la columna podría fallar con datos reales.
    }
};