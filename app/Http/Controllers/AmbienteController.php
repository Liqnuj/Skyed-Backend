<?php

namespace App\Http\Controllers;

use App\Models\Ambiente;
use Illuminate\Http\Request;

class AmbienteController extends Controller
{
    public function index()
    {
        return response()->json([
            'ambientes' => Ambiente::with('servicios')->get()
        ]);
    }

    public function show($id)
    {
        $ambiente = Ambiente::with('servicios')->find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        return response()->json([
            'ambiente' => $ambiente
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_a' => 'required|string|max:100',
            'descripcion_a' => 'nullable|string',
            'capacidad_a' => 'required|integer|min:1',
            'precio_referencia_a' => 'nullable|numeric|min:0',
            'imagen_principal_a' => 'nullable|string|max:255',
        ]);

        $ambiente = Ambiente::create($validated);

        return response()->json([
            'message' => 'Ambiente creado correctamente',
            'ambiente' => $ambiente
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_a' => 'sometimes|string|max:100',
            'descripcion_a' => 'sometimes|nullable|string',
            'capacidad_a' => 'sometimes|integer|min:1',
            'precio_referencia_a' => 'sometimes|nullable|numeric|min:0',
            'imagen_principal_a' => 'sometimes|nullable|string|max:255',
        ]);

        $ambiente->update($validated);

        return response()->json([
            'message' => 'Ambiente actualizado correctamente',
            'ambiente' => $ambiente->fresh()->load('servicios')
        ]);
    }

    public function destroy($id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $ambiente->delete();

        return response()->json([
            'message' => 'Ambiente eliminado correctamente'
        ]);
    }

    public function asignarServicio(Request $request, $id)
    {
        $ambiente = Ambiente::find($id);

        if (!$ambiente) {
            return response()->json([
                'message' => 'Ambiente no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'id_s' => 'required|exists:servicio,id_s',
        ]);

        $ambiente->servicios()->syncWithoutDetaching([
            $validated['id_s']
        ]);

        return response()->json([
            'message' => 'Servicio asignado correctamente',
            'ambiente' => $ambiente->load('servicios')
        ]);
    }
}