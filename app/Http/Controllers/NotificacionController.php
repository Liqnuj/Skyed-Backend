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
    $userId = $request->user()->id_u;
    $porPagina = min(50, max(1, (int) $request->input('per_page', 15)));

    $notificaciones = Notificacion::where('user_id', $userId)
        ->orderByDesc('created_at')
        ->paginate($porPagina);

    return response()->json([
        'message' => 'Notificaciones obtenidas correctamente',
        'notificaciones' => $notificaciones,
        // Total de no leídas (no solo las de esta página), para el contador.
        'no_leidas' => Notificacion::where('user_id', $userId)
            ->where('leida', false)
            ->count(),
    ]);
}

public function marcarTodasLeidas(Request $request)
{
    $actualizadas = Notificacion::where('user_id', $request->user()->id_u)
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
}