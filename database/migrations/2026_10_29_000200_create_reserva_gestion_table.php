<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Información administrativa de una reserva (solo la ve y edita el
 * Administrador Social). Es 1 a 1 con 'reserva': los datos que dio el
 * cliente quedan en 'reserva' sin tocarse.
 *
 * Lo que ya existe en otras tablas NO se repite aquí:
 *  - el estado (pendiente/confirmada/cancelada/completada) sigue en reserva.estado_rese;
 *  - el historial y las notas internas van en seguimiento_reserva;
 *  - los servicios van en reserva_servicio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reserva_gestion')) {
            return;
        }

        Schema::create('reserva_gestion', function (Blueprint $table) {
            $table->id('id_gestion');

            $table->foreignId('id_rese')
                ->unique()
                ->constrained('reserva', 'id_rese')
                ->cascadeOnDelete();

            // Etapa de planificación (ver ReservaGestion::ETAPAS).
            $table->string('etapa_gestion', 30)->default('nueva');

            $table->foreignId('id_responsable')
                ->nullable()
                ->constrained('usuario', 'id_u')
                ->nullOnDelete();

            // Fechas: la solicitada por el cliente vive en reserva.fecha_evento_rese.
            $table->date('fecha_propuesta')->nullable();
            $table->date('fecha_confirmada')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->date('fecha_limite_preparacion')->nullable();

            // Evento
            $table->foreignId('id_a')
                ->nullable()
                ->constrained('ambiente', 'id_a')
                ->nullOnDelete();
            $table->unsignedInteger('asistentes_final')->nullable();
            $table->text('distribucion_espacio')->nullable();
            $table->text('necesidades_especiales')->nullable();
            $table->text('requerimientos_adicionales')->nullable();

            // Bloques flexibles (JSON): decoración, logística y los campos
            // que dependen del tipo de evento.
            $table->json('decoracion')->nullable();
            $table->json('logistica')->nullable();
            $table->json('datos_tipo')->nullable();

            // Contacto con el usuario (el detalle de cada contacto va al historial).
            $table->boolean('contactado')->default(false);
            $table->timestamp('ultimo_contacto_en')->nullable();
            $table->string('medio_ultimo_contacto', 20)->nullable();
            $table->timestamp('proximo_contacto_en')->nullable();
            $table->text('compromisos')->nullable();

            $table->timestamps();

            $table->index('etapa_gestion');
            $table->index('fecha_confirmada');
            $table->index('proximo_contacto_en');
        });

        // Las reservas que ya existen reciben su ficha de gestión vacía.
        // No se inventa historia: solo se parte de la situación actual.
        $ahora = now();

        DB::table('reserva')->orderBy('id_rese')->chunk(200, function ($reservas) use ($ahora): void {
            $filas = [];

            foreach ($reservas as $r) {
                $filas[] = [
                    'id_rese' => $r->id_rese,
                    'etapa_gestion' => $r->estado_rese === 'confirmada' ? 'en_planificacion' : 'nueva',
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            DB::table('reserva_gestion')->insert($filas);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reserva_gestion');
    }
};