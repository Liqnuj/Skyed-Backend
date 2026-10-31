<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonio', function (Blueprint $table) {
            $table->id('id_tes');

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            // Una reseña por reserva, y solo de reservas completadas
            // (eso lo valida el controlador).
            $table->foreignId('id_rese')
                ->constrained('reserva', 'id_rese')
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('calificacion_tes');
            $table->string('comentario_tes', 500);

            // Se publica al instante. La columna queda para poder ocultar
            // una reseña abusiva más adelante sin borrarla.
            $table->string('estado_tes', 20)->default('publicado');

            $table->timestamps();

            $table->unique('id_rese');
            $table->index(['estado_tes', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonio');
    }
};
