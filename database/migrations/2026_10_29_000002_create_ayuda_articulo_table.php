<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ayuda_articulo', function (Blueprint $table) {
            $table->id('id_aya');

            // restrictOnDelete: borrar una categoría NO borra sus artículos.
            $table->foreignId('id_ayc')
                ->constrained('ayuda_categoria', 'id_ayc')
                ->restrictOnDelete();

            $table->string('titulo_aya', 150);
            $table->string('slug_aya', 160)->unique();
            $table->string('resumen_aya', 300)->nullable();
            $table->text('contenido_aya');
            $table->string('palabras_clave_aya', 255)->nullable(); // separadas por coma

            $table->string('modulo_aya', 15)->default('general');    // general | deportivo | social
            $table->string('audiencia_aya', 10)->default('publico'); // publico | usuario | admin
            $table->string('estado_aya', 12)->default('borrador');   // borrador | publicado | desactivado

            $table->boolean('destacado_aya')->default(false);
            $table->boolean('faq_aya')->default(false);
            $table->unsignedSmallInteger('orden_aya')->default(0);
            $table->string('contexto_aya', 60)->nullable(); // p. ej. social.reservar
            $table->unsignedInteger('visitas_aya')->default(0);

            $table->foreignId('id_autor')
                ->nullable()
                ->constrained('usuario', 'id_u')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['estado_aya', 'modulo_aya']);
            $table->index('contexto_aya');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ayuda_articulo');
    }
};