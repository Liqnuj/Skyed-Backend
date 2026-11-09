<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            if (!Schema::hasColumn('usuario', 'acepta_terminos_u')) {
                $table->boolean('acepta_terminos_u')->default(false);
                $table->timestamp('terminos_aceptados_en')->nullable();
            }
            if (!Schema::hasColumn('usuario', 'acepta_privacidad_u')) {
                $table->boolean('acepta_privacidad_u')->default(false);
                $table->timestamp('privacidad_aceptada_en')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn([
                'acepta_terminos_u', 'terminos_aceptados_en',
                'acepta_privacidad_u', 'privacidad_aceptada_en',
            ]);
        });
    }
};