<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->string('titulo');

            $table->text('mensaje');

            $table->string('tipo')->default('general');

            $table->boolean('leida')->default(false);

            $table->timestamp('leida_en')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};