<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entrega_kit', function (Blueprint $table) {
            $table->unsignedBigInteger('id_i')->nullable()->after('id_e');
            $table->unsignedBigInteger('id_inv')->nullable()->after('id_i');

            $table->foreign('id_i')
                ->references('id_i')->on('inscripcion')
                ->cascadeOnDelete();

            $table->foreign('id_inv')
                ->references('id_inv')->on('invitados')
                ->cascadeOnDelete();
        });

        // Las entregas que ya existían se ligan a la inscripción activa
        // más reciente del mismo usuario y evento. Las que no tengan una
        // (inscripción cancelada) quedan con id_i nulo.
        DB::statement("
            UPDATE entrega_kit ek
            SET id_i = (
                SELECT i.id_i FROM inscripcion i
                WHERE i.id_u = ek.id_u
                  AND i.id_e = ek.id_e
                  AND i.estado_i <> 'cancelada'
                ORDER BY i.id_i DESC
                LIMIT 1
            )
            WHERE ek.id_i IS NULL
        ");

        // Un solo kit por persona en cada inscripción: el del titular
        // (id_inv nulo) y, como máximo, el del invitado.
        DB::statement("
            CREATE UNIQUE INDEX entrega_kit_inscripcion_destinatario_unique
            ON entrega_kit (id_i, COALESCE(id_inv, 0))
            WHERE id_i IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS entrega_kit_inscripcion_destinatario_unique');

        Schema::table('entrega_kit', function (Blueprint $table) {
            $table->dropForeign(['id_i']);
            $table->dropForeign(['id_inv']);
            $table->dropColumn(['id_i', 'id_inv']);
        });
    }
};