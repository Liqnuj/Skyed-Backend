<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premio', function (Blueprint $table) {
            $table->id('id_premio');

            $table->string('nombre_premio', 100);
            $table->string('descripcion_premio', 255)->nullable();
            $table->integer('posicion_premio');
            $table->decimal('valor_premio', 12, 2)->nullable();

            $table->foreignId('id_r')
                ->constrained('resultado', 'id_r')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premio');
    }
};