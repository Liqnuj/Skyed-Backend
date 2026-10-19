<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// Respuesta mínima válida de Gemini.
function respuestaGemini(string $texto): array
{
    return ['candidates' => [['content' => ['parts' => [['text' => $texto]]]]]];
}

function errorGemini(int $codigo, string $status, string $mensaje = 'error'): array
{
    return ['error' => ['code' => $codigo, 'status' => $status, 'message' => $mensaje]];
}

beforeEach(function () {
    // Sin esperas reales entre reintentos.
    Sleep::fake();

    config([
        'services.gemini.key' => 'test-api-key',
        'services.gemini.model' => 'modelo-principal',
        'services.gemini.fallback_model' => 'modelo-respaldo',
        'services.gemini.thinking_level' => 'minimal',
        'services.gemini.max_tokens' => 100,
        'services.gemini.timeout' => 25,
    ]);
});

it('envia el nivel de thinking configurado al modelo principal', function () {
    Http::fake(['*modelo-principal:generateContent' => Http::response(respuestaGemini('ok'))]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertOk()
        ->assertJson(['reply' => 'ok']);

    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'modelo-principal')
        && $r['generationConfig']['thinkingConfig']['thinkingLevel'] === 'minimal');
});

it('usa el modelo de respaldo cuando el principal sigue saturado', function () {
    Http::fake([
        '*modelo-principal:generateContent' => Http::response(errorGemini(503, 'UNAVAILABLE'), 503),
        '*modelo-respaldo:generateContent' => Http::response(respuestaGemini('desde el respaldo')),
    ]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertOk()
        ->assertJson(['reply' => 'desde el respaldo']);

    // El respaldo no recibe thinkingConfig (no todos los modelos lo aceptan).
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'modelo-respaldo')
        && !isset($r['generationConfig']['thinkingConfig']));
});

it('usa el modelo de respaldo cuando el principal no responde a tiempo', function () {
    Http::fake([
        '*modelo-principal:generateContent' => Http::failedConnection('cURL error 28: Operation timed out'),
        '*modelo-respaldo:generateContent' => Http::response(respuestaGemini('a tiempo')),
    ]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertOk()
        ->assertJson(['reply' => 'a tiempo']);
});

it('reintenta sin thinking si el modelo no acepta ese nivel', function () {
    Http::fake([
        '*modelo-principal:generateContent' => Http::sequence()
            ->push(errorGemini(400, 'INVALID_ARGUMENT', 'Thinking level MINIMAL is not supported for this model.'), 400)
            ->push(respuestaGemini('sin thinking')),
    ]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertOk()
        ->assertJson(['reply' => 'sin thinking']);
});

it('responde 502 con un mensaje claro si ningun modelo esta disponible', function () {
    Http::fake([
        '*' => Http::response(errorGemini(503, 'UNAVAILABLE'), 503),
    ]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertStatus(502)
        ->assertJson(['error' => 'SKAI está muy solicitado en este momento. Intenta de nuevo en unos segundos.']);
});

it('responde 502 sin traza si no hay conexion con Google', function () {
    Http::fake(['*' => Http::failedConnection()]);

    $this->postJson('/api/asistente', ['message' => 'hola'])
        ->assertStatus(502)
        ->assertJsonMissingPath('exception')
        ->assertJsonPath('error', 'No se pudo conectar con el asistente. Revisa tu conexión e intenta de nuevo.');
});
