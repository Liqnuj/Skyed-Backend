<?php

namespace App\Http\Controllers;

use App\Models\Estacion;
use App\Models\EventoDeportivo;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EstacionController extends Controller
{
    private function reglasKilometro(EventoDeportivo $evento): array
    {
        $distancia = trim((string) $evento->distancia_e);
        $tieneDistancia = $distancia !== '';
        $reglas = $tieneDistancia
            ? ['required', 'numeric', 'min:0']
            : ['nullable', 'numeric', 'min:0'];

        if (preg_match('/^\s*(\d+(?:[.,]\d+)?)/', $distancia, $coincidencia)) {
            $reglas[] = 'max:' . (float) str_replace(',', '.', $coincidencia[1]);
        }

        return ['kilometro_est' => $reglas];
    }

    public function index(Request $request, $eventoId)
    {
        if (!EventoDeportivo::find($eventoId)) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $estaciones = Estacion::where('id_e', $eventoId)
            ->paginate($this->perPage($request));

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
        $evento = EventoDeportivo::find($eventoId);
        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_est' => 'required|string|max:100',
            'tipo_est' => 'required|in:hidratacion,ambulancia,primeros_auxilios,control,meta,general',
            ...$this->reglasKilometro($evento),
            'latitud_est' => 'nullable|numeric|between:-90,90',
            'longitud_est' => 'nullable|numeric|between:-180,180',
            'descripcion_pest' => 'nullable|string|max:255',
            'estado_est' => 'nullable|in:activa,inactiva,cerrada',
        ], [
            'kilometro_est.required' => 'Indica el kilómetro de la estación porque el evento tiene una distancia definida.',
            'kilometro_est.max' => 'El kilómetro de la estación no puede superar la distancia del evento.',
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

        $eventoId = $request->input('id_e', $estacion->id_e);
        $evento = EventoDeportivo::find($eventoId);
        if (!$evento) {
            throw ValidationException::withMessages(['id_e' => 'Selecciona un evento válido para la estación.']);
        }

        $validated = $request->validate([
            'id_e' => 'sometimes|required|integer|exists:eventoDeportivo,id_e',
            'nombre_est' => 'sometimes|string|max:100',
            'tipo_est' => 'sometimes|in:hidratacion,ambulancia,primeros_auxilios,control,meta,general',
            ...$this->reglasKilometro($evento),
            'latitud_est' => 'sometimes|nullable|numeric|between:-90,90',
            'longitud_est' => 'sometimes|nullable|numeric|between:-180,180',
            'descripcion_pest' => 'sometimes|nullable|string|max:255',
            'estado_est' => 'sometimes|in:activa,inactiva,cerrada',
        ], [
            'kilometro_est.required' => 'Indica el kilómetro de la estación porque el evento tiene una distancia definida.',
            'kilometro_est.max' => 'El kilómetro de la estación no puede superar la distancia del evento.',
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