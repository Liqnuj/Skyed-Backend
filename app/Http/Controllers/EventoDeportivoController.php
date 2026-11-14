<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use App\Models\RutaEvento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; 
use Illuminate\Validation\ValidationException;
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
        // Los eventos cuya fecha ya pasó quedan "finalizado" antes de listarlos.
        EventoDeportivo::finalizarVencidos();

        $consulta = EventoDeportivo::with([
            'kit',
            'creador',
            'categorias',
            'patrocinadores',
        ]);

        // ?disponibles=1 (páginas públicas): solo eventos activos de hoy en adelante,
        // del más cercano al más lejano. Antes se mostraban también los inactivos,
        // inhabilitados y pasados, y el usuario intentaba inscribirse en ellos.
        if ($request->boolean('disponibles')) {
            $consulta->where('estado_e', 'activo')
                ->whereDate('fecha_e', '>=', EventoDeportivo::hoyLocal())
                ->orderBy('fecha_e')
                ->orderBy('hora_e');
        }

        $eventos = $consulta->paginate($this->perPage($request));

        return EventoDeportivoResource::collection($eventos);
    }

    /**
     * Mostrar un evento.
     */
    public function show(int $id)
    {
        EventoDeportivo::finalizarVencidos();

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
        $rutaId = (int) $validated['ruta_id_re'];
        unset($validated['ruta_id_re']);

        if ($respuesta = $this->kitYaAsignado($validated['id_k'] ?? null)) {
            return $respuesta;
        }

        $validated['estado_e'] = 'activo';
        $validated['creado_e'] = now();
        $validated['id_u'] = $request->user()->id_u;

        $evento = DB::transaction(function () use ($validated, $rutaId) {
            $ruta = RutaEvento::query()->lockForUpdate()->find($rutaId);
            if (!$ruta || $ruta->id_e !== null) {
                throw ValidationException::withMessages([
                    'ruta_id_re' => 'La ruta seleccionada ya está asignada a otro evento.',
                ]);
            }

            $evento = EventoDeportivo::create($validated);
            $ruta->update(['id_e' => $evento->id_e]);

            return $evento;
        });

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
        $rutaId = isset($validated['ruta_id_re']) ? (int) $validated['ruta_id_re'] : null;
        unset($validated['ruta_id_re']);

        if ($respuesta = $this->kitYaAsignado($validated['id_k'] ?? null, $evento->id_e)) {
            return $respuesta;
        }

        $estadoAnterior = $evento->estado_e;
        $antes = [$evento->fecha_e?->toDateString(), (string) $evento->hora_e, (string) $evento->ubicacion_e];

        // Un evento que ya finalizó y se reprograma para una fecha futura vuelve a quedar activo.
        if ($estadoAnterior === 'finalizado' && isset($validated['fecha_e'])
            && \Illuminate\Support\Carbon::parse($validated['fecha_e'])->toDateString() > EventoDeportivo::hoyLocal()) {
            $validated['estado_e'] = 'activo';
        }

        // FIX: antes los datos se guardaban ANTES de revisar la ruta, así que con una
        // ruta inválida el evento quedaba modificado igual. Ahora todo va en una sola
        // transacción: si la ruta no sirve, no se guarda nada.
        DB::transaction(function () use ($evento, $validated, $rutaId): void {
            if ($rutaId !== null) {
                $ruta = RutaEvento::query()->lockForUpdate()->find($rutaId);
                if (!$ruta || ($ruta->id_e !== null && $ruta->id_e !== $evento->id_e)) {
                    throw ValidationException::withMessages([
                        'ruta_id_re' => 'La ruta seleccionada ya está asignada a otro evento.',
                    ]);
                }
            }

            // Se guardan todos los datos del formulario, no solo el estado.
            $evento->update($validated);
            if ($rutaId !== null) {
                $ruta->update(['id_e' => $evento->id_e]);
            }
        });

        // Si cambió la fecha, la hora o el lugar, se avisa a los inscritos.
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
            'estado_e' => 'required|in:' . implode(',', EventoDeportivo::ESTADOS),
        ], [
            'estado_e.in' => 'El estado debe ser activo, inactivo, inhabilitado o finalizado.',
        ]);
        $nuevo = $validated['estado_e'];
        $estadoAnterior = $evento->estado_e;

        // Finalizar: solo desde el día del evento (antes no se ha realizado;
        // para cancelarlo está "inhabilitado").
        if ($nuevo === 'finalizado' && $estadoAnterior !== 'finalizado' && !$evento->yaEsElDia()) {
            return response()->json([
                'message' => 'Solo puedes finalizar el evento desde el día en que se realiza ('
                    . $evento->fecha_e?->format('d/m/Y') . '). Si se canceló, márcalo como inhabilitado.',
            ], 422);
        }

        // Un evento finalizado no vuelve a abrirse con una fecha que ya pasó:
        // para repetirlo hay que reprogramarlo (editar la fecha).
        if ($estadoAnterior === 'finalizado' && $nuevo !== 'finalizado' && $evento->fecha_e?->toDateString() < EventoDeportivo::hoyLocal()) {
            return response()->json([
                'message' => 'Este evento ya finalizó. Para volver a abrirlo, edita el evento y ponle una fecha nueva.',
            ], 422);
        }

        $evento->update(['estado_e' => $nuevo]);
        $this->avisarSiSeInhabilito($evento, $estadoAnterior);

        return response()->json([
            'message' => $nuevo === 'finalizado' ? 'Evento finalizado' : 'Estado del evento actualizado',
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