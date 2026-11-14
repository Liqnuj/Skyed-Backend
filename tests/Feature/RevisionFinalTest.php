<?php

/*
| Pruebas de los ajustes pedidos en la última revisión del proyecto:
|  - fecha de los eventos (desde mañana) y estado "finalizado" (automático y con botón);
|  - lista pública solo con eventos disponibles;
|  - PQR: el usuario escribe hasta que él la da por resuelta;
|  - resultados de todo un evento con posiciones automáticas por tiempo y categoría;
|  - el admin crea usuarios sin captcha (pero con términos);
|  - mensajes de validación en español.
*/

use App\Models\CategoriaCompetencia;
use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Pqr;
use App\Models\Resultado;
use App\Models\RutaEvento;
use App\Models\User;
use function Pest\Laravel\getJson;

function eventoRevision(array $datos = []): EventoDeportivo
{
    return EventoDeportivo::create(array_merge([
        'nombre_e' => 'Evento revisión',
        'categoria_e' => 'ruta',
        'precio_e' => 50000,
        'fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addWeek()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Sogamoso',
        'descripcion_e' => 'Evento de prueba.',
        'requisitos_e' => 'Casco.',
        'imagen_e' => 'eventos/x.jpg',
        'cupos_disponibles_e' => 30,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ], $datos));
}

function inscripcionRevision(EventoDeportivo $evento, ?User $usuario = null, array $datos = []): Inscripcion
{
    $usuario ??= User::factory()->create();

    return Inscripcion::create(array_merge([
        'cupo_i' => 1,
        'estado_i' => 'confirmada',
        'fecha_i' => now(),
        'precio_pagado_i' => 50000,
        'contacto_emergencia_nombre' => 'Contacto',
        'contacto_emergencia_telefono' => '3001234567',
        'contacto_emergencia_parentesco' => 'Madre',
        'id_u' => $usuario->id_u,
        'id_e' => $evento->id_e,
    ], $datos));
}

function datosNuevoEvento(array $datos = []): array
{
    $ruta = RutaEvento::create(['nombre_re' => 'Ruta revisión ' . uniqid(), 'estado_re' => 'activo']);

    return array_merge([
        'nombre_e' => 'Gran Fondo',
        'categoria_e' => 'ruta',
        'precio_e' => 60000,
        'fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addDays(10)->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Tunja',
        'descripcion_e' => 'Recorrido.',
        'requisitos_e' => 'Casco.',
        'imagen_e' => 'eventos/g.jpg',
        'cupos_disponibles_e' => 40,
        'ruta_id_re' => $ruta->id_re,
    ], $datos);
}

/* ---------------- Fecha de los eventos ---------------- */

it('no deja crear un evento para hoy ni para un día que ya pasó', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $hoy = now(EventoDeportivo::ZONA_HORARIA)->toDateString();
    $ayer = now(EventoDeportivo::ZONA_HORARIA)->subDay()->toDateString();

    foreach ([$hoy, $ayer] as $fecha) {
        $this->actingAs($admin)
            ->postJson('/api/eventos', datosNuevoEvento(['fecha_e' => $fecha]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('fecha_e');
    }

    $this->actingAs($admin)
        ->postJson('/api/eventos', datosNuevoEvento(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addDay()->toDateString()]))
        ->assertCreated();
});

it('al editar acepta la misma fecha aunque ya pasó, pero no una fecha pasada nueva', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDays(3)->toDateString(), 'estado_e' => 'finalizado']);

    // Corregir otro dato sin tocar la fecha: se permite.
    $this->actingAs($admin)
        ->putJson("/api/eventos/{$evento->id_e}", ['nombre_e' => 'Nombre corregido', 'fecha_e' => $evento->fecha_e->toDateString()])
        ->assertOk();

    // Cambiarla a otro día pasado: no.
    $this->actingAs($admin)
        ->putJson("/api/eventos/{$evento->id_e}", ['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDay()->toDateString()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('fecha_e');

    // Reprogramarlo al futuro lo vuelve a abrir.
    $this->actingAs($admin)
        ->putJson("/api/eventos/{$evento->id_e}", ['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addWeek()->toDateString()])
        ->assertOk();
    expect($evento->fresh()->estado_e)->toBe('activo');
});

/* ---------------- Estado "finalizado" ---------------- */

it('los eventos activos cuya fecha ya pasó quedan finalizados al consultarlos; los inhabilitados no', function () {
    $pasado = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDays(2)->toDateString()]);
    $inhabilitado = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDays(2)->toDateString(), 'estado_e' => 'inhabilitado']);
    $hoy = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->toDateString()]);
    $futuro = eventoRevision();

    getJson('/api/eventos')->assertOk();

    expect($pasado->fresh()->estado_e)->toBe('finalizado')
        ->and($inhabilitado->fresh()->estado_e)->toBe('inhabilitado')
        ->and($hoy->fresh()->estado_e)->toBe('activo')
        ->and($futuro->fresh()->estado_e)->toBe('activo');
});

