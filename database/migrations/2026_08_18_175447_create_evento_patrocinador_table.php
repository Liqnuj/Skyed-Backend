<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evento_patrocinador', function (Blueprint $table) {
            $table->id('id_ep');

            $table->foreignId('patrocinador_id_p')
                ->constrained('patrocinador', 'id_p')
                ->cascadeOnDelete();

            $table->foreignId('evento_id_e')
                ->constrained('eventoDeportivo', 'id_e')
                ->cascadeOnDelete();

            $table->string('detalle', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evento_patrocinador');
    }
};