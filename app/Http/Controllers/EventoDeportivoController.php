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

        $estadoAnterior = $evento->estado_e;

        // Se guardan todos los datos del formulario (nombre, fecha, precio...),
        // no solo el estado: el formulario de editar ni siquiera lo envía.
        $antes = [$evento->fecha_e?->toDateString(), (string) $evento->hora_e, (string) $evento->ubicacion_e];
$evento->update($request->validated());
$evento->refresh();
$despues = [$evento->fecha_e?->toDateString(), (string) $evento->hora_e, (string) $evento->ubicacion_e];

if ($antes !== $despues) {
    \App\Models\Inscripcion::where('id_e', $evento->id_e)
        ->whereIn('estado_i', ['pendiente', 'confirmada'])
        ->pluck('id_u')->unique()
        ->each(fn ($uid) => \App\Services\NotificacionService::crearSeguro(
            (int) $uid,
            'Cambió la información de tu evento',
            'Se actualizó la fecha, hora o lugar de «' . $evento->nombre_e . '». Revísalo en tus inscripciones.',
            'evento'
        ));
}

        $this->avisarSiSeInhabilito($evento, $estadoAnterior);

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

$estadoAnterior = $evento->estado_e;
$evento->update(['estado_e' => $validated['estado_e']]);
$this->avisarSiSeInhabilito($evento, $estadoAnterior);

        return response()->json([
            'message' => 'Estado del evento actualizado',
            'evento' => new EventoDeportivoResource($evento)
        ]);
    }

    /**
     * Si el evento acaba de quedar inhabilitado, avisa a quienes siguen
     * inscritos (pendientes o confirmados) con una notificación.
     */
    private function avisarSiSeInhabilito(EventoDeportivo $evento, ?string $estadoAnterior): void
    {
        if ($estadoAnterior === 'inhabilitado' || $evento->estado_e !== 'inhabilitado') {
            return;
        }

        \App\Models\Inscripcion::where('id_e', $evento->id_e)
            ->whereIn('estado_i', ['pendiente', 'confirmada'])
            ->pluck('id_u')
            ->unique()
            ->each(fn ($uid) => \App\Services\NotificacionService::crearSeguro(
                (int) $uid,
                'Evento inhabilitado',
                'El evento «' . $evento->nombre_e . '» fue inhabilitado. Revisa tus inscripciones para más detalles.',
                'evento'
            ));
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
        $inscripciones = $evento->inscripciones()->count();
        if ($inscripciones > 0) {
            $activas = $evento->inscripciones()
                ->where('estado_i', '<>', 'cancelada')
                ->count();
            $sustantivo = $inscripciones === 1 ? 'inscripción' : 'inscripciones';
            $adjetivo = $activas === 1 ? 'activa' : 'activas';
            return response()->json([
                'message' => "Este evento conserva {$inscripciones} {$sustantivo} en su historial ({$activas} {$adjetivo}). Inhabilítalo en lugar de eliminarlo para conservar el historial."
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