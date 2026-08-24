<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evento_realizado', function (Blueprint $table) {
            $table->foreignId('id_u')
                ->nullable()
                ->after('id_a')
                ->constrained('usuario', 'id_u')
                ->nullOnDelete();

            $table->string('estado_er', 20)
                ->default('activo')
                ->after('id_u');
        });
    }

    public function down(): void
    {
        Schema::table('evento_realizado', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_u');
            $table->dropColumn('estado_er');
        });
    }
};
