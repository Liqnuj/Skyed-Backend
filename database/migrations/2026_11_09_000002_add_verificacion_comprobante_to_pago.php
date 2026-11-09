<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Datos de la revisión automática del comprobante de pago:
 *  - hash_comprobante_p: huella (SHA-256) de la imagen original, para no
 *    aceptar el mismo comprobante en dos inscripciones.
 *  - referencia_transaccion_p: número de la transacción en Nequi o el banco
 *    (leído de la imagen), con el mismo fin.
 *  - verificacion_comprobante_p: lo que se revisó (QR, destino, monto) para
 *    mostrárselo al admin cuando valida el pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->string('hash_comprobante_p', 64)->nullable()->index();
            $table->string('referencia_transaccion_p', 40)->nullable()->index();
            $table->json('verificacion_comprobante_p')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pago', function (Blueprint $table) {
            $table->dropIndex(['hash_comprobante_p']);
            $table->dropIndex(['referencia_transaccion_p']);
            $table->dropColumn(['hash_comprobante_p', 'referencia_transaccion_p', 'verificacion_comprobante_p']);
        });
    }
};
