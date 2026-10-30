<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Datos opcionales que el cliente podrá indicar al reservar. Son solo
 * columnas nuevas y vacías: las reservas existentes no cambian.
 *  - hora_aprox_rese:    hora aproximada de inicio;
 *  - duracion_horas_rese: duración estimada en horas;
 *  - fecha_hasta_rese:   "fecha flexible hasta" (rango aproximado: la fecha
 *                        de fecha_evento_rese es el inicio del rango).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reserva', function (Blueprint $table) {
            if (! Schema::hasColumn('reserva', 'hora_aprox_rese')) {
                $table->time('hora_aprox_rese')->nullable();
            }

            if (! Schema::hasColumn('reserva', 'duracion_horas_rese')) {
                $table->unsignedSmallInteger('duracion_horas_rese')->nullable();
            }

            if (! Schema::hasColumn('reserva', 'fecha_hasta_rese')) {
                $table->date('fecha_hasta_rese')->nullable();
            }
        });
    }

    public function down(): void
    {
        $columnas = array_values(array_filter(
            ['hora_aprox_rese', 'duracion_horas_rese', 'fecha_hasta_rese'],
            fn ($c) => Schema::hasColumn('reserva', $c)
        ));

        if ($columnas) {
            Schema::table('reserva', function (Blueprint $table) use ($columnas) {
                $table->dropColumn($columnas);
            });
        }
    }
};