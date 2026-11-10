<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Segundo código QR de la inscripción: el del retiro del kit.
 *
 * El QR de entrada (codigo_qr) es solo para la asistencia del día del evento.
 * Quien pidió kit (el titular, su invitado o ambos) recibe además este código
 * ("KIT-…"); al escanearlo, el staff ve las tallas y marca los kits como
 * entregados. Son códigos distintos para que uno no sirva por el otro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_entrada', function (Blueprint $table) {
            $table->string('codigo_kit_qr', 64)->nullable()->unique();
        });

        // Inscripciones que ya existen y pidieron kit (titular o invitado) en un
        // evento que tiene kit: se les genera su código.
        $ids = DB::table('qr_entrada as q')
            ->join('inscripcion as i', 'i.id_i', '=', 'q.id_i')
            ->join('eventoDeportivo as e', 'e.id_e', '=', 'i.id_e')
            ->leftJoin('invitados as inv', 'inv.id_inv', '=', 'i.id_inv')
            ->whereNotNull('e.id_k')
            ->whereNull('q.codigo_kit_qr')
            ->where(function ($w) {
                $w->where('i.desea_kit_i', true)->orWhere('inv.desea_kit_inv', true);
            })
            ->pluck('q.id_qr');

        foreach ($ids as $id) {
            DB::table('qr_entrada')->where('id_qr', $id)->update(['codigo_kit_qr' => 'KIT-' . Str::uuid()]);
        }
    }

    public function down(): void
    {
        Schema::table('qr_entrada', function (Blueprint $table) {
            $table->dropUnique(['codigo_kit_qr']);
            $table->dropColumn('codigo_kit_qr');
        });
    }
};
