<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entrega_kit', function (Blueprint $table) {
            $table->id('id_ek');

            $table->timestamp('fecha_entrega_real_ek')->nullable();

            $table->string('persona_entrega_ek', 80)->nullable();

            $table->string('estado_ek', 20)->default('pendiente');

            $table->string('observaciones_ek', 255)->nullable();

            $table->foreignId('id_k')
                ->constrained('kit', 'id_k')
                ->cascadeOnDelete();

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->foreignId('id_e')
                ->constrained('eventoDeportivo', 'id_e')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_kit');
    }
};