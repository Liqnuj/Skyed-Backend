<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscripcion', function (Blueprint $table) {
            $table->id('id_i');

            $table->integer('cupo_i');

            $table->string('estado_i', 20)->default('pendiente');

            $table->timestamp('fecha_i')->useCurrent();

            $table->decimal('precio_pagado_i', 10, 2)->nullable();

            $table->string('contacto_emergencia_nombre', 100);
            $table->string('contacto_emergencia_telefono', 15);
            $table->string('contacto_emergencia_parentesco', 50);

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
        Schema::dropIfExists('inscripcion');
    }
};