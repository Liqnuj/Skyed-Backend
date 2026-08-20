<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seguimiento_reserva', function (Blueprint $table) {
            $table->id('id_seguimiento');

            $table->foreignId('id_rese')
                ->constrained('reserva', 'id_rese')
                ->cascadeOnDelete();

            $table->timestamp('fecha_actualizacion')->useCurrent();
            $table->text('comentario')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_reserva');
    }
};