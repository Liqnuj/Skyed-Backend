<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->decimal('monto_p', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->decimal('monto_p', 10, 7)->nullable()->change();
        });
    }
};