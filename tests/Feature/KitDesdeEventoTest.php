<?php

use App\Models\EventoDeportivo;
use App\Models\Kit;

function datosKitNuevo(array $extra = []): array
{
    return array_merge([
        'nombre_k' => 'Kit Gran Fondo',
        'precio_k' => 45000,
        'fecha_entrega_k' => now()->addMonth()->toDateString(),
        'lugar_entrega_k' => 'Parque principal',
        'contenido_k' => 'Jersey y número',
        'imagen_k' => 'kits/kit.jpg',
        'tallas' => [['talla' => 'M', 'cantidad' => 10]],
    ], $extra);
}

it('crea un kit sin evento desde el formulario del evento y el evento lo toma al guardarse', function () {
    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'));

    $kit = $this->postJson('/api/kits', datosKitNuevo())
        ->assertCreated()
        ->json('kit');

    expect(Kit::find($kit['id_k']))->not->toBeNull()
        ->and($kit['eventos'])->toBe([]);

    $evento = $this->postJson('/api/eventos', datosEvento(['id_k' => $kit['id_k']]))
        ->assertCreated()
        ->json('evento');

    expect(EventoDeportivo::find($evento['id'])->id_k)->toBe($kit['id_k']);
    // En la sección Kits aparece asignado a ese evento.
    $this->getJson('/api/kits')
        ->assertOk()
        ->assertJsonPath('data.0.eventos.0.id_e', $evento['id']);
});

it('desde la sección Kits sigue pudiendo asignarse a un evento al crearlo', function () {
    $this->actingAs(userWithRole('adminDeportivo', 'deportivo'));
    $evento = EventoDeportivo::create(datosEvento(['estado_e' => 'activo', 'creado_e' => now()]));

    $kit = $this->postJson('/api/kits', datosKitNuevo(['id_e' => $evento->id_e]))->assertCreated()->json('kit');

    expect($evento->fresh()->id_k)->toBe($kit['id_k']);
});
