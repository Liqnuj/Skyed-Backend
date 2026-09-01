<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usuario', function (Blueprint $table) {
            $table->id('id_u');

            $table->string('tipo_documento_u', 30);
            $table->integer('documento_u')->unique();

            $table->string('nombre_u', 50);
            $table->string('apellido_u', 50);

            $table->string('rh_u', 5)->nullable();

            $table->string('telefono_u', 50)->unique();

            $table->string('correo_u', 80)->unique();

            $table->date('fecha_nacimiento_u');

            $table->string('codigo', 10)->nullable();

            $table->string('contrasena_u', 255);

            $table->string('estado_u', 20)->default('activo');

            $table->foreignId('id_inv')
                ->nullable()
                ->constrained('invitados', 'id_inv')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usuario');
    }
};