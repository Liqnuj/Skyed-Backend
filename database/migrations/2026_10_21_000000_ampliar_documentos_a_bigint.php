<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los números de documento eran "integer" (máximo 2.147.483.647 en
 * PostgreSQL). Una cédula de 10 dígitos como 3456789012 no cabía y el
 * registro o la inscripción de un invitado respondían 500. Las pruebas
 * no lo detectaban porque usan SQLite, que no tiene ese límite.
 * Cambiar el tipo conserva los datos y los índices únicos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->bigInteger('documento_u')->change();
        });

        Schema::table('invitados', function (Blueprint $table) {
            $table->bigInteger('documento_inv')->change();
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->integer('documento_u')->change();
        });

        Schema::table('invitados', function (Blueprint $table) {
            $table->integer('documento_inv')->change();
        });
    }
};
