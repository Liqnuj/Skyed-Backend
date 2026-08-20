<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_participacion', function (Blueprint $table) {
            $table->id('id_hp');

            $table->timestamp('fecha_hp')->nullable();

            $table->string('estado_hp', 20)->nullable();

            $table->string('observaciones_hp', 255)->nullable();

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
        Schema::dropIfExists('historial_participacion');
    }
};