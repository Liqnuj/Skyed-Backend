<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruta_evento', function (Blueprint $table) {
            $table->id('id_re');

            $table->string('nombre_re', 100);

            $table->string('estado_re', 30)->nullable();

            $table->string('distancia_re', 45)->nullable();

            $table->string('desnivel_re', 45)->nullable();

            $table->string('descripcion_re', 255)->nullable();

            $table->string('archivo_gpx_re', 255)->nullable();

            $table->decimal('precio_re', 12, 2)->nullable();

            $table->foreignId('id_e')
                ->constrained('eventoDeportivo', 'id_e')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ruta_evento');
    }
};