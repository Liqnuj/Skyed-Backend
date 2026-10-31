<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Testimonio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Testimonios de SkyedSocial.
 *
 * - Lectura pública: cualquiera ve las reseñas publicadas en la página.
 * - Escribir: solo quien tiene una reserva COMPLETADA, una reseña por
 *   reserva, sin aprobación previa (se publica al instante).
 * - Editar / borrar: solo la persona que la escribió.
 */
class TestimonioController extends Controller
{
    private const POR_DEFECTO = 9;
    private const MAXIMO_PUBLICO = 12;
    private const MIN_COMENTARIO = 20;

    /** GET /testimonios — público. Las más recientes primero. */
    public function index(Request $request): JsonResponse
    {
        $limite = max(1, min((int) $request->input('limite', self::POR_DEFECTO), self::MAXIMO_PUBLICO));

        $testimonios = Testimonio::with(['usuario', 'reserva.evento', 'reserva.tipoEvento'])
            ->publicados()
            ->orderByDesc('created_at')
            ->orderByDesc('id_tes')
            ->limit($limite)
            ->get();

        return response()->json([
            'data' => $testimonios->map(fn (Testimonio $t) => $t->paraPublico())->values(),
            'total' => Testimonio::publicados()->count(),
        ]);
    }

    /** GET /reservas/{id}/testimonio — la reseña que dejé en esta reserva (o null). */
    public function mio(Request $request, $id): JsonResponse
    {
        $reserva = $this->reservaPropia($request, $id);
        if (!$reserva) {
            return $this->reservaNoEncontrada();
        }

        $testimonio = Testimonio::where('id_rese', $reserva->id_rese)->first();

        return response()->json([
            'data' => $testimonio?->paraDueno(),
        ]);
    }

    /** POST /reservas/{id}/testimonio */
    public function store(Request $request, $id): JsonResponse
    {
        $reserva = $this->reservaPropia($request, $id);
        if (!$reserva) {
            return $this->reservaNoEncontrada();
        }

        if ($reserva->estado_rese !== 'completada') {
            return response()->json([
                'message' => 'Podrás dejar tu reseña cuando tu evento esté completado.',
            ], 422);
        }

        if (Testimonio::where('id_rese', $reserva->id_rese)->exists()) {
            return response()->json([
                'message' => 'Ya dejaste una reseña para esta reserva. Puedes editarla.',
            ], 409);
        }

        $datos = $this->validar($request);
        if ($datos instanceof JsonResponse) {
            return $datos;
        }

        $testimonio = Testimonio::create([
            'id_u' => $request->user()->id_u,
            'id_rese' => $reserva->id_rese,
            'calificacion_tes' => $datos['calificacion_tes'],
            'comentario_tes' => $datos['comentario_tes'],
            'estado_tes' => Testimonio::PUBLICADO,
        ]);

        return response()->json([
            'message' => '¡Gracias por tu reseña! Ya está publicada.',
            'data' => $testimonio->paraDueno(),
        ], 201);
    }

    /** PUT /testimonios/{id} — solo la persona que la escribió. */
    public function update(Request $request, $id): JsonResponse
    {
        $testimonio = $this->testimonioPropio($request, $id);
        if (!$testimonio) {
            return $this->noEncontrado();
        }

        $datos = $this->validar($request);
        if ($datos instanceof JsonResponse) {
            return $datos;
        }

        $testimonio->update([
            'calificacion_tes' => $datos['calificacion_tes'],
            'comentario_tes' => $datos['comentario_tes'],
        ]);

        return response()->json([
            'message' => 'Tu reseña se actualizó.',
            'data' => $testimonio->fresh()->paraDueno(),
        ]);
    }

    /** DELETE /testimonios/{id} — solo la persona que la escribió. */
    public function destroy(Request $request, $id): JsonResponse
    {
        $testimonio = $this->testimonioPropio($request, $id);
        if (!$testimonio) {
            return $this->noEncontrado();
        }

        $testimonio->delete();

        return response()->json(['message' => 'Tu reseña se eliminó.']);
    }

    /**
     * Valida y limpia el cuerpo. Devuelve los datos o, si el texto queda
     * demasiado corto una vez quitado el HTML, una respuesta 422.
     */
    private function validar(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'calificacion_tes' => 'required|integer|between:1,5',
            'comentario_tes' => 'required|string|max:500',
        ], [
            'calificacion_tes.required' => 'Elige una calificación de 1 a 5 estrellas.',
            'calificacion_tes.between' => 'La calificación debe estar entre 1 y 5 estrellas.',
            'comentario_tes.required' => 'Cuéntanos cómo fue tu evento.',
            'comentario_tes.max' => 'Tu reseña puede tener hasta 500 caracteres.',
        ]);

        // El texto se muestra tal cual en la página pública: sin etiquetas HTML.
        $comentario = trim(preg_replace('/\s+/u', ' ', strip_tags($validated['comentario_tes'])));

        if (mb_strlen($comentario) < self::MIN_COMENTARIO) {
            return response()->json([
                'message' => 'Cuéntanos un poco más (mínimo ' . self::MIN_COMENTARIO . ' caracteres).',
            ], 422);
        }

        return [
            'calificacion_tes' => (int) $validated['calificacion_tes'],
            'comentario_tes' => $comentario,
        ];
    }

    private function reservaPropia(Request $request, $id): ?Reserva
    {
        return Reserva::where('id_rese', $id)
            ->where('id_u', $request->user()->id_u)
            ->first();
    }

    private function testimonioPropio(Request $request, $id): ?Testimonio
    {
        return Testimonio::where('id_tes', $id)
            ->where('id_u', $request->user()->id_u)
            ->first();
    }

    private function reservaNoEncontrada(): JsonResponse
    {
        return response()->json(['message' => 'Reserva no encontrada'], 404);
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json(['message' => 'Reseña no encontrada'], 404);
    }
}
