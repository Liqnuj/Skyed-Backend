<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Precio del kit. Los kits que ya existen quedan en 0 hasta que se les ponga precio.
        if (!Schema::hasColumn('kit', 'precio_k')) {
            Schema::table('kit', function (Blueprint $table) {
                $table->decimal('precio_k', 10, 2)->default(0);
            });
        }

        // Kit del invitado: si lo quiere y de qué talla.
        if (!Schema::hasColumn('invitados', 'desea_kit_inv')) {
            Schema::table('invitados', function (Blueprint $table) {
                $table->boolean('desea_kit_inv')->default(false);
            });
        }
        if (!Schema::hasColumn('invitados', 'talla_kit_inv')) {
            Schema::table('invitados', function (Blueprint $table) {
                $table->string('talla_kit_inv', 10)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kit', 'precio_k')) {
            Schema::table('kit', fn (Blueprint $t) => $t->dropColumn('precio_k'));
        }
        foreach (['desea_kit_inv', 'talla_kit_inv'] as $col) {
            if (Schema::hasColumn('invitados', $col)) {
                Schema::table('invitados', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};