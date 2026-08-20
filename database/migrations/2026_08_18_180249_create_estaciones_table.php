<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estacion', function (Blueprint $table) {
            $table->id('id_est');

            $table->string('nombre_est', 100)->nullable();

            $table->string('tipo_est', 30)->default('general');

            $table->string('kilometro_est', 20)->nullable();

            $table->decimal('latitud_est', 10, 7)->nullable();
            $table->decimal('longitud_est', 10, 7)->nullable();

            $table->string('descripcion_pest', 255)->nullable();

            $table->string('estado_est', 20)->nullable();

            $table->foreignId('id_e')
                ->constrained('eventoDeportivo', 'id_e')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estacion');
    }
};