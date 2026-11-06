<?php

use App\Models\EventoDeportivo;
use Illuminate\Support\Facades\DB;

function eventoParaEstadisticas(string $nombre): int
{
    return EventoDeportivo::create([
        'nombre_e' => $nombre,
        'categoria_e' => 'ruta',
        'precio_e' => 50000,
        'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00',
        'ubicacion_e' => 'Tunja',
        'descripcion_e' => 'Recorrido',
        'requisitos_e' => 'Casco',
        'imagen_e' => 'eventos/e.jpg',
        'cupos_disponibles_e' => 50,
        'estado_e' => 'activo',
        'creado_e' => now(),
    ])->id_e;
}

/** Una inscripción con su pago. Devuelve el id de la inscripción. */
function inscripcionConPago(int $idE, string $estadoInscripcion, ?string $estadoPago, float $monto, bool $conInvitado = false, array $extra = []): int
{
    $usuario = userWithRole('participante', 'deportivo');
    $idInv = $conInvitado ? DB::table('invitados')->insertGetId([
        'tipo_documento' => 'CC', 'documento_inv' => random_int(10000000, 99999999), 'nombre_inv' => 'Invitado',
        'apellido_inv' => 'Prueba', 'rh_inv' => 'O+', 'fecha_nacimiento_inv' => '2000-01-01', 'telefono_inv' => (string) random_int(3000000000, 3999999999),
        'correo_inv' => uniqid() . '@correo.com', 'desea_kit_inv' => $extra['kit_invitado'] ?? false, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_inv') : null;
    unset($extra['kit_invitado']);

    $idI = DB::table('inscripcion')->insertGetId([
        'cupo_i' => $conInvitado ? 2 : 1, 'estado_i' => $estadoInscripcion, 'fecha_i' => now(), 'precio_pagado_i' => $monto,
        'contacto_emergencia_nombre' => 'Ana', 'contacto_emergencia_telefono' => '3001234567', 'contacto_emergencia_parentesco' => 'Madre',
        'id_u' => $usuario->id_u, 'id_e' => $idE, 'id_inv' => $idInv, 'created_at' => now(), 'updated_at' => now(),
    ] + $extra, 'id_i');

    if ($estadoPago) {
        DB::table('pago')->insert([
            'metodo_pago_p' => 'nequi', 'monto_p' => $monto, 'fecha_p' => now(), 'estado_p' => $estadoPago,
            'id_i' => $idI, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $idI;
}

it('suma inscritos y dinero recaudado por evento deportivo', function () {
    $gran = eventoParaEstadisticas('Gran Fondo');
    $otro = eventoParaEstadisticas('Contrarreloj');

    inscripcionConPago($gran, 'confirmada', 'confirmado', 50000);
    inscripcionConPago($gran, 'confirmada', 'confirmado', 95000, conInvitado: true);
    inscripcionConPago($gran, 'pendiente', 'pendiente', 50000);
    inscripcionConPago($gran, 'cancelada', 'rechazado', 50000); // no cuenta
    inscripcionConPago($otro, 'pendiente', 'pendiente', 40000);

    $datos = $this->actingAs(userWithRole('adminDeportivo', 'deportivo'), 'sanctum')
        ->getJson('/api/estadisticas/eventos-deportivos')
        ->assertOk()
        ->json('data');

    expect($datos[$gran])->toMatchArray([
        'inscritos' => 3, 'confirmadas' => 2, 'pendientes' => 1, 'invitados' => 1,
        'recaudado' => 145000, 'por_validar' => 50000, 'cupos_ocupados' => 4,
    ])->and($datos[$otro])->toMatchArray(['inscritos' => 1, 'recaudado' => 0, 'por_validar' => 40000]);
});

it('separa el dinero, cuenta la asistencia con QR y los kits entregados', function () {
    $idE = eventoParaEstadisticas('Vuelta al Lago');
    $uno = inscripcionConPago($idE, 'confirmada', 'confirmado', 95000, extra: ['desea_kit_i' => true, 'precio_inscripcion_i' => 50000, 'precio_kit_i' => 45000]);
    $dos = inscripcionConPago($idE, 'confirmada', 'confirmado', 190000, conInvitado: true, extra: [
        'desea_kit_i' => true, 'kit_invitado' => true,
        'precio_inscripcion_i' => 50000, 'precio_kit_i' => 45000, 'precio_invitado_i' => 50000, 'precio_kit_invitado_i' => 45000,
    ]);
    inscripcionConPago($idE, 'confirmada', 'confirmado', 50000); // sin desglose (inscripción antigua): todo cuenta como inscripción

    // Entraron 2 personas por la inscripción con invitado y nadie por las otras.
    DB::table('qr_entrada')->insert(['codigo_qr' => 'QR-1', 'estado_qr' => 'usado', 'usos_permitidos_qr' => 2, 'usos_realizados_qr' => 2, 'id_i' => $dos, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('qr_entrada')->insert(['codigo_qr' => 'QR-2', 'estado_qr' => 'activo', 'usos_permitidos_qr' => 1, 'usos_realizados_qr' => 0, 'id_i' => $uno, 'created_at' => now(), 'updated_at' => now()]);
    // Un kit entregado (días antes, desde Entregas kits).
    $idU = DB::table('inscripcion')->where('id_i', $uno)->value('id_u');
    $idK = DB::table('kit')->insertGetId(['nombre_k' => 'Kit', 'stock_k' => 10, 'precio_k' => 45000, 'fecha_entrega_k' => now()->toDateString(), 'lugar_entrega_k' => 'Tunja', 'contenido_k' => 'Jersey', 'imagen_k' => 'kits/k.jpg', 'created_at' => now(), 'updated_at' => now()], 'id_k');
    DB::table('entrega_kit')->insert(['estado_ek' => 'entregado', 'fecha_entrega_real_ek' => now(), 'id_k' => $idK, 'id_u' => $idU, 'id_e' => $idE, 'id_i' => $uno, 'created_at' => now(), 'updated_at' => now()]);

    $datos = $this->actingAs(userWithRole('adminDeportivo', 'deportivo'), 'sanctum')
        ->getJson('/api/estadisticas/eventos-deportivos')
        ->assertOk()
        ->json("data.{$idE}");

    expect($datos['recaudado'])->toEqual(335000)
        ->and($datos['desglose'])->toEqual(['inscripciones' => 150000, 'kits' => 135000, 'invitados' => 50000])
        ->and($datos['asistencia'])->toEqual(['esperados' => 4, 'asistieron' => 2])
        ->and($datos['kits'])->toEqual(['pedidos' => 3, 'entregados' => 1]);
});

it('escanear el QR ya no ofrece entregar kits', function () {
    $idE = eventoParaEstadisticas('Contrarreloj');
    $idI = inscripcionConPago($idE, 'confirmada', 'confirmado', 50000, extra: ['desea_kit_i' => true]);
    DB::table('qr_entrada')->insert(['codigo_qr' => 'QR-ENTRADA', 'estado_qr' => 'activo', 'usos_permitidos_qr' => 1, 'usos_realizados_qr' => 0, 'id_i' => $idI, 'created_at' => now(), 'updated_at' => now()]);

    $acceso = $this->actingAs(userWithRole('adminDeportivo', 'deportivo'), 'sanctum')
        ->postJson('/api/qr/validar', ['codigo_qr' => 'QR-ENTRADA'])
        ->assertOk()
        ->json('acceso');

    expect($acceso)->not->toHaveKey('kits')
        ->and($acceso['ingreso_numero'])->toBe(1);
});

it('suma reservas e ingresos por evento social', function () {
    $cliente = userWithRole('cliente', 'social');
    $idTipo = DB::table('tipo_evento')->insertGetId(['nombre_tipo_eves' => 'boda', 'created_at' => now(), 'updated_at' => now()], 'id_tipo_eves');
    $idEr = DB::table('evento_realizado')->insertGetId([
        'nombre_er' => 'Boda en el jardín', 'descripcion_er' => 'Evento', 'fecha_er' => now()->addMonth()->toDateString(),
        'id_tipo_eves' => $idTipo, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_er');

    $reserva = fn (string $estado, ?float $total, int $invitados) => DB::table('reserva')->insert([
        'fecha_evento_rese' => now()->addMonth()->toDateString(), 'invitados_rese' => $invitados, 'total_rese' => $total, 'presupuesto_rese' => 0, 'ubicacion_rese' => 'Tunja', 'Observaciones_rese' => 'Ninguna',
        'estado_rese' => $estado, 'creado_en_rese' => now(), 'id_u' => $cliente->id_u, 'id_er' => $idEr,
        'id_tipo_eves' => $idTipo, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $reserva('confirmada', 3000000, 100);
    $reserva('completada', 2000000, 80);
    $reserva('pendiente', 1500000, 50);
    $reserva('cancelada', 9000000, 200); // no cuenta

    $datos = $this->actingAs(userWithRole('adminSocial', 'social'), 'sanctum')
        ->getJson('/api/estadisticas/eventos-sociales')
        ->assertOk()
        ->json('data');

    expect($datos[$idEr])->toMatchArray([
        'reservas' => 3, 'confirmadas' => 2, 'pendientes' => 1, 'invitados' => 230,
        'ingresos' => 5000000, 'por_confirmar' => 1500000, 'promedio' => 2500000,
    ]);
});

it('muestra los servicios más pedidos en las reservas de un evento social', function () {
    $cliente = userWithRole('cliente', 'social');
    $idTipo = DB::table('tipo_evento')->insertGetId(['nombre_tipo_eves' => 'boda', 'created_at' => now(), 'updated_at' => now()], 'id_tipo_eves');
    $idEr = DB::table('evento_realizado')->insertGetId([
        'nombre_er' => 'Boda', 'descripcion_er' => 'Evento', 'fecha_er' => now()->addMonth()->toDateString(),
        'id_tipo_eves' => $idTipo, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_er');
    $musica = DB::table('servicio')->insertGetId(['nombre_s' => 'Música en vivo', 'descripcion_s' => 'Banda', 'created_at' => now(), 'updated_at' => now()], 'id_s');
    $catering = DB::table('servicio')->insertGetId(['nombre_s' => 'Catering', 'descripcion_s' => 'Comida', 'created_at' => now(), 'updated_at' => now()], 'id_s');

    $reserva = fn (string $estado) => DB::table('reserva')->insertGetId([
        'fecha_evento_rese' => now()->addMonth()->toDateString(), 'invitados_rese' => 50, 'total_rese' => 1000000, 'presupuesto_rese' => 0,
        'ubicacion_rese' => 'Tunja', 'Observaciones_rese' => 'Ninguna', 'estado_rese' => $estado, 'creado_en_rese' => now(),
        'id_u' => $cliente->id_u, 'id_er' => $idEr, 'id_tipo_eves' => $idTipo, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_rese');
    $servicio = fn (int $idRese, int $idS, string $nombre) => DB::table('reserva_servicio')->insert([
        'id_rese' => $idRese, 'id_s' => $idS, 'nombre_servicio' => $nombre, 'cantidad' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $a = $reserva('confirmada'); $b = $reserva('pendiente'); $c = $reserva('cancelada');
    $servicio($a, $catering, 'Catering'); $servicio($b, $catering, 'Catering');
    $servicio($a, $musica, 'Música en vivo');
    $servicio($c, $musica, 'Música en vivo'); $servicio($c, $musica, 'Música en vivo'); // cancelada: no cuenta

    $top = $this->actingAs(userWithRole('adminSocial', 'social'), 'sanctum')
        ->getJson('/api/estadisticas/eventos-sociales')
        ->assertOk()
        ->json("data.{$idEr}.servicios_top");

    expect($top)->toBe([['nombre' => 'Catering', 'reservas' => 2], ['nombre' => 'Música en vivo', 'reservas' => 1]]);
});

it('la lista pública de eventos sociales no muestra reservas ni datos del creador', function () {
    $admin = userWithRole('adminSocial', 'social');
    $idTipo = DB::table('tipo_evento')->insertGetId(['nombre_tipo_eves' => 'boda', 'created_at' => now(), 'updated_at' => now()], 'id_tipo_eves');
    $idEr = DB::table('evento_realizado')->insertGetId([
        'nombre_er' => 'Boda', 'descripcion_er' => 'Evento', 'fecha_er' => now()->addMonth()->toDateString(),
        'id_tipo_eves' => $idTipo, 'id_u' => $admin->id_u, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_er');
    DB::table('reserva')->insert([
        'fecha_evento_rese' => now()->addMonth()->toDateString(), 'invitados_rese' => 50, 'total_rese' => 1000000,
        'presupuesto_rese' => 0, 'ubicacion_rese' => 'Calle privada 123', 'Observaciones_rese' => 'Dato privado',
        'estado_rese' => 'pendiente', 'creado_en_rese' => now(), 'id_u' => $admin->id_u, 'id_er' => $idEr,
        'id_tipo_eves' => $idTipo, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $publico = $this->getJson('/api/eventos-sociales')->assertOk();
    $detalle = $this->getJson("/api/eventos-sociales/{$idEr}")->assertOk();

    expect($publico->json('data.0'))->not->toHaveKeys(['reservas', 'creador'])
        ->and($detalle->json('evento'))->not->toHaveKeys(['reservas', 'creador'])
        ->and($publico->getContent())->not->toContain('Calle privada 123')
        ->and($publico->getContent())->not->toContain($admin->correo_u);
});

it('las cifras de dinero solo las ve el administrador de cada módulo', function () {
    $this->actingAs(userWithRole('participante', 'deportivo'), 'sanctum')->getJson('/api/estadisticas/eventos-deportivos')->assertForbidden();
    $this->actingAs(userWithRole('adminSocial', 'social'), 'sanctum')->getJson('/api/estadisticas/eventos-deportivos')->assertForbidden();
    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'), 'sanctum')->getJson('/api/estadisticas/eventos-sociales')->assertForbidden();
});

it('sin inscripciones responde un mapa vacío', function () {
    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'), 'sanctum')
        ->getJson('/api/estadisticas/eventos-deportivos')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});
