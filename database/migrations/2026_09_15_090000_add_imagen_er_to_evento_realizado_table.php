<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('evento_realizado', 'imagen_er')) {
            Schema::table('evento_realizado', function (Blueprint $table) {
                $table->string('imagen_er', 255)->nullable()->after('fecha_er');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('evento_realizado', 'imagen_er')) {
            Schema::table('evento_realizado', function (Blueprint $table) {
                $table->dropColumn('imagen_er');
            });
        }
    }
};
