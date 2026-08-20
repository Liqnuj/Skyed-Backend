<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use App\Models\RutaEvento;
use Illuminate\Http\Request;

class RutaEventoController extends Controller
{
    public function index($eventoId)
    {
        if (!EventoDeportivo::find($eventoId)) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        return response()->json([
            'rutas' => RutaEvento::where('id_e', $eventoId)->get()
        ]);
    }

    public function show($id)
    {
        $ruta = RutaEvento::with('evento')->find($id);

        if (!$ruta) {
            return response()->json([
                'message' => 'Ruta no encontrada'
            ], 404);
        }

        return response()->json([
            'ruta' => $ruta
        ]);
    }

    public function store(Request $request, $eventoId)
    {
        if (!EventoDeportivo::find($eventoId)) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_re' => 'required|string|max:100',
            'estado_re' => 'nullable|in:activo,inactivo,deshabilitado',
            'distancia_re' => 'nullable|string|max:45',
            'desnivel_re' => 'nullable|string|max:45',
            'descripcion_re' => 'nullable|string|max:255',
            'archivo_gpx_re' => 'nullable|string|max:255',
            'precio_re' => 'nullable|numeric|min:0',
        ]);

        $validated['id_e'] = $eventoId;
        $validated['estado_re'] ??= 'activo';

        $ruta = RutaEvento::create($validated);

        return response()->json([
            'message' => 'Ruta creada correctamente',
            'ruta' => $ruta->load('evento')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $ruta = RutaEvento::find($id);

        if (!$ruta) {
            return response()->json([
                'message' => 'Ruta no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_re' => 'sometimes|string|max:100',
            'estado_re' => 'sometimes|in:activo,inactivo,deshabilitado',
            'distancia_re' => 'sometimes|nullable|string|max:45',
            'desnivel_re' => 'sometimes|nullable|string|max:45',
            'descripcion_re' => 'sometimes|nullable|string|max:255',
            'archivo_gpx_re' => 'sometimes|nullable|string|max:255',
            'precio_re' => 'sometimes|nullable|numeric|min:0',
        ]);

        $ruta->update($validated);

        return response()->json([
            'message' => 'Ruta actualizada correctamente',
            'ruta' => $ruta->fresh()->load('evento')
        ]);
    }

    public function destroy($id)
    {
        $ruta = RutaEvento::find($id);

        if (!$ruta) {
            return response()->json([
                'message' => 'Ruta no encontrada'
            ], 404);
        }

        $ruta->delete();

        return response()->json([
            'message' => 'Ruta eliminada correctamente'
        ]);
    }
}