
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pqr_seguimientos', function (Blueprint $table) {
            $table->id('id_seguimiento');

            $table->foreignId('id_pqr')
                ->constrained('pqr', 'id_pqr')
                ->cascadeOnDelete();

            $table->foreignId('id_admin')
                ->nullable()
                ->constrained('usuario', 'id_u')
                ->nullOnDelete();

            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->string('tipo_evento', 30)->default('actualizacion');

            $table->text('comentario_publico')->nullable();
            $table->text('nota_interna')->nullable();

            $table->timestamps();

            $table->index(['id_pqr', 'created_at']);
        });

        // Registrar el estado actual de las PQRS existentes.
        // No inventamos el historial anterior a esta migración.
        DB::table('pqr')->orderBy('id_pqr')
            ->chunk(100, function ($pqrs): void {
                foreach ($pqrs as $pqr) {
                    DB::table('pqr_seguimientos')->insert([
                        'id_pqr' => $pqr->id_pqr,
                        'id_admin' => null,
                        'estado_anterior' => null,
                        'estado_nuevo' => $pqr->estado_pqr,
                        'tipo_evento' => 'migracion',
                        'comentario_publico' =>
                            'El seguimiento digital está habilitado. ' .
                            'Las actuaciones anteriores no cuentan ' .
                            'con un historial detallado.',
                        'nota_interna' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('pqr_seguimientos');
    }
};
