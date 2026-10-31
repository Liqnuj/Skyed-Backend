<?php

use App\Models\Ambiente;
use App\Models\Notificacion;
use App\Models\Reserva;
use App\Models\SeguimientoReserva;
use App\Models\Servicio;
use App\Models\TipoEvento;
use App\Models\User;

/*
| Módulo de gestión de reservas y eventos del Administrador Social.
| Cada bloque indica a qué caso de la lista de pruebas corresponde.
*/

/** Entra como ese usuario. Reinicia los guards para poder alternar cliente y admin dentro de una misma prueba. */
function comoUsuario(User $usuario)
{
    app('auth')->forgetGuards();

    return test()->actingAs($usuario);
}

function tipoCumpleanos(): TipoEvento
{
    return TipoEvento::firstOrCreate(
        ['nombre_tipo_eves' => 'Cumpleaños', 'modulo_tipo_eves' => 'social'],
        ['descripcion_eves' => 'Celebración de cumpleaños']
    );
}

function datosReservaCliente(array $sobre = []): array
{
    return array_merge([
        'id_tipo_eves' => tipoCumpleanos()->id_tipo_eves,
        'fecha_evento_rese' => now()->addDays(20)->toDateString(),
        'invitados_rese' => 60,
        'presupuesto_rese' => 3000000,
        'ubicacion_rese' => 'Centro recreativo',
        'Observaciones_rese' => 'Quiero decoración infantil',
        'nombre_rese' => 'Cumpleaños de Sofía',
    ], $sobre);
}

/** Crea una reserva como cliente, por la API (igual que el formulario real). Devuelve su id. */
function reservaDeCliente(User $cliente, array $sobre = []): int
{
    comoUsuario($cliente)->postJson('/api/reservas', datosReservaCliente($sobre))->assertCreated();

    return (int) Reserva::latest('id_rese')->value('id_rese');
}

/** Deja una reserva lista para confirmar (fecha y dato obligatorio del tipo). */
function prepararParaConfirmar(User $admin, int $id, ?string $fecha = null): void
{
    comoUsuario($admin)->putJson("/api/reservas/{$id}/gestion", [
        'fecha_confirmada' => $fecha ?? now()->addDays(25)->toDateString(),
        'datos_tipo' => ['nombre_cumpleanero' => 'Sofía'],
    ])->assertOk();
}

/* ================================================================
   CASO 1 · El usuario crea una reserva y le llega al Administrador Social
================================================================ */

it('caso 1: la reserva del cliente llega al administrador social', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $servicio = Servicio::create(['nombre_s' => 'Sonido']);

    comoUsuario($cliente)->postJson('/api/reservas', datosReservaCliente([
        'fecha_hasta_rese' => now()->addDays(25)->toDateString(),
        'hora_aprox_rese' => '15:00',
        'duracion_horas_rese' => 4,
        'servicios_solicitados' => [
            ['nombre_servicio' => 'DJ', 'cantidad' => 1],
            ['id_s' => $servicio->id_s],
        ],
    ]))->assertCreated();

    $reserva = Reserva::latest('id_rese')->first();

    // Quedó su ficha de gestión en "nueva" y sus servicios como pedidos por el cliente.
    expect($reserva->gestion->etapa_gestion)->toBe('nueva');
    expect($reserva->servicios()->where('origen', 'cliente')->count())->toBe(2);
    expect($reserva->servicios()->where('nombre_servicio', 'Sonido')->exists())->toBeTrue();

    // El administrador la ve en su listado y en el resumen.
    $lista = comoUsuario($admin)->getJson('/api/reservas')->assertOk();
    expect(collect($lista->json('data'))->pluck('id_rese')->all())->toContain($reserva->id_rese);

    comoUsuario($admin)->getJson('/api/reservas/resumen')
        ->assertOk()
        ->assertJsonPath('resumen.nuevas', 1);

    // Y recibe un aviso.
    expect(Notificacion::where('user_id', $admin->id_u)->where('tipo', 'reserva_admin')->exists())->toBeTrue();
});

/* ================================================================
   CASO 2 · El administrador abre la reserva y ve toda la información
================================================================ */

