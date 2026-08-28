<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 public function up()
    {
        Schema::table('usuario', function (Blueprint $table) {
            // Agregamos el campo para controlar el tiempo
            $table->timestamp('codigo_expira_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('usuario', function (Blueprint $table) {
            $table->dropColumn('codigo_expira_at');
        });
    }
    
};
