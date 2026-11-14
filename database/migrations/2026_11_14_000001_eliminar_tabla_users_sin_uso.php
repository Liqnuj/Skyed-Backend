<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza de la base de datos (revisión final del proyecto).
 *
 * La tabla "users" es la que Laravel crea por defecto. SKYED guarda las
 * cuentas en "usuario" (modelo App\Models\User con $table = 'usuario'),
 * así que "users" nunca tuvo datos ni la usa ningún controlador, modelo
 * ni llave foránea. Se elimina para que la base solo tenga lo que el
 * sistema realmente usa.
 *
 * Las demás tablas se revisaron una por una y todas tienen uso:
 * las de sesiones, caché y colas (sessions, cache, cache_locks, jobs,
 * job_batches, failed_jobs) las usa Laravel cuando el proyecto corre sin
 * Docker (SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=database), y
 * password_reset_tokens la usa la recuperación de contraseña.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('users');
    }

    /** Por si hay que revertir: deja la tabla como la crea Laravel. */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
