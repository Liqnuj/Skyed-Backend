<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_competencia', function (Blueprint $table) {
            $table->id('id_cc');

            $table->string('nombre_cc', 50);
            $table->integer('edad_minima_cc')->nullable();
            $table->integer('edad_maxima_cc')->nullable();
            $table->string('genero_cc', 20)->nullable();
            $table->string('distancia_cc', 45)->nullable();
            $table->string('descripcion_cc', 255)->nullable();

            $table->foreignId('id_e')
                ->constrained('eventoDeportivo', 'id_e')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_competencia');
    }
};