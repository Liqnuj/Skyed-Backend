<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historial_contrasena', function (Blueprint $table) {
            $table->id('id_hc');
            $table->unsignedBigInteger('id_u');
            $table->string('contrasena_hash'); 
            $table->timestamps();

            // Relación con la tabla de usuarios
            $table->foreign('id_u')->references('id_u')->on('usuario')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historial_contrasena');
    }
};