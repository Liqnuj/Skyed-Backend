<?php

namespace App\Http\Controllers;

use App\Models\EventoRealizado;
use Illuminate\Http\Request;
use App\Http\Requests\StoreEventoRealizadoRequest;
use App\Http\Requests\UpdateEventoRealizadoRequest;

class EventoRealizadoController extends Controller
{
    public function index(Request $request)
    {
        $eventos = EventoRealizado::with([
            'tipoEvento',
            'ambiente.servicios',
            'creador',
            'reservas',
        ])->paginate($request->input('per_page', 15));

        return response()->json($eventos);
    }

    public function show($id)
    {
        $evento = EventoRealizado::with([
            'tipoEvento',
            'ambiente.servicios',
            'creador',
            'reservas',
        ])->find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        return response()->json([
            'evento' => $evento
        ]);
    }

    public function store(StoreEventoRealizadoRequest $request)
    {
        $validated = $request->validated();

        $validated['estado_er'] = 'activo';
        $validated['id_u'] = $request->user()->id_u;

        $evento = EventoRealizado::create($validated);

        return response()->json([
            'message' => 'Evento social creado correctamente',
            'evento' => $evento->load([
                'tipoEvento',
                'ambiente',
                'creador',
            ])
        ], 201);
    }


    public function update(UpdateEventoRealizadoRequest $request, $id)
    {
        $evento = EventoRealizado::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        $validated = $request->validated();

        if (array_key_exists('imagen_er', $validated)
            && $validated['imagen_er'] !== $evento->imagen_er) {
            SocialImagenController::eliminarSiEsPropia($evento->imagen_er);
        }

        $evento->update($validated);

        return response()->json([
            'message' => 'Evento social actualizado correctamente',
            'evento' => $evento->fresh()->load([
                'tipoEvento',
                'ambiente',
                'creador',
            ])
        ]);
    }

    /**
     * Cambiar estado del evento social (activo/inactivo) sin borrarlo.
     */
    public function cambiarEstado(Request $request, $id)
    {
        $evento = EventoRealizado::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'estado_er' => 'required|in:activo,inactivo',
        ]);

        $evento->update([
            'estado_er' => $validated['estado_er']
        ]);

        return response()->json([
            'message' => 'Estado del evento social actualizado',
            'evento' => $evento
        ]);
    }

    public function destroy($id)
    {
        $evento = EventoRealizado::find($id);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento social no encontrado'
            ], 404);
        }

        SocialImagenController::eliminarSiEsPropia($evento->imagen_er);
        $evento->delete();

        return response()->json([
            'message' => 'Evento social eliminado correctamente'
        ]);
    }
}