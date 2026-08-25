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
        $notificaciones = Notificacion::where(
            'user_id',
            $request->user()->id_u
        )
        ->orderByDesc('created_at')
        ->paginate($request->input('per_page', 15));

        return response()->json([
            'message' => 'Notificaciones obtenidas correctamente',
            'notificaciones' => $notificaciones
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