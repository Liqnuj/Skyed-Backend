<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipo_evento', function (Blueprint $table) {
            $table->id('id_tipo_eves');

            $table->string('nombre_tipo_eves', 50);
            $table->string('descripcion_eves', 120)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tipo_evento');
    }
};