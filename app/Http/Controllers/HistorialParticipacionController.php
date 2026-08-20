<?php

namespace App\Http\Controllers;

use App\Models\HistorialParticipacion;
use Illuminate\Http\Request;

class HistorialParticipacionController extends Controller
{
    public function index()
    {
        $historial = HistorialParticipacion::with([
            'usuario',
            'evento'
        ])->get();

        return response()->json([
            'historial' => $historial
        ]);
    }

    public function porUsuario($usuarioId)
    {
        $historial = HistorialParticipacion::with('evento')
            ->where('id_u', $usuarioId)
            ->orderByDesc('fecha_hp')
            ->get();

        return response()->json([
            'historial' => $historial
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