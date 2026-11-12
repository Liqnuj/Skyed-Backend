<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * Listar las notificaciones del usuario autenticado.
     */
public function index(Request $request)
{
    $base = $this->consulta($request);
    $noLeidas = (clone $base)->where('leida', false)->count();

    $notificaciones = $base
        ->orderByDesc('created_at')
        ->paginate($request->input('per_page', 15));

    return response()->json([
        'message' => 'Notificaciones obtenidas correctamente',
        'notificaciones' => $notificaciones,
        'no_leidas' => $noLeidas,
    ]);
}

public function marcarTodasLeidas(Request $request)
{
    $actualizadas = $this->consulta($request)
        ->where('leida', false)
        ->update(['leida' => true, 'leida_en' => now()]);

    return response()->json([
        'message' => 'Notificaciones marcadas como leídas',
        'actualizadas' => $actualizadas,
    ]);
}

    /**
     * Mostrar una notificación específica.
     */
    public function show(Request $request, $id)
    {
        $notificacion = Notificacion::where(
            'user_id',
            $request->user()->id_u
        )->find($id);

        if (!$notificacion) {
            return response()->json([
                'message' => 'Notificación no encontrada'
            ], 404);
        }

        return response()->json([
            'message' => 'Notificación obtenida correctamente',
            'notificacion' => $notificacion
        ]);
    }


    /**
     * Marcar una notificación como leída.
     */
    public function marcarLeida(Request $request, $id)
    {
        $notificacion = Notificacion::where(
            'user_id',
            $request->user()->id_u
        )->find($id);

        if (!$notificacion) {
            return response()->json([
                'message' => 'Notificación no encontrada'
            ], 404);
        }

        $notificacion->update([
            'leida' => true,
            'leida_en' => now(),
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída',
            'notificacion' => $notificacion->fresh()
        ]);
    }



private const TIPOS_DEPORTIVO = [
    'pago', 'inscripcion', 'kit', 'acceso', 'evento', 'resultado', 'recordatorio', 'pqr_deportivo',
];

private function consulta(Request $request)
{
    $q = Notificacion::where('user_id', $request->user()->id_u);

    return match ($request->input('contexto')) {
        'deportivo' => $q->whereIn('tipo', self::TIPOS_DEPORTIVO),
        'social'    => $q->whereNotIn('tipo', self::TIPOS_DEPORTIVO),
        default     => $q,
    };
}


}