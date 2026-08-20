<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago', function (Blueprint $table) {
            $table->id('id_pago');

            $table->string('metodo_pago_p', 50)->nullable();
            $table->string('referencia_p', 100)->nullable();
            $table->string('comprobante_p', 255)->nullable();

            $table->decimal('monto_p', 10, 7)->nullable();

            $table->timestamp('fecha_p')->useCurrent();

            $table->string('estado_p', 20)->default('pendiente');

            $table->foreignId('id_i')
                ->constrained('inscripcion', 'id_i')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago');
    }
};