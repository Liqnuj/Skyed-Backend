<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->foreignId('id_inv')
                ->nullable()
                ->after('id_e')
                ->constrained('invitados', 'id_inv')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_inv');
        });
    }
};
