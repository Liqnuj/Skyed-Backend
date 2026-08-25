<?php

namespace App\Http\Controllers;

use App\Models\Patrocinador;
use Illuminate\Http\Request;

class PatrocinadorController extends Controller
{
    /**
     * Listar patrocinadores.
     */
    public function index(Request $request)
    {
        $patrocinadores = Patrocinador::with('eventos')
            ->paginate($request->input('per_page', 15));

        return response()->json($patrocinadores);
    }

    /**
     * Mostrar un patrocinador.
     */
    public function show($id)
    {
        $patrocinador = Patrocinador::with('eventos')->find($id);

        if (!$patrocinador) {
            return response()->json([
                'message' => 'Patrocinador no encontrado'
            ], 404);
        }

        return response()->json([
            'patrocinador' => $patrocinador
        ]);
    }

    /**
     * Crear un patrocinador.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_p' => 'required|string|max:100',
            'logo_p' => 'nullable|string|max:255',
            'telefono_p' => 'nullable|string|max:15',
            'correo_p' => 'nullable|email|max:80',
            'pagina_web_p' => 'nullable|string|max:120',
            'aporte_p' => 'nullable|string|max:100',
            'estado_p' => 'nullable|in:activo,inactivo,inhabilitado',
        ]);

        $validated['estado_p'] ??= 'activo';

        $patrocinador = Patrocinador::create($validated);

        return response()->json([
            'message' => 'Patrocinador creado correctamente',
            'patrocinador' => $patrocinador
        ], 201);
    }

    /**
     * Actualizar un patrocinador.
     */
    public function update(Request $request, $id)
    {
        $patrocinador = Patrocinador::find($id);

        if (!$patrocinador) {
            return response()->json([
                'message' => 'Patrocinador no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_p' => 'sometimes|string|max:100',
            'logo_p' => 'sometimes|nullable|string|max:255',
            'telefono_p' => 'sometimes|nullable|string|max:15',
            'correo_p' => 'sometimes|nullable|email|max:80',
            'pagina_web_p' => 'sometimes|nullable|string|max:120',
            'aporte_p' => 'sometimes|nullable|string|max:100',
            'estado_p' => 'sometimes|in:activo,inactivo,inhabilitado',
        ]);

        $patrocinador->update($validated);

        return response()->json([
            'message' => 'Patrocinador actualizado correctamente',
            'patrocinador' => $patrocinador->fresh()->load('eventos')
        ]);
    }

    /**
     * Eliminar un patrocinador.
     */
    public function destroy($id)
    {
        $patrocinador = Patrocinador::find($id);

        if (!$patrocinador) {
            return response()->json([
                'message' => 'Patrocinador no encontrado'
            ], 404);
        }

        $patrocinador->delete();

        return response()->json([
            'message' => 'Patrocinador eliminado correctamente'
        ]);
    }

    /**
     * Asignar un patrocinador a un evento deportivo.
     */
    public function asignarEvento(Request $request, $id)
    {
        $patrocinador = Patrocinador::find($id);

        if (!$patrocinador) {
            return response()->json([
                'message' => 'Patrocinador no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'evento_id_e' => 'required|exists:eventoDeportivo,id_e',
            'detalle' => 'nullable|string|max:255',
        ]);

        $patrocinador->eventos()->syncWithoutDetaching([
            $validated['evento_id_e'] => [
                'detalle' => $validated['detalle'] ?? null
            ]
        ]);

        return response()->json([
            'message' => 'Patrocinador asignado al evento correctamente',
            'patrocinador' => $patrocinador->load('eventos')
        ]);
    }
}