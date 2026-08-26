<?php

namespace App\Http\Controllers;

use App\Models\CategoriaCompetencia;
use App\Models\EventoDeportivo;
use Illuminate\Http\Request;

class CategoriaCompetenciaController extends Controller
{
    public function index($eventoId)
    {
        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        return response()->json([
            'categorias' => CategoriaCompetencia::where(
                'id_e',
                $eventoId
            )->get()
        ]);
    }

    public function store(Request $request, $eventoId)
    {
        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_cc' => 'required|string|max:50',

            'edad_minima_cc' => 'nullable|integer|min:0',

            'edad_maxima_cc' => 'nullable|integer|min:0|gte:edad_minima_cc',

            'genero_cc' => 'nullable|in:masculino,femenino,mixto',

            'distancia_cc' => 'nullable|string|max:45',

            'descripcion_cc' => 'nullable|string|max:255',
        ]);

        $validated['id_e'] = $eventoId;

        $categoria = CategoriaCompetencia::create($validated);

        return response()->json([
            'message' => 'Categoría creada correctamente',
            'categoria' => $categoria
        ], 201);
    }

    public function show($id)
    {
        $categoria = CategoriaCompetencia::with('evento')->find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        return response()->json([
            'categoria' => $categoria
        ]);
    }

    public function update(Request $request, $id)
    {
        $categoria = CategoriaCompetencia::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_cc' => 'sometimes|string|max:50',

            'edad_minima_cc' => 'sometimes|integer|min:0',

            'edad_maxima_cc' => 'sometimes|integer|min:0|gte:edad_minima_cc',

            'genero_cc' => 'sometimes|in:masculino,femenino,mixto',

            'distancia_cc' => 'sometimes|string|max:45',

            'descripcion_cc' => 'sometimes|string|max:255',
        ]);

        $categoria->update($validated);

        return response()->json([
            'message' => 'Categoría actualizada correctamente',
            'categoria' => $categoria->fresh()
        ]);
    }

    public function destroy($id)
    {
        $categoria = CategoriaCompetencia::find($id);

        if (!$categoria) {
            return response()->json([
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        // Verificar si la categoría tiene resultados asociados
        if ($categoria->resultados()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene resultados asociados'
            ], 409);
        }

        $categoria->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente'
        ]);
    }
}