it('caso 2: el administrador abre la reserva y ve la información del cliente', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente, ['servicios_solicitados' => [['nombre_servicio' => 'DJ']]]);

    comoUsuario($admin)->getJson("/api/reservas/{$id}")
        ->assertOk()
        ->assertJsonPath('reserva.usuario.correo_u', $cliente->correo_u)
        ->assertJsonPath('reserva.usuario.telefono_u', $cliente->telefono_u)
        ->assertJsonPath('reserva.tipo_evento.nombre_tipo_eves', 'Cumpleaños')
        ->assertJsonPath('reserva.invitados_rese', 60)
        ->assertJsonPath('reserva.ubicacion_rese', 'Centro recreativo')
        ->assertJsonPath('reserva.servicios.0.nombre_servicio', 'DJ')
        ->assertJsonPath('meta.grupo_tipo', 'cumpleanos')
        ->assertJsonPath('meta.fecha_efectiva.tipo', 'solicitada');

    // Al abrirla pasa de "nueva" a "en revisión" y queda en el historial.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/abrir")
        ->assertOk()
        ->assertJsonPath('reserva.gestion.etapa_gestion', 'en_revision');

    expect(SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'cambio_etapa')->where('id_admin', $admin->id_u)->exists())->toBeTrue();

    // Abrirla otra vez no cambia nada.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/abrir")->assertOk()->assertJsonPath('message', 'Reserva consultada.');
});

/* ================================================================
   CASO 3 · El administrador completa la información administrativa
================================================================ */

it('caso 3: la información administrativa queda guardada y no toca los datos del cliente', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);
    $salon = Ambiente::create(['nombre_a' => 'Salón Principal', 'capacidad_a' => 100]);
    $original = Reserva::find($id)->only(['fecha_evento_rese', 'invitados_rese', 'ubicacion_rese', 'Observaciones_rese', 'presupuesto_rese']);

    comoUsuario($admin)->putJson("/api/reservas/{$id}/gestion", [
        'id_responsable' => $admin->id_u,
        'fecha_propuesta' => now()->addDays(22)->toDateString(),
        'hora_inicio' => '15:00',
        'hora_fin' => '20:00',
        'id_a' => $salon->id_a,
        'asistentes_final' => 80,
        'distribucion_espacio' => 'Mesas redondas',
        'decoracion' => ['requiere' => true, 'tematica' => 'Sirenas', 'colores' => 'Azul y dorado', 'estado' => 'pendiente'],
        'logistica' => ['montaje' => 'Desde las 10:00', 'personal' => '4 personas'],
        'datos_tipo' => ['nombre_cumpleanero' => 'Sofía', 'edad_cumplida' => 8, 'torta' => true],
    ])->assertOk()->assertJsonPath('message', 'Planificación guardada.');

    $g = Reserva::find($id)->gestion;
    expect($g->id_responsable)->toBe($admin->id_u);
    expect($g->fecha_propuesta->toDateString())->toBe(now()->addDays(22)->toDateString());
    expect($g->id_a)->toBe($salon->id_a);
    expect($g->asistentes_final)->toBe(80);
    expect($g->decoracion['tematica'])->toBe('Sirenas');
    expect($g->logistica['personal'])->toBe('4 personas');
    expect($g->datos_tipo['nombre_cumpleanero'])->toBe('Sofía');

    // Lo que escribió el cliente sigue igual.
    expect(Reserva::find($id)->only(['fecha_evento_rese', 'invitados_rese', 'ubicacion_rese', 'Observaciones_rese', 'presupuesto_rese']))->toEqual($original);

    // Quedó el registro de qué se tocó, y la propuesta de fecha sí se le muestra al cliente.
    expect(SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'edicion')->where('visible_usuario', false)->exists())->toBeTrue();
    expect(SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'cambio_fecha')->where('visible_usuario', true)->exists())->toBeTrue();

    // Guardar lo mismo otra vez no cambia nada.
    comoUsuario($admin)->putJson("/api/reservas/{$id}/gestion", ['hora_inicio' => '15:00', 'asistentes_final' => 80])
        ->assertOk()->assertJsonPath('message', 'No hubo cambios que guardar.');
});

