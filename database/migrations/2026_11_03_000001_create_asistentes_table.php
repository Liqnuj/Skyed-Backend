<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistente', function (Blueprint $table) {
            $table->id('id_asistente');
            $table->foreignId('id_admin')->constrained('usuario', 'id_u')->cascadeOnDelete();
            $table->foreignId('id_usuario')->constrained('usuario', 'id_u')->cascadeOnDelete();
            $table->string('modulo', 20);
            $table->json('permisos');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['id_admin', 'id_usuario', 'modulo']);
            $table->index(['id_admin', 'modulo', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistente');
    }
};
