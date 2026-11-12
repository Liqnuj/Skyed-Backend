<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruta_evento', function (Blueprint $table) {
            $table->dropForeign(['id_e']);
            $table->unsignedBigInteger('id_e')->nullable()->change();
            $table->foreign('id_e')
                ->references('id_e')
                ->on('eventoDeportivo')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('ruta_evento')->whereNull('id_e')->exists()) {
            throw new RuntimeException('No se puede revertir: hay rutas sin evento asignado.');
        }

        Schema::table('ruta_evento', function (Blueprint $table) {
            $table->dropForeign(['id_e']);
            $table->unsignedBigInteger('id_e')->nullable(false)->change();
            $table->foreign('id_e')
                ->references('id_e')
                ->on('eventoDeportivo')
                ->cascadeOnDelete();
        });
    }
};