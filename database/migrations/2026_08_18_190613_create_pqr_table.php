<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pqr', function (Blueprint $table) {
            $table->id('id_pqr');

            $table->string('tipo_pqr', 20);
            $table->string('asunto_pqr', 150);
            $table->text('mensaje_pqr');
            $table->string('estado_pqr', 20)->default('abierto');
            $table->text('respuesta_pqr')->nullable();
            $table->timestamp('respondido_en_pqr')->nullable();
            $table->timestamp('creado_en_pqr')->useCurrent();

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pqr');
    }
};