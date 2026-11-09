<?php

use App\Models\EventoDeportivo;
use App\Models\Pago;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Inscripción pendiente con su pago, de un participante. */
function pagoPendienteDe(\App\Models\User $usuario): Pago
{
    $evento = EventoDeportivo::create([
        'nombre_e' => 'Vuelta Sogamoso', 'categoria_e' => 'ruta', 'precio_e' => 60000,
        'distancia_e' => '80km', 'desnivel_e' => '900m', 'fecha_e' => now()->addMonth()->toDateString(),
        'hora_e' => '07:00', 'ubicacion_e' => 'Parque', 'descripcion_e' => 'x', 'requisitos_e' => 'x',
        'imagen_e' => 'eventos/x.jpg', 'cupos_disponibles_e' => 60, 'estado_e' => 'activo', 'creado_e' => now(),
    ]);
    $idI = DB::table('inscripcion')->insertGetId([
        'cupo_i' => 1, 'estado_i' => 'pendiente', 'fecha_i' => now(), 'precio_pagado_i' => 80000,
        'contacto_emergencia_nombre' => 'Ana', 'contacto_emergencia_telefono' => '3001234567', 'contacto_emergencia_parentesco' => 'Madre',
        'id_u' => $usuario->id_u, 'id_e' => $evento->id_e, 'created_at' => now(), 'updated_at' => now(),
    ], 'id_i');

    return Pago::create(['metodo_pago_p' => 'nequi', 'referencia_p' => 'SKYED-0001-' . $usuario->id_u, 'monto_p' => 80000, 'estado_p' => 'pendiente', 'id_i' => $idI]);
}

beforeEach(fn () => Storage::fake('local'));

it('guarda lo que se revisó del comprobante para que el admin lo vea', function () {
    $participante = userWithRole('participante', 'deportivo');
    $pago = pagoPendienteDe($participante);

    $this->actingAs($participante)->post("/api/pagos/{$pago->id_pago}/comprobante", [
        'comprobante' => UploadedFile::fake()->image('nequi.jpg', 400, 800),
        'hash_original' => str_repeat('a', 64),
        'referencia_transaccion' => 'm3164 7609',
        'verificacion' => json_encode(['qr' => true, 'qr_legible' => false, 'destino' => true, 'monto' => false, 'monto_leido' => 25000, 'monto_esperado' => 80000, 'otro' => '<script>']),
    ], ['Accept' => 'application/json'])->assertCreated();

    $pago->refresh();
    expect($pago->hash_comprobante_p)->toBe(str_repeat('a', 64))
        ->and($pago->referencia_transaccion_p)->toBe('M31647609')
        ->and($pago->verificacion_comprobante_p['destino'])->toBeTrue()
        ->and($pago->verificacion_comprobante_p['monto'])->toBeFalse()
        ->and($pago->verificacion_comprobante_p['monto_leido'])->toEqual(25000)
        ->and($pago->verificacion_comprobante_p)->not->toHaveKey('otro');
});

it('no acepta el mismo comprobante (misma imagen) en otra inscripción', function () {
    $ana = userWithRole('participante', 'deportivo');
    $luis = userWithRole('participante', 'deportivo');
    $pagoAna = pagoPendienteDe($ana);
    $pagoLuis = pagoPendienteDe($luis);
    $huella = str_repeat('b', 64);

    $this->actingAs($ana)->post("/api/pagos/{$pagoAna->id_pago}/comprobante", [
        'comprobante' => UploadedFile::fake()->image('a.jpg'), 'hash_original' => $huella,
    ], ['Accept' => 'application/json'])->assertCreated();

    // Antes de inscribirse, el navegador pregunta: ya fue usado.
    $this->actingAs($luis)->postJson('/api/pagos/comprobante/verificar', ['hash' => $huella])
        ->assertStatus(409)->assertJsonPath('usado', true);

    // Y si igual lo intenta subir, el servidor lo frena.
    $this->actingAs($luis)->post("/api/pagos/{$pagoLuis->id_pago}/comprobante", [
        'comprobante' => UploadedFile::fake()->image('a.jpg'), 'hash_original' => $huella,
    ], ['Accept' => 'application/json'])->assertStatus(409);

    expect($pagoLuis->fresh()->comprobante_p)->toBeNull();
});

it('no acepta otra captura de la misma transacción (mismo número de referencia)', function () {
    $ana = userWithRole('participante', 'deportivo');
    $luis = userWithRole('participante', 'deportivo');
    $this->actingAs($ana)->post('/api/pagos/' . pagoPendienteDe($ana)->id_pago . '/comprobante', [
        'comprobante' => UploadedFile::fake()->image('a.jpg'), 'referencia_transaccion' => 'M31647609',
    ], ['Accept' => 'application/json'])->assertCreated();

    $this->actingAs($luis)->postJson('/api/pagos/comprobante/verificar', ['hash' => str_repeat('c', 64), 'referencia_transaccion' => 'M31647609'])
        ->assertStatus(409);
});

it('un comprobante nuevo pasa la verificación', function () {
    $ana = userWithRole('participante', 'deportivo');

    $this->actingAs($ana)->postJson('/api/pagos/comprobante/verificar', ['hash' => str_repeat('d', 64), 'referencia_transaccion' => 'M99999999'])
        ->assertOk()->assertJsonPath('usado', false);
});

it('volver a subir el comprobante al mismo pago no cuenta como repetido', function () {
    $ana = userWithRole('participante', 'deportivo');
    $pago = pagoPendienteDe($ana);
    $datos = fn () => ['comprobante' => UploadedFile::fake()->image('a.jpg'), 'hash_original' => str_repeat('e', 64)];

    $this->actingAs($ana)->post("/api/pagos/{$pago->id_pago}/comprobante", $datos(), ['Accept' => 'application/json'])->assertCreated();
    $this->actingAs($ana)->post("/api/pagos/{$pago->id_pago}/comprobante", $datos(), ['Accept' => 'application/json'])->assertCreated();
});

it('la huella del archivo no se muestra en la API', function () {
    $ana = userWithRole('participante', 'deportivo');
    $pago = pagoPendienteDe($ana);
    $pago->update(['hash_comprobante_p' => str_repeat('f', 64)]);

    $this->actingAs($ana)->getJson("/api/pagos/{$pago->id_pago}")
        ->assertOk()->assertJsonMissingPath('pago.hash_comprobante_p');
});
