<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pqr', 'reserva'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->boolean('oculta_usuario')->default(false);
                $t->boolean('oculta_admin')->default(false);
            });
        }
    }

    public function down(): void
    {
        foreach (['pqr', 'reserva'] as $tabla) {
            Schema::table($tabla, function (Blueprint $t) {
                $t->dropColumn(['oculta_usuario', 'oculta_admin']);
            });
        }
    }
};