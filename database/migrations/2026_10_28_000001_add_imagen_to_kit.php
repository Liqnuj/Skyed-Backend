<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kit', function (Blueprint $table) {
            if (!Schema::hasColumn('kit', 'imagen_k')) {
                $table->string('imagen_k', 255)->nullable()->after('precio_k');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kit', function (Blueprint $table) {
            if (Schema::hasColumn('kit', 'imagen_k')) {
                $table->dropColumn('imagen_k');
            }
        });
    }
};