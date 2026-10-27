<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * El commit "rol y reserva" (22-oct) editó migraciones que ya se habían
 * ejecutado: renombró 'roles' a 'rol' y agregó 'id_tipo_eves' (y volvió
 * opcional 'id_er') en 'reserva'. Las bases creadas antes de ese cambio
 * no los reciben, porque Laravel no repite migraciones ya corridas, y el
 * login y los seeders fallan con "no existe la relación «rol»".
 *
 * Esta migración pone al día esas bases. En una base nueva (creada con
 * las migraciones ya editadas) no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! Schema::hasTable('rol')) {
            Schema::rename('roles', 'rol');
        }

        if (! Schema::hasColumn('reserva', 'id_tipo_eves')) {
            Schema::table('reserva', function (Blueprint $table) {
                // Opcional: las reservas que ya existen no tienen tipo.
                $table->foreignId('id_tipo_eves')
                    ->nullable()
                    ->constrained('tipo_evento', 'id_tipo_eves');
            });
        }

        $idEr = collect(Schema::getColumns('reserva'))->firstWhere('name', 'id_er');
        if ($idEr && ! $idEr['nullable']) {
            Schema::table('reserva', function (Blueprint $table) {
                $table->unsignedBigInteger('id_er')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Sin reversa: deja la base igual a como la crean las migraciones actuales.
    }
};