it('rechaza datos administrativos inválidos', function () {
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente(userWithRole('cliente'));
    $salon = Ambiente::create(['nombre_a' => 'Salón Pequeño', 'capacidad_a' => 50]);

    $url = "/api/reservas/{$id}/gestion";

    comoUsuario($admin)->putJson($url, ['hora_inicio' => '20:00', 'hora_fin' => '18:00'])
        ->assertUnprocessable()->assertJsonValidationErrors(['hora_fin']);

    comoUsuario($admin)->putJson($url, ['id_a' => $salon->id_a, 'asistentes_final' => 80])
        ->assertUnprocessable()->assertJsonValidationErrors(['asistentes_final']);

    comoUsuario($admin)->putJson($url, ['fecha_confirmada' => now()->subDays(3)->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_confirmada']);

    comoUsuario($admin)->putJson($url, ['id_responsable' => userWithRole('cliente')->id_u])
        ->assertUnprocessable()->assertJsonValidationErrors(['id_responsable']);

    comoUsuario($admin)->putJson($url, ['fecha_limite_preparacion' => now()->addDays(60)->toDateString()])
        ->assertUnprocessable()->assertJsonValidationErrors(['fecha_limite_preparacion']);

    comoUsuario($admin)->putJson($url, ['datos_tipo' => ['edad_cumplida' => 'muchos']])
        ->assertUnprocessable()->assertJsonValidationErrors(['datos_tipo.edad_cumplida']);

    // Nada de lo anterior se guardó.
    expect(Reserva::find($id)->gestion->asistentes_final)->toBeNull();
});

it('registra los contactos con el usuario y los servicios de la reserva', function () {
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente(userWithRole('cliente'));

    comoUsuario($admin)->postJson("/api/reservas/{$id}/contacto", [
        'medio' => 'telefono',
        'observaciones' => 'Confirmó disponibilidad',
        'compromisos' => 'Enviar cotización',
        'proximo_contacto_en' => now()->addDays(3)->format('Y-m-d H:i'),
    ])->assertOk()->assertJsonPath('reserva.gestion.etapa_gestion', 'contactando_usuario');

    $g = Reserva::find($id)->gestion;
    expect($g->contactado)->toBeTrue();
    expect($g->medio_ultimo_contacto)->toBe('telefono');
    expect($g->compromisos)->toBe('Enviar cotización');
    expect(SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'contacto')->where('visible_usuario', false)->exists())->toBeTrue();

    comoUsuario($admin)->postJson("/api/reservas/{$id}/contacto", ['medio' => 'paloma'])
        ->assertUnprocessable()->assertJsonValidationErrors(['medio']);

    // Servicios: agregar, cambiar de estado, quitar.
    $resp = comoUsuario($admin)->postJson("/api/reservas/{$id}/servicios", ['nombre_servicio' => 'Catering', 'cantidad' => 2])
        ->assertCreated();
    $idServicio = collect($resp->json('reserva.servicios'))->firstWhere('nombre_servicio', 'Catering')['id_rs'];

    comoUsuario($admin)->putJson("/api/reservas/{$id}/servicios/{$idServicio}", ['estado' => 'confirmado'])->assertOk();
    expect(Reserva::find($id)->servicios()->where('id_rs', $idServicio)->value('estado'))->toBe('confirmado');

    // Un servicio de otra reserva no se puede tocar desde esta.
    $otro = reservaDeCliente(userWithRole('cliente'));
    comoUsuario($admin)->putJson("/api/reservas/{$otro}/servicios/{$idServicio}", ['estado' => 'no_disponible'])->assertNotFound();

    comoUsuario($admin)->deleteJson("/api/reservas/{$id}/servicios/{$idServicio}")->assertOk();
    expect(Reserva::find($id)->servicios()->where('id_rs', $idServicio)->exists())->toBeFalse();
});

/* ================================================================
   CASO 4 · El administrador cambia el estado y queda en el historial
================================================================ */

it('caso 4: cambiar el estado queda registrado en el historial', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);

    // Sin fecha confirmada ni datos obligatorios del tipo no se puede confirmar.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'la fecha confirmada') && str_contains($m, 'nombre del cumpleañero'));
    expect(Reserva::find($id)->estado_rese)->toBe('pendiente');

    prepararParaConfirmar($admin, $id);

    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", [
        'estado' => 'confirmada',
        'comentario_publico' => 'Tu reserva quedó confirmada.',
        'nota_interna' => 'Falta confirmar decoración.',
    ])->assertOk()->assertJsonPath('reserva.estado_rese', 'confirmada');

    expect(Reserva::find($id)->estado_rese)->toBe('confirmada');

    $cambio = SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'cambio_estado')->first();
    expect($cambio->comentario)->toBe('Estado cambiado a: confirmada');   // el perfil del cliente interpreta este texto
    expect($cambio->estado_anterior)->toBe('pendiente');
    expect($cambio->estado_nuevo)->toBe('confirmada');
    expect($cambio->id_admin)->toBe($admin->id_u);
    expect($cambio->visible_usuario)->toBeTrue();

    // La nota interna quedó marcada como interna, y el cliente recibió aviso.
    expect(SeguimientoReserva::where('id_rese', $id)->where('comentario', 'Falta confirmar decoración.')->value('visible_usuario'))->toBeFalse();
    expect(Notificacion::where('user_id', $cliente->id_u)->where('tipo', 'reserva')->exists())->toBeTrue();

    // Al confirmar, la etapa pasa sola a "en planificación".
    expect(Reserva::find($id)->gestion->etapa_gestion)->toBe('en_planificacion');
});

