<?php

use App\Models\EntregaKit;
use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Kit;
use App\Models\QrEntrada;
use Illuminate\Support\Facades\DB;

/** Evento activo con un kit que tiene tallas en inventario. */
function eventoConKit(): EventoDeportivo
{
    $kit = Kit::create([
        'nombre_k' => 'Kit del Ciclista', 'stock_k' => 20, 'fecha_entrega_k' => now()->addWeek()->toDateString(),
        'lugar_entrega_k' => 'Sogamoso', 'contenido_k' => 'Jersey y medias', 'precio_k' => 20000,
    ]);
    foreach (['S', 'M', 'L'] as $t) {
        DB::table('kit_talla')->insert(['id_k' => $kit->id_k, 'talla_kt' => $t, 'cantidad_kt' => 5, 'created_at' => now(), 'updated_at' => now()]);
    }

    return EventoDeportivo::create([
        'nombre_e' => 'Vuelta Sogamoso', 'categoria_e' => 'ruta', 'precio_e' => 60000,
        'fecha_e' => now()->addMonth()->toDateString(), 'hora_e' => '07:00', 'ubicacion_e' => 'Parque',
        'descripcion_e' => 'x', 'requisitos_e' => 'x', 'imagen_e' => 'eventos/x.jpg',
        'cupos_disponibles_e' => 50, 'estado_e' => 'activo', 'creado_e' => now(), 'id_k' => $kit->id_k,
    ]);
}

function inscribir($test, $usuario, EventoDeportivo $evento, array $extra = [])
{
    return $test->actingAs($usuario)->postJson("/api/eventos/{$evento->id_e}/inscripciones", array_merge([
        'cupo_i' => 1, 'precio_pagado_i' => 80000,
        'contacto_emergencia_nombre' => 'María Pérez', 'contacto_emergencia_telefono' => '3001234567',
        'contacto_emergencia_parentesco' => 'Madre', 'metodo_pago_p' => 'transferencia',
    ], $extra))->assertCreated();
}

function confirmar(int $idInscripcion): void
{
    Inscripcion::where('id_i', $idInscripcion)->update(['estado_i' => 'confirmada']);
}

it('quien pide kit recibe un segundo QR (KIT-…) y quien no, solo el de entrada', function () {
    $evento = eventoConKit();

    $conKit = inscribir($this, userWithRole('participante'), $evento, ['desea_jersey' => true, 'talla_kit' => 'M']);
    $sinKit = inscribir($this, userWithRole('participante'), $evento, ['desea_jersey' => false]);

    $qrCon = QrEntrada::where('id_i', $conKit->json('inscripcion.id'))->first();
    $qrSin = QrEntrada::where('id_i', $sinKit->json('inscripcion.id'))->first();

    expect($qrCon->codigo_kit_qr)->toStartWith('KIT-')
        ->and($qrCon->codigo_kit_qr)->not->toBe($qrCon->codigo_qr)
        ->and($qrSin->codigo_kit_qr)->toBeNull();
});

it('el participante ve su QR del kit en "mis inscripciones"', function () {
    $evento = eventoConKit();
    $ana = userWithRole('participante');
    inscribir($this, $ana, $evento, ['desea_jersey' => true, 'talla_kit' => 'S']);

    $this->actingAs($ana)->getJson('/api/mis-inscripciones')
        ->assertOk()
        ->assertJsonPath('inscripciones.0.qr.codigo_kit', fn ($c) => str_starts_with($c, 'KIT-'));
});

