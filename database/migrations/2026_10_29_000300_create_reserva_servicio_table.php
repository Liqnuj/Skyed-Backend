<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Servicios de una reserva (los que pide el cliente y los que agrega el
 * administrador). Puede apuntar al catálogo 'servicio' o ser un texto libre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reserva_servicio')) {
            return;
        }

        Schema::create('reserva_servicio', function (Blueprint $table) {
            $table->id('id_rs');

            $table->foreignId('id_rese')
                ->constrained('reserva', 'id_rese')
                ->cascadeOnDelete();

            // Si viene del catálogo; si el servicio se borra, el registro se conserva.
            $table->foreignId('id_s')
                ->nullable()
                ->constrained('servicio', 'id_s')
                ->nullOnDelete();

            // Siempre se guarda el nombre (copia del catálogo o texto libre).
            $table->string('nombre_servicio', 100);
            $table->unsignedInteger('cantidad')->default(1);

            // 'cliente' = lo pidió el usuario; 'admin' = lo agregó el administrador.
            $table->string('origen', 10)->default('admin');
            // pendiente | confirmado | no_disponible
            $table->string('estado', 20)->default('pendiente');

            $table->foreignId('id_responsable')
                ->nullable()
                ->constrained('usuario', 'id_u')
                ->nullOnDelete();

            $table->string('observaciones', 255)->nullable();

            $table->timestamps();

            $table->index('id_rese');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_servicio');
    }
};