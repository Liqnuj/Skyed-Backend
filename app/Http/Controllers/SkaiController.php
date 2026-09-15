<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Backend del asistente virtual SKAI.
 *
 * Migrado desde el prototipo original en PHP plano (api/asistente.php)
 * que vivía fuera de Laravel. La lógica (system prompt, historial,
 * llamada a Gemini) es la misma; lo único que cambia es que ahora
 * corre dentro de la app (validación con FormRequest-style, Http
 * facade en lugar de cURL crudo, y la API key sale del .env en vez de
 * estar escrita en el código).
 */
class SkaiController extends Controller
{
    public function responder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'context' => 'sometimes|string|in:general,social,deportivo',
            'history' => 'sometimes|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
        ]);

        // La llamada a Gemini puede reintentarse varias veces (ver más abajo);
        // le damos más margen que el límite por defecto de PHP para que un
        // 503 transitorio no termine en un error fatal por timeout.
        set_time_limit(45);

        $apiKey = config('services.gemini.key');

        if (!$apiKey) {
            // No rompemos el widget si todavía no se configuró la key;
            // devolvemos un error claro para que se note en desarrollo.
            Log::warning('SKAI: GEMINI_API_KEY no está configurada.');

            return response()->json([
                'error' => 'El asistente no está configurado todavía. Falta GEMINI_API_KEY en el servidor.',
            ], 503);
        }

        $userMessage = trim($validated['message']);
        $context = $validated['context'] ?? 'general';
        $history = $validated['history'] ?? [];

        $systemPrompt = $this->buildSystemPrompt($context);

        // Solo los últimos 10 turnos, igual que el prototipo original,
        // para no gastar tokens de más.
        $recentHistory = array_slice($history, -10);

        $contents = [];
        foreach ($recentHistory as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) $turn['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $model = config('services.gemini.model');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        try {
            // Gemini devuelve 503 "high demand" de vez en cuando bajo el
            // tier gratuito; son picos momentáneos, así que reintentamos
            // un par de veces con backoff antes de darnos por vencidos.
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
                ->timeout(12)
                ->retry(3, 500, function ($exception, $request) {
                    return $exception instanceof \Illuminate\Http\Client\RequestException
                        && in_array($exception->response->status(), [429, 503], true);
                })
                ->post($url, [
                    'contents' => $contents,
                    'systemInstruction' => [
                        'parts' => [['text' => $systemPrompt]],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => config('services.gemini.max_tokens'),
                    ],
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('SKAI: no se pudo conectar con Gemini: ' . $e->getMessage());

            return response()->json([
                'error' => 'No se pudo conectar con el asistente',
            ], 502);
        }

        $data = $response->json();

        if (!$response->ok() || !isset($data['candidates'][0]['content']['parts'])) {
            Log::warning('SKAI: respuesta inesperada de Gemini', [
                'status' => $response->status(),
                'body' => $data,
            ]);

            $status = $data['error']['status'] ?? null;
            $message = in_array($status, ['UNAVAILABLE', 'RESOURCE_EXHAUSTED'], true)
                ? 'SKAI está muy solicitado en este momento. Intenta de nuevo en unos segundos.'
                : 'La IA no respondió correctamente';

            return response()->json([
                'error' => $message,
                'detail' => $data['error']['message'] ?? 'Error desconocido',
            ], 502);
        }

        $replyText = '';
        foreach ($data['candidates'][0]['content']['parts'] as $part) {
            if (isset($part['text'])) {
                $replyText .= $part['text'];
            }
        }

        if ($replyText === '') {
            // Puede pasar si Gemini bloqueó la respuesta (finishReason: SAFETY).
            $replyText = 'No obtuve una respuesta clara, ¿puedes reformular tu pregunta?';
        }

        return response()->json(['reply' => $replyText]);
    }

    private function buildSystemPrompt(string $context): string
    {
        $fechaHoy = now('America/Bogota')->translatedFormat('l, j \d\e F \d\e Y');

        return <<<PROMPT
Te llamas SKAI, el Asistente Virtual de SKYED, una plataforma colombiana (con sede en Sogamoso, Boyacá) que reúne dos servicios bajo el mismo "universo":

1. SKYED (eventos deportivos): carreras de ciclismo, ciclomontañismo, running y otros eventos deportivos. Los usuarios pueden explorar eventos, inscribirse, ver categorías, kits del corredor/ciclista y patrocinadores.

2. SkyedSocial (eventos sociales): organización y planeación de bodas, cumpleaños, grados, baby showers y otras celebraciones. Ayuda a los usuarios a definir qué tipo de celebración quieren y qué servicios necesitan.

Hoy es: {$fechaHoy} (hora de Colombia). Úsalo para resolver preguntas sobre fechas relativas ("el próximo fin de semana", "en un mes", etc.) y para calcular cuánto falta para una fecha que el usuario mencione.

Tu trabajo principal:
- Ayudar al usuario a decidir qué tipo de evento le interesa (deportivo o social) si aún no lo sabe.
- Si busca un evento deportivo: pregunta por disciplina (ciclismo, running, etc.), nivel/categoría, fecha o ubicación aproximada, y orienta hacia la sección de inscripción.
- Si busca planear un evento social: pregunta por el tipo de celebración, número aproximado de invitados, fecha tentativa y estilo/presupuesto, y sugiere qué servicios de SkyedSocial le convendrían.

Además de tu enfoque principal:
- Eres un asistente conversacional capaz, no un formulario. Si el usuario te pregunta algo general (una operación matemática, la fecha de hoy, una duda rápida de cultura general, etc.), respóndela con normalidad y naturalidad — no la evadas ni digas que "no puedes".
- Después de responder, intenta siempre traer la conversación de vuelta a Skyed, pero hazlo de forma natural y conversacional, como lo haría una persona real charlando, no como un vendedor leyendo un guion. Varía la forma en que lo haces (a veces un comentario casual, a veces una pregunta ligera, a veces solo una idea conectada de paso) y evita repetir siempre la misma fórmula de cierre. Si el tema no tiene ninguna relación real con Skyed (como una operación matemática), que ese gesto de vuelta sea muy breve y liviano, casi de pasada, nunca una oferta larga o forzada.
- Responde siempre en español, de forma cálida, cercana y breve (2-4 frases en general; un poco más si la pregunta lo amerita, como un cálculo). Evita párrafos largos: esto es un chat.
- Si el usuario pide hablar con soporte humano o algo que realmente no puedes resolver (pagos, datos personales de su inscripción, reclamos), dile que puede usar el botón "Hablar con soporte".
- No inventes fechas de eventos, precios ni datos específicos reales que no conoces; si no tienes ese dato, dilo con honestidad y sugiere dónde buscarlo dentro de la plataforma. Esto no aplica a la fecha de hoy (arriba) ni a cálculos matemáticos, que sí puedes resolver directamente.

Contexto actual seleccionado por el usuario en la interfaz: {$context}
PROMPT;
    }
}
