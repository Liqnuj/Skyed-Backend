<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Las copias dejan de guardarse dentro de la base de datos (si la base se daña,
 * se perderían con ella) y pasan a ser archivos privados. Esta tabla queda como
 * el registro de cada copia: quién la hizo, qué contiene y dónde está.
 *
 * Las filas del esquema anterior se borran: guardaban tablas completas en JSON,
 * incluidas las contraseñas cifradas de los usuarios, y no se pueden restaurar.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('copia_seguridad')->delete();

        Schema::table('copia_seguridad', function (Blueprint $table) {
            $table->dropColumn(['nombre_tabla_cs', 'datos_json_cs']);
        });

        Schema::table('copia_seguridad', function (Blueprint $table) {
            $table->string('alcance_cs', 20)->default('general');
            $table->json('conjuntos_cs')->nullable();
            $table->string('archivo_cs', 200)->nullable();
            $table->unsignedBigInteger('tamano_cs')->default(0);
            $table->unsignedInteger('registros_cs')->default(0);
            $table->string('checksum_cs', 64)->nullable();
            // manual | automatica | previa_restauracion
            $table->string('origen_cs', 25)->default('manual');
            $table->foreignId('id_u')->nullable()->constrained('usuario', 'id_u')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('copia_seguridad', function (Blueprint $table) {
            $table->dropForeign(['id_u']);
            $table->dropColumn(['alcance_cs', 'conjuntos_cs', 'archivo_cs', 'tamano_cs', 'registros_cs', 'checksum_cs', 'origen_cs', 'id_u']);
        });

        Schema::table('copia_seguridad', function (Blueprint $table) {
            $table->string('nombre_tabla_cs', 50)->nullable();
            $table->json('datos_json_cs')->nullable();
        });
    }
};