it('al escanear el QR del kit se ven las tallas y se entregan los kits del titular y del invitado', function () {
    $evento = eventoConKit();
    $ana = userWithRole('participante');
    $res = inscribir($this, $ana, $evento, [
        'desea_jersey' => true, 'talla_kit' => 'M',
        'invitado' => [
            'tipo_documento' => 'CC', 'documento_inv' => 1055667788, 'nombre_inv' => 'Luis', 'apellido_inv' => 'Rojas',
            'rh_inv' => 'O+', 'telefono_inv' => '3201234567', 'fecha_nacimiento_inv' => '2000-05-05',
            'desea_kit' => true, 'talla_kit' => 'L',
        ],
    ]);
    $id = $res->json('inscripcion.id');
    confirmar($id);
    $codigo = QrEntrada::where('id_i', $id)->value('codigo_kit_qr');
    $staff = userWithRole('adminDeportivo', 'deportivo');

    // Consultar: no cambia nada, muestra las dos tallas pendientes.
    $this->actingAs($staff)->postJson('/api/qr/kit/consultar', ['codigo_kit' => $codigo])
        ->assertOk()
        ->assertJsonPath('pendientes', 2)
        ->assertJsonPath('kits.0.rol', 'titular')
        ->assertJsonPath('kits.0.talla', 'M')
        ->assertJsonPath('kits.1.rol', 'invitado')
        ->assertJsonPath('kits.1.talla', 'L');

    // Entregar: los dos quedan entregados y el participante recibe aviso.
    $this->actingAs($staff)->postJson('/api/qr/kit/entregar', ['codigo_kit' => strtoupper($codigo)])
        ->assertOk()
        ->assertJsonPath('entregados', 2)
        ->assertJsonPath('pendientes', 0);

    expect(EntregaKit::where('id_i', $id)->where('estado_ek', 'entregado')->count())->toBe(2)
        ->and(\App\Models\Notificacion::where('user_id', $ana->id_u)->where('titulo', 'Kit entregado')->exists())->toBeTrue();

    // Escanearlo otra vez no entrega nada más.
    $this->actingAs($staff)->postJson('/api/qr/kit/entregar', ['codigo_kit' => $codigo])->assertStatus(409);
});

it('no entrega el kit si el pago de la inscripción no está confirmado', function () {
    $evento = eventoConKit();
    $res = inscribir($this, userWithRole('participante'), $evento, ['desea_jersey' => true, 'talla_kit' => 'M']);
    $codigo = QrEntrada::where('id_i', $res->json('inscripcion.id'))->value('codigo_kit_qr');

    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'))
        ->postJson('/api/qr/kit/entregar', ['codigo_kit' => $codigo])
        ->assertStatus(422);

    expect(EntregaKit::where('estado_ek', 'entregado')->count())->toBe(0);
});

it('avisa si escanean el QR equivocado (entrada en kits y kit en la entrada)', function () {
    $evento = eventoConKit();
    $res = inscribir($this, userWithRole('participante'), $evento, ['desea_jersey' => true, 'talla_kit' => 'M']);
    confirmar($res->json('inscripcion.id'));
    $qr = QrEntrada::where('id_i', $res->json('inscripcion.id'))->first();
    $staff = userWithRole('adminDeportivo', 'deportivo');

    $this->actingAs($staff)->postJson('/api/qr/kit/consultar', ['codigo_kit' => $qr->codigo_qr])
        ->assertStatus(422)->assertJsonPath('tipo', 'entrada');

    $this->actingAs($staff)->postJson('/api/qr/validar', ['codigo_qr' => $qr->codigo_kit_qr])
        ->assertStatus(422)->assertJsonPath('tipo', 'kit');

    // El QR de entrada no se gastó por el intento.
    expect($qr->fresh()->usos_realizados_qr)->toBe(0);
});

it('un participante no puede entregarse el kit a sí mismo', function () {
    $evento = eventoConKit();
    $ana = userWithRole('participante');
    $res = inscribir($this, $ana, $evento, ['desea_jersey' => true, 'talla_kit' => 'M']);
    confirmar($res->json('inscripcion.id'));
    $codigo = QrEntrada::where('id_i', $res->json('inscripcion.id'))->value('codigo_kit_qr');

    $this->actingAs($ana)->postJson('/api/qr/kit/entregar', ['codigo_kit' => $codigo])->assertStatus(403);
});

it('entregar un kit desde la tabla de Entregas kits ya no responde con error 500', function () {
    $evento = eventoConKit();
    $ana = userWithRole('participante');
    $res = inscribir($this, $ana, $evento, ['desea_jersey' => true, 'talla_kit' => 'M']);
    confirmar($res->json('inscripcion.id'));

    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'))
        ->postJson('/api/entregas-kit', ['id_i' => $res->json('inscripcion.id')])
        ->assertCreated();

    expect(\App\Models\Notificacion::where('user_id', $ana->id_u)->where('titulo', 'Kit entregado')->exists())->toBeTrue();
});
