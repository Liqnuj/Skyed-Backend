<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Amplía el historial de la reserva (seguimiento_reserva) para tener
 * trazabilidad completa, igual que ya hace pqr_seguimientos:
 *  - id_admin:        quién hizo el cambio (vacío = el cliente o el sistema);
 *  - tipo_evento:     qué clase de registro es (creacion, cambio_estado, contacto...);
 *  - estado_anterior / estado_nuevo: para los cambios de estado o de etapa;
 *  - visible_usuario: false = nota interna, el cliente nunca la recibe.
 *
 * Los registros que ya existen quedan visibles para el cliente (como hasta hoy)
 * y no se modifica ningún dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seguimiento_reserva', function (Blueprint $table) {
            if (! Schema::hasColumn('seguimiento_reserva', 'id_admin')) {
                $table->foreignId('id_admin')
                    ->nullable()
                    ->constrained('usuario', 'id_u')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('seguimiento_reserva', 'tipo_evento')) {
                $table->string('tipo_evento', 30)->default('actualizacion');
            }

            if (! Schema::hasColumn('seguimiento_reserva', 'estado_anterior')) {
                $table->string('estado_anterior', 30)->nullable();
            }

            if (! Schema::hasColumn('seguimiento_reserva', 'estado_nuevo')) {
                $table->string('estado_nuevo', 30)->nullable();
            }

            if (! Schema::hasColumn('seguimiento_reserva', 'visible_usuario')) {
                $table->boolean('visible_usuario')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_reserva', function (Blueprint $table) {
            if (Schema::hasColumn('seguimiento_reserva', 'id_admin')) {
                $table->dropConstrainedForeignId('id_admin');
            }

            $columnas = array_values(array_filter(
                ['tipo_evento', 'estado_anterior', 'estado_nuevo', 'visible_usuario'],
                fn ($c) => Schema::hasColumn('seguimiento_reserva', $c)
            ));

            if ($columnas) {
                $table->dropColumn($columnas);
            }
        });
    }
};