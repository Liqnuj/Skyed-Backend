<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
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

        // Peor caso: el modelo principal agota su timeout y luego responde
        // el de respaldo (ver pedirAGemini). Le damos margen a PHP para que
        // eso no termine en un error fatal por tiempo máximo de ejecución.
        set_time_limit(75);

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

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'maxOutputTokens' => config('services.gemini.max_tokens'),
            ],
        ];

        $response = $this->pedirAGemini($apiKey, $payload);
        $data = $response?->json();

        if (!$response || !$response->ok() || !isset($data['candidates'][0]['content']['parts'])) {
            $status = $data['error']['status'] ?? null;

            if (!$response) {
                $message = 'No se pudo conectar con el asistente. Revisa tu conexión e intenta de nuevo.';
            } elseif (in_array($status, ['UNAVAILABLE', 'RESOURCE_EXHAUSTED'], true)) {
                $message = 'SKAI está muy solicitado en este momento. Intenta de nuevo en unos segundos.';
            } else {
                $message = 'La IA no respondió correctamente';
            }

            return response()->json(array_filter([
                'error' => $message,
                // El detalle técnico solo en desarrollo (APP_DEBUG=true).
                'detail' => config('app.debug') ? ($data['error']['message'] ?? null) : null,
            ]), 502);
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

    /**
     * Pide la respuesta al modelo principal y, si está saturado, tarda
     * demasiado o ya no existe, al modelo de respaldo. Devuelve la última
     * respuesta obtenida (aunque sea de error) o null si ningún intento
     * llegó a conectarse con Google.
     *
     * Antes había un solo intento con timeout de 12 s: los modelos 3.x
     * "piensan" antes de responder y con el prompt completo + historial
     * superaban ese límite, por eso el chat decía "No se pudo conectar"
     * y al rato (con Gemini menos cargado) volvía a funcionar.
     */
    private function pedirAGemini(string $apiKey, array $payload): ?Response
    {
        $principal = config('services.gemini.model');
        $respaldo = config('services.gemini.fallback_model');
        $timeout = max(5, (int) config('services.gemini.timeout'));
        $thinking = config('services.gemini.thinking_level');

        $conThinking = $payload;
        if ($thinking) {
            $conThinking['generationConfig']['thinkingConfig'] = ['thinkingLevel' => $thinking];
        }

        $response = $this->llamarModelo($apiKey, $principal, $conThinking, $timeout);

        // No todos los modelos aceptan cualquier nivel de "thinking"
        // (p. ej. algunos rechazan "minimal" con 400): reintentamos sin él.
        if ($thinking && $response?->status() === 400
            && str_contains(strtolower((string) $response->json('error.message')), 'thinking')) {
            $response = $this->llamarModelo($apiKey, $principal, $payload, $timeout);
        }

        $debeUsarRespaldo = !$response || in_array($response->status(), [404, 429, 500, 502, 503, 504], true);

        if ($respaldo && $respaldo !== $principal && $debeUsarRespaldo) {
            Log::info("SKAI: usando el modelo de respaldo {$respaldo}", [
                'motivo' => $response ? $response->status() : 'sin conexión / timeout',
            ]);

            $respuestaRespaldo = $this->llamarModelo($apiKey, $respaldo, $payload, min($timeout, 15));

            // Si el respaldo tampoco conectó, conservamos el error del principal.
            $response = $respuestaRespaldo ?? $response;
        }

        if ($response && !$response->ok()) {
            Log::warning('SKAI: respuesta inesperada de Gemini', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        return $response;
    }

    /**
     * Una llamada a un modelo concreto. Reintenta una vez los picos de
     * demanda (429/503); devuelve null si no hubo conexión o se agotó el
     * tiempo de espera.
     */
    private function llamarModelo(string $apiKey, string $modelo, array $payload, int $timeout): ?Response
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent";

        try {
            return Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-goog-api-key' => $apiKey,
            ])
                ->connectTimeout(5)
                ->timeout($timeout)
                // throw: false => al agotar los intentos devolvemos la última
                // respuesta (503/429) para manejarla, en vez de lanzar una
                // RequestException sin capturar (500 con traza).
                ->retry(2, 600, function ($exception) {
                    return $exception instanceof RequestException
                        && in_array($exception->response->status(), [429, 503], true);
                }, throw: false)
                ->post($url, $payload);
        } catch (ConnectionException $e) {
            Log::error("SKAI: no se pudo conectar con Gemini ({$modelo}): " . $e->getMessage());

            return null;
        }
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
- Escribe con buena ortografía en español, con todas sus tildes y la letra ñ. No uses formato Markdown (ni asteriscos ni almohadillas), porque el chat muestra el texto tal cual.
- Si el usuario pide hablar con soporte humano o algo que realmente no puedes resolver (pagos, datos personales de su inscripción, reclamos), dile que puede usar el botón "Hablar con soporte".
- No inventes fechas de eventos, precios ni datos específicos reales que no conoces; si no tienes ese dato, dilo con honestidad y sugiere dónde buscarlo dentro de la plataforma. Esto no aplica a la fecha de hoy (arriba) ni a cálculos matemáticos, que sí puedes resolver directamente.
- No menciones páginas web, dominios, apps móviles, correos ni teléfonos: el usuario ya está dentro de SKYED. Oriéntalo con las secciones reales del menú: en SKYED Deportivo "Eventos" (y el botón "Inscribirme" de cada evento) y "Nosotros"; en SkyedSocial "Eventos", "Lugares", "Reservar ahora" y "PQRS".

Contexto actual seleccionado por el usuario en la interfaz: {$context}
PROMPT;
    }
}
