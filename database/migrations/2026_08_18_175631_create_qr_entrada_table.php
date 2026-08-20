<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_entrada', function (Blueprint $table) {
            $table->id('id_qr');

            $table->string('codigo_qr', 255)->unique()->nullable();
            $table->string('qr_imagen_qr', 255)->nullable();

            $table->timestamp('fecha_generacion_qr')->nullable();
            $table->timestamp('fecha_uso_qr')->nullable();

            $table->string('estado_qr', 20)->default('activo');

            $table->foreignId('id_i')
                ->constrained('inscripcion', 'id_i')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_entrada');
    }
};