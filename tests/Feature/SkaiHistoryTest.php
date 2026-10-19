
<?php

use Illuminate\Support\Facades\Http;

it('conserva los ultimos 10 turnos del historial de SKAI', function () {
    // Configuracion de prueba: no se usa la API real de Gemini.
    config([
        'services.gemini.key' => 'test-api-key',
        'services.gemini.model' => 'gemini-test',
        'services.gemini.max_tokens' => 100,
    ]);

    $contenidoEnviado = [];

    // Simulamos una respuesta exitosa de Gemini.
    Http::fake(function ($request) use (&$contenidoEnviado) {
        $contenidoEnviado = $request->data()['contents'];

        return Http::response([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Respuesta simulada de SKAI'],
                        ],
                    ],
                ],
            ],
        ], 200);
    });

    // Creamos 15 turnos identificables.
    $history = [];

    for ($i = 1; $i <= 15; $i++) {
        $history[] = [
            'role' => 'user',
            'content' => sprintf('turno-%02d', $i),
        ];
    }

    // Enviamos la solicitud al endpoint real de Laravel.
    $response = $this->postJson('/api/asistente', [
        'message' => 'Mensaje actual de prueba',
        'context' => 'general',
        'history' => $history,
    ]);

    // La API debe responder correctamente.
    $response->assertOk()
        ->assertJson([
            'reply' => 'Respuesta simulada de SKAI',
        ]);

    // Deben conservarse los últimos 10 turnos.
    expect(array_slice($contenidoEnviado, 0, 10))
        ->toHaveCount(10);

    // El primero conservado debe ser el turno 6.
    expect($contenidoEnviado[0]['parts'][0]['text'])
        ->toBe('turno-06');

    // El último turno del historial debe ser el 15.
    expect($contenidoEnviado[9]['parts'][0]['text'])
        ->toBe('turno-15');

    // El mensaje actual se agrega después del historial.
    expect($contenidoEnviado[10]['parts'][0]['text'])
        ->toBe('Mensaje actual de prueba');

    expect($contenidoEnviado)->toHaveCount(11);
});