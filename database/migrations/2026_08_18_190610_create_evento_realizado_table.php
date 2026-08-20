<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_realizado', function (Blueprint $table) {
            $table->id('id_er');

            $table->string('nombre_er', 150);
            $table->string('descripcion_er', 255)->nullable();
            $table->date('fecha_er')->nullable();

            $table->foreignId('id_tipo_eves')
                ->nullable()
                ->constrained('tipo_evento', 'id_tipo_eves');

            $table->foreignId('id_a')
                ->nullable()
                ->constrained('ambiente', 'id_a');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_realizado');
    }
};