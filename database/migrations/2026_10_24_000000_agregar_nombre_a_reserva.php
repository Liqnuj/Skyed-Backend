<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre opcional que el cliente le pone a su reserva
 * (por ejemplo "Cumpleaños de Sofía"). No modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('reserva', 'nombre_rese')) {
            Schema::table('reserva', function (Blueprint $table) {
                $table->string('nombre_rese', 80)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reserva', 'nombre_rese')) {
            Schema::table('reserva', function (Blueprint $table) {
                $table->dropColumn('nombre_rese');
            });
        }
    }
};