it('respeta las transiciones de estado y de etapa', function () {
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente(userWithRole('cliente'));

    // "En ejecución" exige la reserva confirmada; saltar de "nueva" a "en planificación" tampoco se permite.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/etapa", ['etapa' => 'en_ejecucion'])->assertUnprocessable();
    comoUsuario($admin)->postJson("/api/reservas/{$id}/etapa", ['etapa' => 'en_planificacion'])->assertUnprocessable();
    comoUsuario($admin)->postJson("/api/reservas/{$id}/etapa", ['etapa' => 'inventada'])->assertUnprocessable();

    comoUsuario($admin)->postJson("/api/reservas/{$id}/etapa", ['etapa' => 'en_revision'])->assertOk();

    // No se puede finalizar un evento sin fecha confirmada, ni uno cuya fecha aún no llega.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'completada'])->assertUnprocessable();
    prepararParaConfirmar($admin, $id);
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])->assertOk();
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'completada'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Solo se puede finalizar un evento cuya fecha confirmada ya llegó.');

    // Una reserva cancelada no vuelve a confirmarse ni se puede editar.
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'cancelada'])->assertOk();
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])->assertUnprocessable();
    comoUsuario($admin)->putJson("/api/reservas/{$id}/gestion", ['asistentes_final' => 10])->assertUnprocessable();
});

/* ================================================================
   CASO 5 · Una reserva con fecha aparece en el calendario
================================================================ */

it('caso 5: la reserva aparece en el calendario sin convertir la fecha aproximada en confirmada', function () {
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente(userWithRole('cliente'), [
        'fecha_evento_rese' => now()->addDays(20)->toDateString(),
        'fecha_hasta_rese' => now()->addDays(24)->toDateString(),
        'hora_aprox_rese' => '15:00',
        'duracion_horas_rese' => 4,
    ]);

    $resp = comoUsuario($admin)->getJson('/api/reservas/calendario?' . http_build_query([
        'desde' => now()->addDays(10)->toDateString(),
        'hasta' => now()->addDays(30)->toDateString(),
    ]))->assertOk();

    $evento = collect($resp->json('eventos'))->firstWhere('id_rese', $id);

    expect($evento)->not->toBeNull();
    expect($evento['titulo'])->toBe('Cumpleaños de Sofía');
    expect($evento['fecha_tipo'])->toBe('solicitada');
    expect($evento['aproximada'])->toBeTrue();
    expect($evento['fecha_inicio'])->toBe(now()->addDays(20)->toDateString());
    expect($evento['fecha_fin'])->toBe(now()->addDays(24)->toDateString());
    expect($evento['hora_inicio'])->toBe('15:00');
    expect($evento['hora_fin'])->toBe('19:00');
    expect($evento['estado_visual'])->toBe('nueva');
    expect($evento['tipo_evento']['nombre'])->toBe('Cumpleaños');

    // La fecha confirmada sigue vacía: el sistema no inventa una fecha definitiva.
    expect(Reserva::find($id)->gestion->fecha_confirmada)->toBeNull();

    // Fuera del rango no aparece.
    $fuera = comoUsuario($admin)->getJson('/api/reservas/calendario?' . http_build_query([
        'desde' => now()->addDays(40)->toDateString(),
        'hasta' => now()->addDays(50)->toDateString(),
    ]))->assertOk();
    expect($fuera->json('eventos'))->toBeEmpty();

    comoUsuario($admin)->getJson('/api/reservas/calendario?desde=2026-12-10&hasta=2026-12-01')->assertUnprocessable();
    comoUsuario($admin)->getJson('/api/reservas/calendario?desde=2026-01-01&hasta=2026-12-31')->assertUnprocessable();
});

