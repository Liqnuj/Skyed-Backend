<?php

use App\Support\SkaiConocimiento;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.gemini.key' => 'test-api-key',
        'services.gemini.model' => 'gemini-test',
        'services.gemini.fallback_model' => '',
    ]);
});

function respuestaDeGemini(string $texto = 'Respuesta simulada de SKAI'): void
{
    Http::fake(['*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => $texto]]]]]])]);
}

it('responde que solo ayuda con SKYED a preguntas de hora, fecha y operaciones, sin consultar a la IA', function (string $pregunta) {
    respuestaDeGemini();

    $this->postJson('/api/asistente', ['message' => $pregunta, 'context' => 'general'])
        ->assertOk()
        ->assertJsonPath('reply', SkaiConocimiento::RESPUESTA_FUERA_DE_TEMA);

    Http::assertNothingSent();
})->with([
    '¿Qué hora es?',
    'que hora es',
    'Hola SKAI, ¿me puedes decir la hora?',
    '¿Qué día es hoy?',
    '¿Cuál es la fecha de hoy?',
    '¿Cuánto es 2+2?',
    '2 + 2',
    '15*3',
    'cuanto es 5 por 3',
    'Calcula (10 - 4) / 2',
    '¿cuánto da 7 más 8?',
]);

it('pasa a la IA las preguntas sobre SKYED aunque tengan horas o números', function (string $pregunta) {
    respuestaDeGemini();

    $this->postJson('/api/asistente', ['message' => $pregunta, 'context' => 'deportivo'])
        ->assertOk()
        ->assertJsonPath('reply', 'Respuesta simulada de SKAI');

    Http::assertSentCount(1);
})->with([
    '¿A qué hora es la salida del evento?',
    '¿Cuánto cuesta la inscripción?',
    '¿Cómo me inscribo a un evento?',
    '¿Cuántos días faltan para la próxima carrera?',
    'Tengo 2 invitados, ¿cómo los agrego?',
]);

it('en un módulo ofrece el botón para volver a sus eventos', function () {
    respuestaDeGemini();

    $this->postJson('/api/asistente', ['message' => '¿qué hora es?', 'context' => 'social'])
        ->assertOk()
        ->assertJsonPath('reply', SkaiConocimiento::RESPUESTA_FUERA_DE_TEMA . "\n[[Ver eventos sociales|/social/eventos]]");
});

it('le indica a la IA que solo responda sobre SKYED', function () {
    $instrucciones = SkaiConocimiento::instrucciones('general', null, [], null, SkaiConocimiento::proximosEventos());

    expect($instrucciones)
        ->toContain('ALCANCE')
        ->toContain('Solo respondes sobre SKYED')
        ->toContain(SkaiConocimiento::RESPUESTA_FUERA_DE_TEMA)
        ->not->toContain('preguntas generales');
});
