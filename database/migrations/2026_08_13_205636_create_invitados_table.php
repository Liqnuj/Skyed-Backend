<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitados', function (Blueprint $table) {
            $table->id('id_inv');

            $table->string('tipo_documento', 30);
            $table->integer('documento_inv')->unique();

            $table->string('nombre_inv', 50);
            $table->string('apellido_inv', 50);

            $table->string('rh_inv', 5);

            $table->string('telefono_inv', 50)->unique();

            $table->date('fecha_nacimiento_inv');

            $table->string('correo_inv', 80)->nullable()->unique();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitados');
    }
};