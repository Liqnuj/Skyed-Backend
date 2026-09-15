<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tipo_evento es una tabla compartida, pero hoy solo la usa
     * evento_realizado (Social) — EventoDeportivo tiene su propio
     * campo libre categoria_e. Aun así, admins han creado ahí
     * clasificaciones que en realidad son de Deportivo (ej.
     * "Ciclomontañismo"), y nada impedía que el admin Social las
     * usara para un evento social por error.
     *
     * Se agrega 'modulo_tipo_eves' para poder filtrar el selector
     * del panel Social a solo clasificaciones de 'social', y para
     * validar en el backend que un evento social no pueda quedar
     * asociado a un tipo de 'deportivo'.
     */
    public function up(): void
    {
        Schema::table('tipo_evento', function (Blueprint $table) {
            $table->string('modulo_tipo_eves', 20)
                ->default('social')
                ->after('descripcion_eves');
        });
    }

    public function down(): void
    {
        Schema::table('tipo_evento', function (Blueprint $table) {
            $table->dropColumn('modulo_tipo_eves');
        });
    }
};