/* ================================================================
   CASO 6 · Si la fecha cambia, el calendario se actualiza
================================================================ */

it('caso 6: al cambiar la fecha el calendario se actualiza y la reserva queda reprogramada', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);

    $rangoViejo = http_build_query(['desde' => now()->addDays(15)->toDateString(), 'hasta' => now()->addDays(25)->toDateString()]);
    $rangoNuevo = http_build_query(['desde' => now()->addDays(35)->toDateString(), 'hasta' => now()->addDays(45)->toDateString()]);

    prepararParaConfirmar($admin, $id, now()->addDays(22)->toDateString());
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])->assertOk();

    $evento = collect(comoUsuario($admin)->getJson("/api/reservas/calendario?{$rangoViejo}")->json('eventos'))->firstWhere('id_rese', $id);
    expect($evento['fecha_tipo'])->toBe('confirmada');
    expect($evento['fecha_inicio'])->toBe(now()->addDays(22)->toDateString());
    expect($evento['estado_visual'])->toBe('confirmada');

    // Se mueve la fecha confirmada a otro mes.
    $nueva = now()->addDays(40)->toDateString();
    comoUsuario($admin)->putJson("/api/reservas/{$id}/gestion", ['fecha_confirmada' => $nueva])
        ->assertOk()
        ->assertJsonPath('reserva.gestion.etapa_gestion', 'reprogramada');

    expect(collect(comoUsuario($admin)->getJson("/api/reservas/calendario?{$rangoViejo}")->json('eventos'))->pluck('id_rese')->all())->not->toContain($id);

    $evento = collect(comoUsuario($admin)->getJson("/api/reservas/calendario?{$rangoNuevo}")->json('eventos'))->firstWhere('id_rese', $id);
    expect($evento['fecha_inicio'])->toBe($nueva);
    expect($evento['estado_visual'])->toBe('reprogramada');

    // La fecha que pidió el cliente no se tocó, y el cambio le avisa y queda en su historial.
    expect(Reserva::find($id)->fecha_evento_rese->toDateString())->toBe(now()->addDays(20)->toDateString());
    // La primera fecha se fijó con la reserva aún pendiente (interna); el cambio posterior sí es visible para el cliente.
    expect(SeguimientoReserva::where('id_rese', $id)->where('tipo_evento', 'cambio_fecha')->where('visible_usuario', true)->count())->toBe(1);
    expect(Notificacion::where('user_id', $cliente->id_u)->where('mensaje', 'like', '%cambió del%')->exists())->toBeTrue();
});

/* ================================================================
   CASO 7 · Si la reserva se cancela, el calendario lo refleja
================================================================ */

it('caso 7: una reserva cancelada se ve cancelada en el calendario y avisa al administrador', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);

    comoUsuario($cliente)->postJson("/api/reservas/{$id}/cancelar", ['motivo' => 'Cambio de planes'])->assertOk();

    $resp = comoUsuario($admin)->getJson('/api/reservas/calendario?' . http_build_query([
        'desde' => now()->addDays(10)->toDateString(),
        'hasta' => now()->addDays(30)->toDateString(),
    ]))->assertOk();

    $evento = collect($resp->json('eventos'))->firstWhere('id_rese', $id);
    expect($evento['estado_rese'])->toBe('cancelada');
    expect($evento['estado_visual'])->toBe('cancelada');

    expect(Notificacion::where('user_id', $admin->id_u)->where('titulo', 'Reserva cancelada')->exists())->toBeTrue();

    // El cliente ve en su historial la cancelación con el texto de siempre.
    $historial = collect(comoUsuario($cliente)->getJson("/api/reservas/{$id}")->json('reserva.seguimientos'))->pluck('comentario')->all();
    expect($historial)->toContain('Estado cambiado a: cancelada');
});

/* ================================================================
   CASO 8 · Un usuario normal no accede a la información administrativa
================================================================ */

