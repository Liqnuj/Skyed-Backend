<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Talla y dorsal son de cada participante (antes estaban en `kit`,
        // que es del evento y no de la persona).
        Schema::table('inscripcion', function (Blueprint $table) {
            $table->string('talla_camiseta_i', 10)->nullable()->after('contacto_emergencia_parentesco');
            $table->unsignedInteger('numero_dorsal_i')->nullable()->after('talla_camiseta_i');
        });

        // El historial queda ligado a la inscripción concreta.
        Schema::table('historial_participacion', function (Blueprint $table) {
            $table->unsignedBigInteger('id_i')->nullable()->after('id_e');
            $table->foreign('id_i')->references('id_i')->on('inscripcion')->nullOnDelete();
        });

        // Una persona no puede tener dos inscripciones activas al mismo evento
        // (el controlador ya lo valida; esto lo garantiza la base de datos).
        DB::statement(
            "CREATE UNIQUE INDEX inscripcion_activa_unica
             ON inscripcion (id_u, id_e)
             WHERE estado_i IN ('pendiente', 'confirmada')"
        );

        // Un dorsal no se repite dentro del mismo evento.
        DB::statement(
            "CREATE UNIQUE INDEX inscripcion_dorsal_unico
             ON inscripcion (id_e, numero_dorsal_i)
             WHERE numero_dorsal_i IS NOT NULL AND estado_i <> 'cancelada'"
        );

        // Datos que ya existen: una fila de historial por cada inscripción
        // no cancelada ("asistio" si su QR ya se usó, si no "inscrito").
        DB::statement(
            "INSERT INTO historial_participacion
                (fecha_hp, estado_hp, observaciones_hp, id_u, id_e, id_i, created_at, updated_at)
             SELECT
                CASE WHEN q.estado_qr = 'usado' THEN COALESCE(q.fecha_uso_qr, i.fecha_i) ELSE i.fecha_i END,
                CASE WHEN q.estado_qr = 'usado' THEN 'asistio' ELSE 'inscrito' END,
                NULL, i.id_u, i.id_e, i.id_i, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM inscripcion i
             LEFT JOIN qr_entrada q ON q.id_i = i.id_i
             WHERE i.estado_i <> 'cancelada'
               AND NOT EXISTS (
                   SELECT 1 FROM historial_participacion h
                   WHERE h.id_u = i.id_u AND h.id_e = i.id_e
               )"
        );

        // Y una entrega de kit pendiente cuando el evento tiene kit.
        DB::statement(
            "INSERT INTO entrega_kit
                (estado_ek, id_k, id_u, id_e, created_at, updated_at)
             SELECT 'pendiente', e.id_k, i.id_u, i.id_e, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
             FROM inscripcion i
             JOIN \"eventoDeportivo\" e ON e.id_e = i.id_e
             WHERE i.estado_i <> 'cancelada'
               AND e.id_k IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM entrega_kit k
                   WHERE k.id_u = i.id_u AND k.id_e = i.id_e
               )"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS inscripcion_dorsal_unico');
        DB::statement('DROP INDEX IF EXISTS inscripcion_activa_unica');

        Schema::table('historial_participacion', function (Blueprint $table) {
            $table->dropForeign(['id_i']);
            $table->dropColumn('id_i');
        });

        Schema::table('inscripcion', function (Blueprint $table) {
            $table->dropColumn(['talla_camiseta_i', 'numero_dorsal_i']);
        });
    }
};