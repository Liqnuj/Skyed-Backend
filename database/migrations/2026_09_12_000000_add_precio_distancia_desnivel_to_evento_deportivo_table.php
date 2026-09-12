<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventoDeportivo', function (Blueprint $table) {
            $table->decimal('precio_e', 12, 2)->default(0)->after('categoria_e');
            $table->string('distancia_e', 30)->nullable()->after('precio_e');
            $table->string('desnivel_e', 30)->nullable()->after('distancia_e');
        });
    }

    public function down(): void
    {
        Schema::table('eventoDeportivo', function (Blueprint $table) {
            $table->dropColumn(['precio_e', 'distancia_e', 'desnivel_e']);
        });
    }
};