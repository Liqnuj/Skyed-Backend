<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventoDeportivoController extends Controller
{
    /**
     * Listar eventos.
     */
    public function index()
    {
        $eventos = EventoDeportivo::with([
            'kit',
            'creador',
            'categorias',
            'patrocinadores',
        ])->get();

        return response()->json([
            'eventos' => $eventos
        ]);
    }

    /**
     * Mostrar un evento.
     */
    public function show($id)
    {
        $evento = EventoDeportivo::with([
            'kit',
            'creador',
            'categorias',
            'patrocinadores',
        ])->find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        return response()->json([
            'evento' => $evento
        ]);
    }

    /**
     * Crear evento.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_e' => 'required|string|max:120',
            'categoria_e' => 'required|in:atletismo,senderismo,ciclismo',
            'fecha_e' => 'required|date',
            'hora_e' => 'required',
            'ubicacion_e' => 'required|string|max:120',
            'descripcion_e' => 'required|string|max:255',
            'requisitos_e' => 'required|string|max:255',
            'imagen_e' => 'required|string|max:120',
            'cupos_disponibles_e' => 'required|integer|min:0',
            'id_k' => 'nullable|exists:kit,id_k',
        ]);

        $validated['estado_e'] = 'activo';
        $validated['creado_e'] = now();

        $validated['id_u'] = $request->user()->id_u;

        $evento = EventoDeportivo::create($validated);

        return response()->json([
            'message' => 'Evento creado correctamente',
            'evento' => $evento->load([
                'kit',
                'creador',
                'categorias',
                'patrocinadores',
            ])
        ], 201);
    }

    /**
     * Actualizar evento.
     */
    public function update(Request $request, $id)
    {
        $evento = EventoDeportivo::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_e' => 'sometimes|string|max:120',
            'categoria_e' => 'sometimes|in:atletismo,senderismo,ciclismo',
            'fecha_e' => 'sometimes|date',
            'hora_e' => 'sometimes',
            'ubicacion_e' => 'sometimes|string|max:120',
            'descripcion_e' => 'sometimes|string|max:255',
            'requisitos_e' => 'sometimes|string|max:255',
            'imagen_e' => 'sometimes|string|max:120',
            'cupos_disponibles_e' => 'sometimes|integer|min:0',
            'estado_e' => 'sometimes|in:activo,inactivo,inhabilitado',
            'id_k' => 'nullable|exists:kit,id_k',
        ]);

        $evento->update($validated);

        return response()->json([
            'message' => 'Evento actualizado correctamente',
            'evento' => $evento->fresh()->load([
                'kit',
                'creador',
                'categorias',
                'patrocinadores',
            ])
        ]);
    }

    /**
     * Cambiar estado del evento.
     */
    public function cambiarEstado(Request $request, $id)
    {
        $evento = EventoDeportivo::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'estado_e' => 'required|in:activo,inactivo,inhabilitado',
        ]);

        $evento->update([
            'estado_e' => $validated['estado_e']
        ]);

        return response()->json([
            'message' => 'Estado del evento actualizado',
            'evento' => $evento
        ]);
    }

    /**
     * Eliminar evento.
     */
    public function destroy($id)
    {
        $evento = EventoDeportivo::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente'
        ]);
    }
}