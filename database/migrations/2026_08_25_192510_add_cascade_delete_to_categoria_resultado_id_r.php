<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria_resultado', function (Blueprint $table) {
            $table->dropForeign(['id_r']);

            $table->foreign('id_r')
                ->references('id_r')
                ->on('resultado')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('categoria_resultado', function (Blueprint $table) {
            $table->dropForeign(['id_r']);

            $table->foreign('id_r')
                ->references('id_r')
                ->on('resultado');
        });
    }
};