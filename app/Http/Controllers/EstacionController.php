<?php

namespace App\Http\Controllers;

use App\Models\Estacion;
use App\Models\EventoDeportivo;
use Illuminate\Http\Request;

class EstacionController extends Controller
{
    public function index(Request $request, $eventoId)
    {
        if (!EventoDeportivo::find($eventoId)) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $estaciones = Estacion::where('id_e', $eventoId)
            ->paginate($request->input('per_page', 15));

        return response()->json($estaciones);
    }
    public function show($id)
    {
        $estacion = Estacion::with('evento')->find($id);

        if (!$estacion) {
            return response()->json([
                'message' => 'Estación no encontrada'
            ], 404);
        }

        return response()->json([
            'estacion' => $estacion
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
            'nombre_est' => 'required|string|max:100',
            'tipo_est' => 'required|in:hidratacion,primeros_auxilios,control,meta,general',
            'kilometro_est' => 'nullable|string|max:20',
            'latitud_est' => 'nullable|numeric|between:-90,90',
            'longitud_est' => 'nullable|numeric|between:-180,180',
            'descripcion_pest' => 'nullable|string|max:255',
            'estado_est' => 'nullable|in:activa,inactiva,cerrada',
        ]);

        $validated['id_e'] = $eventoId;
        $validated['estado_est'] ??= 'activa';

        $estacion = Estacion::create($validated);

        return response()->json([
            'message' => 'Estación creada correctamente',
            'estacion' => $estacion->load('evento')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $estacion = Estacion::find($id);

        if (!$estacion) {
            return response()->json([
                'message' => 'Estación no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_est' => 'sometimes|string|max:100',
            'tipo_est' => 'sometimes|in:hidratacion,primeros_auxilios,control,meta,general',
            'kilometro_est' => 'sometimes|nullable|string|max:20',
            'latitud_est' => 'sometimes|nullable|numeric|between:-90,90',
            'longitud_est' => 'sometimes|nullable|numeric|between:-180,180',
            'descripcion_pest' => 'sometimes|nullable|string|max:255',
            'estado_est' => 'sometimes|in:activa,inactiva,cerrada',
        ]);

        $estacion->update($validated);

        return response()->json([
            'message' => 'Estación actualizada correctamente',
            'estacion' => $estacion->fresh()->load('evento')
        ]);
    }

    public function destroy($id)
    {
        $estacion = Estacion::find($id);

        if (!$estacion) {
            return response()->json([
                'message' => 'Estación no encontrada'
            ], 404);
        }

        $estacion->delete();

        return response()->json([
            'message' => 'Estación eliminada correctamente'
        ]);
    }
}