<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage; 
use App\Http\Requests\StoreEventoDeportivoRequest;
use App\Http\Requests\UpdateEventoDeportivoRequest;
use App\Http\Resources\EventoDeportivoResource;

class EventoDeportivoController extends Controller
{
    /**
     * Listar eventos.
     */
    public function index(Request $request)
    {
        $eventos = EventoDeportivo::with([
            'kit',
            'creador',
            'categorias',
            'patrocinadores',
        ])->paginate($request->input('per_page', 15));

        return EventoDeportivoResource::collection($eventos);
    }

    /**
     * Mostrar un evento.
     */
    public function show(int $id)
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
            'evento' => new EventoDeportivoResource($evento)
        ]);
    }

    /**
     * Crear evento.
     */
    public function store(StoreEventoDeportivoRequest $request)
    {
        $validated = $request->validated();

        $validated['estado_e'] = 'activo';
        $validated['creado_e'] = now();
        $validated['id_u'] = $request->user()->id_u;

        $evento = EventoDeportivo::create($validated);

        return response()->json([
            'message' => 'Evento creado correctamente',
            'evento' => new EventoDeportivoResource($evento->load([
                'kit',
                'creador',
                'categorias',
                'patrocinadores',
            ]))
        ], 201);
    }
    /**
     * Actualizar evento.
     */
    public function update(UpdateEventoDeportivoRequest $request, int $id)
    {
        $evento = EventoDeportivo::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $evento->update($request->validated());

        return response()->json([
            'message' => 'Evento actualizado correctamente',
            'evento' => new EventoDeportivoResource($evento->fresh()->load([
                'kit',
                'creador',
                'categorias',
                'patrocinadores',
            ]))
        ]);
    }

    /**
     * Cambiar estado del evento.
     */
    public function cambiarEstado(Request $request, int $id)
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
            'evento' => new EventoDeportivoResource($evento)
        ]);
    }

    /**
     * Eliminar evento.
     */
    public function destroy(int $id)
    {
        $evento = EventoDeportivo::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        if ($evento->imagen) {
            Storage::disk('public')->delete($evento->imagen);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente'
        ]);
    }
}