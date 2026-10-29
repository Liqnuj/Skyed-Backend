<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayuda_categoria', function (Blueprint $table) {
            $table->id('id_ayc');
            $table->string('nombre_ayc', 100);
            $table->string('slug_ayc', 120)->unique();
            $table->text('descripcion_ayc')->nullable();
            $table->string('icono_ayc', 50)->default('ti-help-circle');
            $table->string('modulo_ayc', 15)->default('general'); // general | deportivo | social
            $table->unsignedSmallInteger('orden_ayc')->default(0);
            $table->boolean('activa_ayc')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayuda_categoria');
    }
};