it('caso 8: el cliente no ve información administrativa ni notas internas', function () {
    $cliente = userWithRole('cliente');
    $otroCliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);

    comoUsuario($admin)->postJson("/api/reservas/{$id}/nota", ['nota' => 'Nota secreta del equipo'])->assertCreated();
    comoUsuario($admin)->postJson("/api/reservas/{$id}/seguimiento", ['comentario' => 'Mensaje público para el cliente'])->assertCreated();

    // Endpoints exclusivos del administrador social.
    foreach (['/api/reservas/resumen', '/api/reservas/calendario?desde=2026-11-01&hasta=2026-11-30', '/api/reservas/responsables'] as $ruta) {
        comoUsuario($cliente)->getJson($ruta)->assertForbidden();
    }

    // Su propia reserva: sin ficha administrativa, sin notas internas, sin id del admin.
    $resp = comoUsuario($cliente)->getJson("/api/reservas/{$id}")->assertOk();
    expect($resp->json('reserva'))->not->toHaveKey('gestion');
    expect($resp->json('reserva'))->not->toHaveKey('servicios');
    expect($resp->json())->not->toHaveKey('meta');

    $seguimientos = collect($resp->json('reserva.seguimientos'));
    expect($seguimientos->pluck('comentario')->all())->toContain('Mensaje público para el cliente');
    expect($seguimientos->pluck('comentario')->all())->not->toContain('Nota secreta del equipo');
    expect($seguimientos->first())->not->toHaveKey('id_admin');

    // Lo mismo en su listado.
    $lista = comoUsuario($cliente)->getJson('/api/reservas')->assertOk();
    expect(json_encode($lista->json()))->not->toContain('Nota secreta del equipo');

    // La reserva de otra persona no se puede ver.
    comoUsuario($otroCliente)->getJson("/api/reservas/{$id}")->assertForbidden();
});

/* ================================================================
   CASO 9 · Un usuario intenta modificar campos administrativos por la API
================================================================ */

it('caso 9: el cliente no puede modificar campos administrativos y el dato no cambia', function () {
    $cliente = userWithRole('cliente');
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente($cliente);

    comoUsuario($cliente)->putJson("/api/reservas/{$id}/gestion", ['fecha_confirmada' => now()->addDays(30)->toDateString(), 'asistentes_final' => 5])->assertForbidden();
    comoUsuario($cliente)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])->assertForbidden();
    comoUsuario($cliente)->postJson("/api/reservas/{$id}/etapa", ['etapa' => 'en_revision'])->assertForbidden();
    comoUsuario($cliente)->postJson("/api/reservas/{$id}/contacto", ['medio' => 'telefono'])->assertForbidden();
    comoUsuario($cliente)->postJson("/api/reservas/{$id}/nota", ['nota' => 'x'])->assertForbidden();
    comoUsuario($cliente)->postJson("/api/reservas/{$id}/servicios", ['nombre_servicio' => 'DJ'])->assertForbidden();
    comoUsuario($cliente)->putJson("/api/reservas/{$id}", ['estado_rese' => 'confirmada', 'total_rese' => 1])->assertForbidden();

    $reserva = Reserva::find($id);
    expect($reserva->estado_rese)->toBe('pendiente');
    expect((float) $reserva->total_rese)->toBe(0.0);
    expect($reserva->gestion->fecha_confirmada)->toBeNull();
    expect($reserva->gestion->asistentes_final)->toBeNull();
    expect($reserva->gestion->etapa_gestion)->toBe('nueva');

    // Sin sesión tampoco.
    app('auth')->forgetGuards();
    $this->putJson("/api/reservas/{$id}/gestion", [])->assertUnauthorized();

    // Ni siquiera el administrador puede pisar los datos que escribió el cliente por el PUT antiguo.
    comoUsuario($admin)->putJson("/api/reservas/{$id}", [
        'fecha_evento_rese' => now()->addDays(99)->toDateString(),
        'invitados_rese' => 1,
        'total_rese' => 500000,
    ])->assertOk();

    $reserva = Reserva::find($id);
    expect($reserva->fecha_evento_rese->toDateString())->toBe(now()->addDays(20)->toDateString());
    expect($reserva->invitados_rese)->toBe(60);
    expect((float) $reserva->total_rese)->toBe(500000.0);
});

