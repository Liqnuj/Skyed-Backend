<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayuda_valoracion', function (Blueprint $table) {
            $table->id('id_ayv');

            $table->foreignId('id_aya')
                ->constrained('ayuda_articulo', 'id_aya')
                ->cascadeOnDelete();

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->boolean('util_ayv');
            $table->string('comentario_ayv', 500)->nullable();
            $table->timestamps();

            // Un voto por usuario y artículo.
            $table->unique(['id_aya', 'id_u']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayuda_valoracion');
    }
};