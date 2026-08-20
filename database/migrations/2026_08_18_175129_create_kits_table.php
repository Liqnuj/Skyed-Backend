<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kit', function (Blueprint $table) {
            $table->id('id_k');

            $table->string('nombre_k', 45);
            $table->integer('stock_k');

            $table->date('fecha_entrega_k')->nullable();
            $table->string('lugar_entrega_k', 45)->nullable();
            $table->string('contenido_k', 255)->nullable();
            $table->string('talla_camiseta_k', 10)->nullable();
            $table->integer('numero_dorsal_k')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit');
    }
};