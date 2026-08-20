<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioController extends Controller
{
    public function index()
    {
        return response()->json([
            'servicios' => Servicio::with('ambientes')->get()
        ]);
    }

    public function show($id)
    {
        $servicio = Servicio::with('ambientes')->find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        return response()->json([
            'servicio' => $servicio
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_s' => 'required|string|max:100',
            'descripcion_s' => 'nullable|string|max:255',
        ]);

        $servicio = Servicio::create($validated);

        return response()->json([
            'message' => 'Servicio creado correctamente',
            'servicio' => $servicio
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_s' => 'sometimes|string|max:100',
            'descripcion_s' => 'sometimes|nullable|string|max:255',
        ]);

        $servicio->update($validated);

        return response()->json([
            'message' => 'Servicio actualizado correctamente',
            'servicio' => $servicio->fresh()->load('ambientes')
        ]);
    }

    public function destroy($id)
    {
        $servicio = Servicio::find($id);

        if (!$servicio) {
            return response()->json([
                'message' => 'Servicio no encontrado'
            ], 404);
        }

        $servicio->delete();

        return response()->json([
            'message' => 'Servicio eliminado correctamente'
        ]);
    }
}