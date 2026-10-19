<?php

namespace App\Http\Controllers;

use App\Models\HistorialParticipacion;
use Illuminate\Http\Request;
use App\Http\Resources\HistorialParticipacionResource;

class HistorialParticipacionController extends Controller
{
    /**
     * FIX: antes esta ruta (zona general) devolvía el historial de
     * TODOS los usuarios sin ningún filtro. Ahora un usuario normal
     * solo ve el suyo; un adminDeportivo ve todo.
     */
    public function index(Request $request)
    {
        $query = HistorialParticipacion::with([
            'usuario',
            'evento'
        ]);

        if (!$request->user()->hasRole('adminDeportivo')) {
            $query->where('id_u', $request->user()->id_u);
        }

        $historial = $query->paginate($request->input('per_page', 15));

        return response()->json($historial);
    }

    /**
     * FIX: antes cualquier usuario autenticado podía pasar el
     * $usuarioId de otra persona y ver su historial completo (IDOR).
     * Ahora solo el propio usuario o un adminDeportivo pueden.
     */
    public function porUsuario(Request $request, $usuarioId)
    {
        $esDueno = (int) $usuarioId === (int) $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminDeportivo');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver este historial'
            ], 403);
        }

        $historial = HistorialParticipacion::with('evento')
            ->where('id_u', $usuarioId)
            ->orderByDesc('fecha_hp')
            ->get();

        return response()->json([
            'historial' => HistorialParticipacionResource::collection($historial)
        ]);
    }

    public function store(Request $request)
{
    $validated = $request->validate([
        'id_u' => 'required|exists:usuario,id_u',
        'id_e' => 'required|exists:eventoDeportivo,id_e',
        'estado_hp' => 'required|in:inscrito,finalizado,asistio,abandono',
        'observaciones_hp' => 'nullable|string|max:255',
    ]);

    $historial = HistorialParticipacion::create([
        'fecha_hp' => now(),
        'estado_hp' => $validated['estado_hp'],
        'observaciones_hp' => $validated['observaciones_hp'] ?? null,
        'id_u' => $validated['id_u'],
        'id_e' => $validated['id_e'],
    ]);

    return response()->json([
        'message' => 'Historial registrado correctamente',
        'historial' => $historial->load([
            'usuario',
            'evento'
        ])
    ], 201);
}

    public function update(Request $request, $id)
    {
        $historial = HistorialParticipacion::find($id);

        if (!$historial) {
            return response()->json([
                'message' => 'Registro de historial no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'estado_hp' => 'sometimes|in:inscrito,finalizado,asistio,abandono',
            'observaciones_hp' => 'sometimes|nullable|string|max:255',
        ]);

        $historial->update($validated);

        return response()->json([
            'message' => 'Historial actualizado correctamente',
            'historial' => $historial->fresh()->load([
                'usuario',
                'evento'
            ])
        ]);
    }
}