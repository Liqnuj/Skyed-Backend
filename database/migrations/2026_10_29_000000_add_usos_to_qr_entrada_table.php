<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_entrada', function (Blueprint $table) {
            // Cuántas personas pueden entrar con este QR: 1 normal, 2 si hay invitado.
            $table->unsignedSmallInteger('usos_permitidos_qr')->default(1);
            // Cuántos escaneos válidos lleva.
            $table->unsignedSmallInteger('usos_realizados_qr')->default(0);
        });

        // Las inscripciones que ya tienen invitado admiten 2 usos.
        DB::statement('
            UPDATE qr_entrada q
            SET usos_permitidos_qr = 2
            FROM inscripcion i
            WHERE i.id_i = q.id_i
              AND i.id_inv IS NOT NULL
        ');

        // Los QR que ya registraron una entrada cuentan como 1 uso realizado.
        // No se toca estado_qr: los usados siguen usados.
        DB::statement('
            UPDATE qr_entrada
            SET usos_realizados_qr = 1
            WHERE fecha_uso_qr IS NOT NULL
        ');

        // Nunca puede haber más usos realizados que permitidos.
        DB::statement('
            ALTER TABLE qr_entrada
            ADD CONSTRAINT qr_entrada_usos_validos
            CHECK (usos_realizados_qr <= usos_permitidos_qr)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE qr_entrada DROP CONSTRAINT IF EXISTS qr_entrada_usos_validos');

        Schema::table('qr_entrada', function (Blueprint $table) {
            $table->dropColumn(['usos_permitidos_qr', 'usos_realizados_qr']);
        });
    }
};