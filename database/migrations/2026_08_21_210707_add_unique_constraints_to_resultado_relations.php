
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_entrada', function (Blueprint $table) {
            $table->unique('id_i', 'qr_entrada_id_i_unique');
        });

        Schema::table('categoria_resultado', function (Blueprint $table) {
            $table->unique('id_r', 'categoria_resultado_id_r_unique');
        });

        Schema::table('premio', function (Blueprint $table) {
            $table->unique('id_r', 'premio_id_r_unique');
        });
    }

    public function down(): void
    {
        Schema::table('qr_entrada', function (Blueprint $table) {
            $table->dropUnique('qr_entrada_id_i_unique');
        });

        Schema::table('categoria_resultado', function (Blueprint $table) {
            $table->dropUnique('categoria_resultado_id_r_unique');
        });

        Schema::table('premio', function (Blueprint $table) {
            $table->dropUnique('premio_id_r_unique');
        });
    }
};
