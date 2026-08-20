<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario_contexto_rol', function (Blueprint $table) {
            $table->id('id_ucr');

            $table->foreignId('id_u')
                ->constrained('usuario', 'id_u')
                ->cascadeOnDelete();

            $table->foreignId('id_rol')
                ->constrained('roles', 'id_rol')
                ->cascadeOnDelete();

            $table->string('contexto', 20);

            $table->timestamps();

            $table->unique(['id_u', 'id_rol', 'contexto']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario_contexto_rol');
    }
};