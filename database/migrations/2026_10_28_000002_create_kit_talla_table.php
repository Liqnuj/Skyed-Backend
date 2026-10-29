<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kit_talla')) {
            return;
        }

        Schema::create('kit_talla', function (Blueprint $table) {
            $table->bigIncrements('id_kt');
            // Sin llave foránea a propósito: así no choca con el tipo de id_k en tu tabla kit.
            // KitController borra las tallas cuando se elimina el kit.
            $table->unsignedBigInteger('id_k')->index();
            $table->string('talla_kt', 10);
            $table->unsignedInteger('cantidad_kt')->default(0);
            $table->timestamps();

            $table->unique(['id_k', 'talla_kt']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kit_talla');
    }
};