<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reserva', function (Blueprint $table) {
            $table->id('id_rese');

            $table->date('fecha_evento_rese');
            $table->integer('invitados_rese');
            $table->decimal('presupuesto_rese', 12, 2);
            $table->string('ubicacion_rese', 120);
            $table->string('Observaciones_rese', 255);
            $table->decimal('total_rese', 12, 2)->default(0);
            $table->string('estado_rese', 20)->default('pendiente');
            $table->timestamp('creado_en_rese')->useCurrent();

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->foreignId('id_er')
                ->constrained('evento_realizado', 'id_er')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva');
    }
};