it('responde con mensajes claros cuando la reserva no existe', function () {
    $admin = userWithRole('adminSocial');

    comoUsuario($admin)->getJson('/api/reservas/99999')->assertNotFound()->assertJsonPath('message', 'Reserva no encontrada');
    comoUsuario($admin)->postJson('/api/reservas/99999/abrir')->assertNotFound()->assertJsonPath('message', 'Reserva no encontrada');
    comoUsuario($admin)->putJson('/api/reservas/99999/gestion', [])->assertNotFound();
    comoUsuario($admin)->postJson('/api/reservas/99999/estado', ['estado' => 'confirmada'])->assertNotFound();
    comoUsuario($admin)->postJson('/api/reservas/99999/nota', ['nota' => 'x'])->assertNotFound();
});

/* ================================================================
   CASO 10 · Dos reservas con fechas cercanas
================================================================ */

it('caso 10: dos reservas cercanas aparecen completas en el calendario y en los filtros', function () {
    $admin = userWithRole('adminSocial');
    $dia = now()->addDays(20)->toDateString();

    $a = reservaDeCliente(userWithRole('cliente'), ['fecha_evento_rese' => $dia, 'nombre_rese' => 'Boda de Ana']);
    $b = reservaDeCliente(userWithRole('cliente'), ['fecha_evento_rese' => $dia, 'nombre_rese' => 'Fiesta de Luis']);
    $c = reservaDeCliente(userWithRole('cliente'), ['fecha_evento_rese' => now()->addDays(21)->toDateString(), 'nombre_rese' => 'Grado de Eva']);
    $lejos = reservaDeCliente(userWithRole('cliente'), ['fecha_evento_rese' => now()->addDays(80)->toDateString(), 'nombre_rese' => 'Evento lejano']);

    $resp = comoUsuario($admin)->getJson('/api/reservas/calendario?' . http_build_query([
        'desde' => now()->addDays(18)->toDateString(),
        'hasta' => now()->addDays(23)->toDateString(),
    ]))->assertOk();

    $eventos = collect($resp->json('eventos'));
    expect($eventos->pluck('id_rese')->sort()->values()->all())->toBe(collect([$a, $b, $c])->sort()->values()->all());
    expect($eventos->where('fecha_inicio', $dia)->count())->toBe(2);
    expect($eventos->pluck('titulo')->all())->toContain('Boda de Ana', 'Fiesta de Luis', 'Grado de Eva');
    expect($eventos->pluck('id_rese')->all())->not->toContain($lejos);

    // El listado filtra por fecha, por texto y por estado.
    $porFecha = comoUsuario($admin)->getJson('/api/reservas?' . http_build_query(['desde' => $dia, 'hasta' => $dia]))->assertOk();
    expect(collect($porFecha->json('data'))->pluck('id_rese')->sort()->values()->all())->toBe(collect([$a, $b])->sort()->values()->all());

    $porTexto = comoUsuario($admin)->getJson('/api/reservas?q=' . urlencode('fiesta de luis'))->assertOk();
    expect(collect($porTexto->json('data'))->pluck('id_rese')->all())->toBe([$b]);

    $porId = comoUsuario($admin)->getJson("/api/reservas?q=%23{$c}")->assertOk();
    expect(collect($porId->json('data'))->pluck('id_rese')->all())->toBe([$c]);

    comoUsuario($admin)->getJson('/api/reservas?estado=confirmada')->assertOk()->assertJsonCount(0, 'data');
});

/* ================================================================
   Panel principal: indicadores con datos reales
================================================================ */

it('el resumen del panel sale de datos reales y cambia al gestionar', function () {
    $admin = userWithRole('adminSocial');
    $id = reservaDeCliente(userWithRole('cliente'));
    reservaDeCliente(userWithRole('cliente'));

    comoUsuario($admin)->getJson('/api/reservas/resumen')->assertOk()
        ->assertJsonPath('resumen.nuevas', 2)
        ->assertJsonPath('resumen.por_contactar', 2)
        ->assertJsonPath('resumen.confirmadas', 0);

    comoUsuario($admin)->postJson("/api/reservas/{$id}/abrir")->assertOk();
    prepararParaConfirmar($admin, $id, now()->addDays(2)->toDateString());
    comoUsuario($admin)->postJson("/api/reservas/{$id}/estado", ['estado' => 'confirmada'])->assertOk();

    $resumen = comoUsuario($admin)->getJson('/api/reservas/resumen')->assertOk();
    $resumen->assertJsonPath('resumen.nuevas', 1)
        ->assertJsonPath('resumen.confirmadas', 1);

    expect($resumen->json('resumen.proximos.0.id_rese'))->toBe($id);   // la confirmada para dentro de 2 días va primero
});