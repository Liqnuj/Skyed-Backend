<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copia_seguridad', function (Blueprint $table) {
            $table->id('id_cs');

            $table->string('nombre_tabla_cs', 50)->nullable();
            $table->timestamp('fecha_cs')->useCurrent();
            $table->json('datos_json_cs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copia_seguridad');
    }
};