<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambiente', function (Blueprint $table) {
            $table->id('id_a');

            $table->string('nombre_a', 100);
            $table->text('descripcion_a')->nullable();
            $table->integer('capacidad_a');
            $table->decimal('precio_referencia_a', 12, 2)->nullable();
            $table->string('imagen_principal_a', 255)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambiente');
    }
};