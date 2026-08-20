<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patrocinador', function (Blueprint $table) {
            $table->id('id_p');

            $table->string('nombre_p', 100);
            $table->string('logo_p', 255)->nullable();
            $table->string('telefono_p', 15)->nullable();
            $table->string('correo_p', 80)->nullable();
            $table->string('pagina_web_p', 120)->nullable();
            $table->string('aporte_p', 100)->nullable();
            $table->string('estado_p', 30)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patrocinador');
    }
};