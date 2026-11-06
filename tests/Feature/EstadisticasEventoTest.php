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

/** Una inscripción con su pago. */
function inscripcionConPago(int $idE, string $estadoInscripcion, ?string $estadoPago, float $monto, bool $conInvitado = false): void
{
    $usuario = userWithRole('participante', 'deportivo');
    $idInv = $conInvitado ? DB::table('invitados')->insertGetId([
        'tipo_documento' => 'CC', 'documento_inv' => random_int(10000000, 99999999), 'nombre_inv' => 'Invitado',
        'apellido_inv' => 'Prueba', 'rh_inv' => 'O+', 'fecha_nacimiento_inv' => '2000-01-01', 'telefono_inv' => (string) random_int(3000000000, 3999999999),
        'correo_inv' => uniqid() . '@correo.com', 'created_at' => now(), 'updated_at' => now(),
    ], 'id_inv') : null;

    $idI = DB::table('inscripcion')->insertGetId([
        'cupo_i' => $conInvitado ? 2 : 1, 'estado_i' => $estadoInscripcion, 'fecha_i' => now(), 'precio_pagado_i' => $monto,
        'contacto_emergencia_nombre' => 'Ana', 'contacto_emergencia_telefono' => '3001234567', 'contacto_emergencia_parentesco' => 'Madre',
        'id_u' => $usuario->id_u, 'id_e' => $idE, 'id_inv' => $idInv, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_i');

    if ($estadoPago) {
        DB::table('pago')->insert([
            'metodo_pago_p' => 'nequi', 'monto_p' => $monto, 'fecha_p' => now(), 'estado_p' => $estadoPago,
            'id_i' => $idI, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
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
        'recaudado' => 145000, 'por_validar' => 50000,
    ])->and($datos[$otro])->toMatchArray(['inscritos' => 1, 'recaudado' => 0, 'por_validar' => 40000]);
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
        'ingresos' => 5000000, 'por_confirmar' => 1500000,
    ]);
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
