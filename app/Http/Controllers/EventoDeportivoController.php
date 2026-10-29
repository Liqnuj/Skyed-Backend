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
     * Un kit es individual por evento: si ya está asignado a otro evento
     * devuelve un 422; si está libre (o es el del propio evento) devuelve null.
     */
    private function kitYaAsignado($idKit, ?int $eventoActual = null)
    {
        if (!$idKit) {
            return null;
        }

        $query = EventoDeportivo::where('id_k', $idKit);
        if ($eventoActual) {
            $query->where('id_e', '!=', $eventoActual);
        }

        $otro = $query->first();
        if (!$otro) {
            return null;
        }

        return response()->json([
            'message' => "Ese kit ya está asignado al evento \"{$otro->nombre_e}\". Cada kit es de un solo evento: crea otro kit para este evento.",
        ], 422);
    }

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
        ])->paginate($this->perPage($request));

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

        if ($respuesta = $this->kitYaAsignado($validated['id_k'] ?? null)) {
            return $respuesta;
        }

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

        $validated = $request->validated();

        if ($respuesta = $this->kitYaAsignado($validated['id_k'] ?? null, $evento->id_e)) {
            return $respuesta;
        }

        $evento->update($validated);

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

        // FIX: la tabla inscripcion tiene cascadeOnDelete hacia el evento,
        // así que borrar un evento con inscritos eliminaba en silencio sus
        // inscripciones (y fallaba con 500 si alguna ya tenía resultado).
        $inscritos = $evento->inscripciones()->count();
        if ($inscritos > 0) {
            return response()->json([
                'message' => "Este evento tiene {$inscritos} inscripción(es). Inhabilítalo en lugar de eliminarlo para conservar el historial."
            ], 409);
        }

        // FIX: usaba $evento->imagen (no existe); la columna es imagen_e.
        if ($evento->imagen_e) {
            Storage::disk('public')->delete($evento->imagen_e);
        }

        $evento->delete();

        return response()->json([
            'message' => 'Evento eliminado correctamente'
        ]);
    }
}