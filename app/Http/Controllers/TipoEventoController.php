<?php

namespace App\Http\Controllers;

use App\Models\TipoEvento;
use Illuminate\Http\Request;

class TipoEventoController extends Controller
{
    public function index()
    {
        return response()->json([
            'tipos_evento' => TipoEvento::with('eventos')->get()
        ]);
    }

    public function show($id)
    {
        $tipo = TipoEvento::with('eventos')->find($id);

        if (!$tipo) {
            return response()->json([
                'message' => 'Tipo de evento no encontrado'
            ], 404);
        }

        return response()->json([
            'tipo_evento' => $tipo
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_tipo_eves' => 'required|string|max:50',
            'descripcion_eves' => 'nullable|string|max:120',
        ]);

        $tipo = TipoEvento::create($validated);

        return response()->json([
            'message' => 'Tipo de evento creado correctamente',
            'tipo_evento' => $tipo
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $tipo = TipoEvento::find($id);

        if (!$tipo) {
            return response()->json([
                'message' => 'Tipo de evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_tipo_eves' => 'sometimes|string|max:50',
            'descripcion_eves' => 'sometimes|nullable|string|max:120',
        ]);

        $tipo->update($validated);

        return response()->json([
            'message' => 'Tipo de evento actualizado correctamente',
            'tipo_evento' => $tipo->fresh()
        ]);
    }

    public function destroy($id)
    {
        $tipo = TipoEvento::find($id);

        if (!$tipo) {
            return response()->json([
                'message' => 'Tipo de evento no encontrado'
            ], 404);
        }

        $tipo->delete();

        return response()->json([
            'message' => 'Tipo de evento eliminado correctamente'
        ]);
    }
}