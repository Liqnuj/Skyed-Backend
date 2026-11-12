<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pqr', 'contexto_pqr')) {
            Schema::table('pqr', function (Blueprint $table) {
                $table->string('contexto_pqr', 20)->default('social')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pqr', 'contexto_pqr')) {
            Schema::table('pqr', function (Blueprint $table) {
                $table->dropColumn('contexto_pqr');
            });
        }
    }
};