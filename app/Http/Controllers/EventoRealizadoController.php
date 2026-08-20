<?php

namespace App\Http\Controllers;

use App\Models\EventoRealizado;
use Illuminate\Http\Request;

class EventoRealizadoController extends Controller
{
    public function index()
    {
        $eventos = EventoRealizado::with([
            'tipoEvento',
            'ambiente',
            'reservas',
        ])->get();

        return response()->json([
            'eventos' => $eventos
        ]);
    }

    public function show($id)
    {
        $evento = EventoRealizado::with([
            'tipoEvento',
            'ambiente',
            'reservas',
        ])->find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        return response()->json([
            'evento' => $evento
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_er' => 'required|string|max:150',
            'descripcion_er' => 'nullable|string|max:255',
            'fecha_er' => 'nullable|date',
            'id_tipo_eves' => 'required|exists:tipo_evento,id_tipo_eves',
            'id_a' => 'required|exists:ambiente,id_a',
        ]);

        $evento = EventoRealizado::create($validated);

        return response()->json([
            'message' => 'Evento social creado correctamente',
            'evento' => $evento->load([
                'tipoEvento',
                'ambiente',
            ])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $evento = EventoRealizado::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_er' => 'sometimes|string|max:150',
            'descripcion_er' => 'sometimes|nullable|string|max:255',
            'fecha_er' => 'sometimes|nullable|date',
            'id_tipo_eves' => 'sometimes|exists:tipo_evento,id_tipo_eves',
            'id_a' => 'sometimes|exists:ambiente,id_a',
        ]);

        $evento->update($validated);

        return response()->json([
            'message' => 'Evento social actualizado correctamente',
            'evento' => $evento->fresh()->load([
                'tipoEvento',
                'ambiente',
            ])
        ]);
    }

    public function destroy($id)
    {
        $evento = EventoRealizado::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento social eliminado correctamente'
        ]);
    }
}