it('el comando eventos:finalizar marca los eventos vencidos', function () {
    $pasado = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDay()->toDateString(), 'estado_e' => 'inactivo']);

    $this->artisan('eventos:finalizar')->assertExitCode(0);

    expect($pasado->fresh()->estado_e)->toBe('finalizado');
});

it('el botón Finalizar solo funciona desde el día del evento', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $futuro = eventoRevision();
    $hoy = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->toDateString()]);

    $this->actingAs($admin)
        ->patchJson("/api/eventos/{$futuro->id_e}/estado", ['estado_e' => 'finalizado'])
        ->assertStatus(422);

    $this->actingAs($admin)
        ->patchJson("/api/eventos/{$hoy->id_e}/estado", ['estado_e' => 'finalizado'])
        ->assertOk()
        ->assertJsonPath('evento.estado', 'finalizado');
});

it('un evento finalizado con fecha pasada no se reabre cambiando solo el estado', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDays(5)->toDateString(), 'estado_e' => 'finalizado']);

    $this->actingAs($admin)
        ->patchJson("/api/eventos/{$evento->id_e}/estado", ['estado_e' => 'activo'])
        ->assertStatus(422);
});

it('no recibe inscripciones en un evento finalizado', function () {
    $evento = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDay()->toDateString(), 'estado_e' => 'finalizado']);
    $participante = userWithRole('participante', 'deportivo');

    $this->actingAs($participante)
        ->postJson("/api/eventos/{$evento->id_e}/inscripciones", ['metodo_pago' => 'efectivo'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'Este evento ya finalizó: no recibe más inscripciones.');
});

