<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambiente_servicio', function (Blueprint $table) {
            $table->foreignId('id_a')
                ->constrained('ambiente', 'id_a')
                ->cascadeOnDelete();

            $table->foreignId('id_s')
                ->constrained('servicio', 'id_s')
                ->cascadeOnDelete();

            $table->primary(['id_a', 'id_s']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambiente_servicio');
    }
};