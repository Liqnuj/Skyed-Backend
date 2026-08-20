<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_resultado', function (Blueprint $table) {
            $table->id('id_categoria_resultado');

            $table->integer('posicion_categoria');

            $table->string('estado_competidor', 30)
                ->default('clasificado');

            $table->foreignId('id_cc')
                ->nullable()
                ->constrained('categoria_competencia', 'id_cc');

            $table->foreignId('id_r')
                ->nullable()
                ->constrained('resultado', 'id_r');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_resultado');
    }
};  