it('la lista pública de eventos disponibles solo trae activos de hoy en adelante, en orden de fecha', function () {
    $lejano = eventoRevision(['nombre_e' => 'Lejano', 'fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addMonth()->toDateString()]);
    $cercano = eventoRevision(['nombre_e' => 'Cercano', 'fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->addDays(2)->toDateString()]);
    eventoRevision(['nombre_e' => 'Inactivo', 'estado_e' => 'inactivo']);
    eventoRevision(['nombre_e' => 'Pasado', 'fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->subDays(3)->toDateString()]);

    $nombres = collect(getJson('/api/eventos?disponibles=1')->assertOk()->json('data'))->pluck('nombre')->all();

    expect($nombres)->toBe(['Cercano', 'Lejano']);
});

/* ---------------- PQR: el usuario decide cuándo quedó resuelta ---------------- */

function pqrDe(User $usuario, string $estado): Pqr
{
    return Pqr::create([
        'tipo_pqr' => 'peticion',
        'asunto_pqr' => 'Mi solicitud',
        'mensaje_pqr' => 'Necesito ayuda con mi inscripción.',
        'estado_pqr' => $estado,
        'creado_en_pqr' => now(),
        'id_u' => $usuario->id_u,
        'contexto_pqr' => 'deportivo',
    ]);
}

it('el usuario puede escribir en una PQR resuelta y eso la reabre en proceso', function () {
    $usuario = userWithRole('participante', 'deportivo');
    $pqr = pqrDe($usuario, 'resuelto');

    $this->actingAs($usuario)
        ->postJson("/api/pqr/{$pqr->id_pqr}/responder", ['mensaje' => 'Todavía no se soluciona.'])
        ->assertCreated()
        ->assertJsonPath('reabierta', true);

    expect($pqr->fresh()->estado_pqr)->toBe('en_proceso');
});

it('una PQR cerrada ya no recibe mensajes', function () {
    $usuario = userWithRole('participante', 'deportivo');
    $pqr = pqrDe($usuario, 'cerrado');

    $this->actingAs($usuario)
        ->postJson("/api/pqr/{$pqr->id_pqr}/responder", ['mensaje' => 'Hola'])
        ->assertStatus(422);
});

it('el dueño cierra su PQR como resuelta; otra persona no puede', function () {
    $usuario = userWithRole('participante', 'deportivo');
    $otro = userWithRole('participante', 'deportivo');
    $pqr = pqrDe($usuario, 'en_proceso');

    $this->actingAs($otro)->postJson("/api/pqr/{$pqr->id_pqr}/conforme")->assertStatus(403);

    $this->actingAs($usuario)->postJson("/api/pqr/{$pqr->id_pqr}/conforme")->assertOk();

    expect($pqr->fresh()->estado_pqr)->toBe('cerrado')
        ->and($pqr->seguimientos()->where('tipo_evento', 'cerrada_cliente')->exists())->toBeTrue();
});

/* ---------------- Resultados de todo un evento ---------------- */

it('guarda los tiempos de todo el evento y calcula posiciones generales y por categoría', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision(['fecha_e' => now(EventoDeportivo::ZONA_HORARIA)->toDateString()]);
    $elite = CategoriaCompetencia::create(['nombre_cc' => 'Élite', 'id_e' => $evento->id_e]);
    $master = CategoriaCompetencia::create(['nombre_cc' => 'Máster', 'id_e' => $evento->id_e]);

    $a = inscripcionRevision($evento, null, ['id_cc' => $elite->id_cc]);
    $b = inscripcionRevision($evento, null, ['id_cc' => $elite->id_cc]);
    $c = inscripcionRevision($evento, null, ['id_cc' => $master->id_cc]);
    $d = inscripcionRevision($evento, null, ['id_cc' => $master->id_cc]);

    $this->actingAs($admin)
        ->getJson("/api/eventos/{$evento->id_e}/resultados/tablero")
        ->assertOk()
        ->assertJsonCount(4, 'participantes');

    $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/resultados/lote", [
            'estado_r' => 'oficial',
            'filas' => [
                ['id_i' => $a->id_i, 'tiempo_final_r' => '02:10:00'],
                ['id_i' => $b->id_i, 'tiempo_final_r' => '02:05:00'],
                ['id_i' => $c->id_i, 'tiempo_final_r' => '02:20:00'],
                ['id_i' => $d->id_i, 'tiempo_final_r' => '02:00:00', 'descalificado' => true],
            ],
        ])
        ->assertOk();

    $r = fn (Inscripcion $i) => Resultado::with('categoriaResultado')->where('id_i', $i->id_i)->first();

    expect($r($b)->posicion_general_r)->toBe(1)
        ->and($r($a)->posicion_general_r)->toBe(2)
        ->and($r($c)->posicion_general_r)->toBe(3)
        ->and($r($d)->posicion_general_r)->toBeNull()
        ->and($r($d)->estado_r)->toBe('descalificado')
        ->and($r($b)->categoriaResultado->posicion_categoria)->toBe(1)
        ->and($r($a)->categoriaResultado->posicion_categoria)->toBe(2)
        ->and($r($c)->categoriaResultado->posicion_categoria)->toBe(1);
});

it('no registra resultados de una inscripción de otro evento o sin confirmar', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision();
    $otro = eventoRevision();
    $ajena = inscripcionRevision($otro);
    $pendiente = inscripcionRevision($evento, null, ['estado_i' => 'pendiente']);

    foreach ([$ajena, $pendiente] as $ins) {
        $this->actingAs($admin)
            ->postJson("/api/eventos/{$evento->id_e}/resultados/lote", [
                'estado_r' => 'oficial',
                'filas' => [['id_i' => $ins->id_i, 'tiempo_final_r' => '01:00:00']],
            ])
            ->assertStatus(422);
    }
});

/* ---------------- Crear usuarios desde el panel ---------------- */

it('un administrador crea cuentas sin captcha, pero con los términos aceptados', function () {
    config(['services.captcha.enabled' => true]);
    $admin = userWithRole('adminDeportivo', 'deportivo');

    $datos = [
        'tipo_documento_u' => 'cedula_ciudadania',
        'documento_u' => '1098765432',
        'nombre_u' => 'Laura',
        'apellido_u' => 'Gómez',
        'telefono_u' => '3109876543',
        'correo_u' => 'laura.gomez@correo.com',
        'fecha_nacimiento_u' => '1995-05-05',
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
        'contexto' => 'deportivo',
    ];

    // Sin términos: no.
    $this->actingAs($admin, 'sanctum')->postJson('/api/register', $datos)
        ->assertStatus(422)
        ->assertJsonValidationErrors('acepta_terminos');

    // Con términos y sin captcha: sí.
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/register', $datos + ['acepta_terminos' => true, 'acepta_privacidad' => true])
        ->assertCreated();
});

it('el registro público sigue pidiendo el captcha', function () {
    config(['services.captcha.enabled' => true]);

    $this->postJson('/api/register', [
        'tipo_documento_u' => 'cedula_ciudadania',
        'documento_u' => '1098765433',
        'nombre_u' => 'Pedro',
        'apellido_u' => 'Ruiz',
        'telefono_u' => '3109876544',
        'correo_u' => 'pedro.ruiz@correo.com',
        'fecha_nacimiento_u' => '1995-05-05',
        'contrasena_u' => CLAVE_VALIDA,
        'contrasena_u_confirmation' => CLAVE_VALIDA,
        'acepta_terminos' => true,
        'acepta_privacidad' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('captcha');
});

/* ---------------- Mensajes en español ---------------- */

it('los errores de validación llegan en español, no como claves crudas', function () {
    app()->setLocale('es');
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision();

    $mensaje = $this->actingAs($admin)
        ->postJson("/api/eventos/{$evento->id_e}/estaciones", [
            'nombre_est' => 'Hidratación',
            'tipo_est' => 'hidratacion',
            'kilometro_est' => 'diez',
            'estado_est' => 'activa',
        ])
        ->assertStatus(422)
        ->json('errors.kilometro_est.0');

    expect($mensaje)->not->toStartWith('validation.')
        ->and($mensaje)->toContain('kilómetro');
});

/* ---------------- Páginas públicas y privacidad ---------------- */

it('un visitante ve los resultados oficiales solo con el nombre del competidor', function () {
    $evento = eventoRevision();
    $ins = inscripcionRevision($evento);
    Resultado::create(['tiempo_final_r' => '01:30:00', 'estado_r' => 'oficial', 'posicion_general_r' => 1, 'id_i' => $ins->id_i]);
    $otra = inscripcionRevision($evento);
    Resultado::create(['tiempo_final_r' => '01:40:00', 'estado_r' => 'en revision', 'id_i' => $otra->id_i]);

    $datos = getJson('/api/resultados')->assertOk()->json('resultados.data');

    expect($datos)->toHaveCount(1)
        ->and($datos[0]['inscripcion']['usuario'])->toHaveKeys(['nombre_u', 'apellido_u'])
        ->and($datos[0]['inscripcion']['usuario'])->not->toHaveKeys(['correo_u', 'telefono_u', 'documento_u', 'fecha_nacimiento_u']);
});

it('el administrador sigue viendo todos los resultados con sus datos', function () {
    $admin = userWithRole('adminDeportivo', 'deportivo');
    $evento = eventoRevision();
    $ins = inscripcionRevision($evento);
    Resultado::create(['tiempo_final_r' => '01:40:00', 'estado_r' => 'en revision', 'id_i' => $ins->id_i]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/resultados')
        ->assertOk()
        ->assertJsonPath('resultados.data.0.inscripcion.usuario.correo_u', $ins->usuario->correo_u);
});

it('las rutas que usan las páginas públicas responden sin iniciar sesión', function () {
    $evento = eventoRevision();

    getJson("/api/eventos/{$evento->id_e}/rutas")->assertOk();
    getJson('/api/tipos-evento?modulo=social')->assertOk();
    getJson('/api/servicios')->assertOk();
});

/* ---------------- Recuperar contraseña por documento ---------------- */

it('recuperar por documento no revela el correo y permite terminar con el documento', function () {
    \Illuminate\Support\Facades\Mail::fake();
    $usuario = User::factory()->create(['tipo_documento_u' => 'cedula_ciudadania', 'documento_u' => 1077123456]);

    $respuesta = $this->postJson('/api/enviar-codigo-documento', [
        'tipo_documento_u' => 'cedula_ciudadania',
        'documento_u' => '1077123456',
    ])->assertOk();

    expect($respuesta->json())->not->toHaveKey('email_real')
        ->and($respuesta->json('email_enmascarado'))->not->toBe($usuario->correo_u);

    $codigo = (string) $usuario->fresh()->codigo;
    $identidad = ['tipo_documento_u' => 'cedula_ciudadania', 'documento_u' => '1077123456'];

    $this->postJson('/api/verificar-codigo', $identidad + ['token' => $codigo])->assertOk();

    $this->postJson('/api/reset-password', $identidad + [
        'token' => $codigo,
        'contrasena_u' => 'NuevaClave2026',
        'contrasena_u_confirmation' => 'NuevaClave2026',
    ])->assertOk();

    expect(\Illuminate\Support\Facades\Hash::check('NuevaClave2026', $usuario->fresh()->contrasena_u))->toBeTrue();
});
