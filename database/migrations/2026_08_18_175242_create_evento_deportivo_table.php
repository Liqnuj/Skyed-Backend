<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventoDeportivo', function (Blueprint $table) {
            $table->id('id_e');

            $table->string('nombre_e', 120);
            $table->string('categoria_e', 30)->nullable();

            $table->date('fecha_e');
            $table->time('hora_e');

            $table->string('ubicacion_e', 120);
            $table->string('descripcion_e', 255);
            $table->string('requisitos_e', 255);
            $table->string('imagen_e', 120);

            $table->integer('cupos_disponibles_e')->default(0);

            $table->string('estado_e', 30)->default('activo');

            $table->timestamp('creado_e')->useCurrent();

            $table->foreignId('id_k')
                ->nullable()
                ->constrained('kit', 'id_k')
                ->nullOnDelete();

            $table->foreignId('id_u')
                ->nullable()
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventoDeportivo');
    }
};