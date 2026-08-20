<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultado', function (Blueprint $table) {
            $table->id('id_r');

            $table->time('tiempo_final_r');

            $table->integer('posicion_general_r')->nullable();

            $table->string('estado_r', 30);

            $table->foreignId('id_i')
                ->nullable()
                ->constrained('inscripcion', 'id_i');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultado');
    }
};