<?php

namespace App\Http\Controllers;

use App\Models\Resultado;
use App\Models\Inscripcion;
use App\Models\CategoriaResultado;
use Illuminate\Http\Request;

class ResultadoController extends Controller
{
    /**
     * Resultados. Es público (página "Resultados" de SKYED Deportivo), así que:
     *  - el administrador (o asistente con la sección) ve todo, como antes;
     *  - cualquier otra persona solo ve los resultados OFICIALES y del
     *    competidor únicamente su nombre. Antes se enviaba el usuario completo
     *    (correo, teléfono, documento, fecha de nacimiento) a quien lo pidiera.
     */
    public function index(Request $request)
    {
        $consulta = Resultado::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'categoriaResultado.categoria',
        ]);

        $esAdmin = $this->veTodoLosResultados();

        if (!$esAdmin) {
            $consulta->where('estado_r', 'oficial')
                ->orderByRaw('posicion_general_r is null')
                ->orderBy('posicion_general_r');
        }

        $resultados = $consulta->paginate($this->perPage($request));

        if (!$esAdmin) {
            $resultados->getCollection()->transform(fn (Resultado $r) => $this->presentarPublico($r));
        }

        return response()->json([
            'resultados' => $resultados
        ]);
    }

    /** Lo que cualquiera puede ver de un resultado: nada de datos personales. */
    private function presentarPublico(Resultado $r): array
    {
        $i = $r->inscripcion;

        return [
            'id_r' => $r->id_r,
            'tiempo_final_r' => $r->tiempo_final_r,
            'posicion_general_r' => $r->posicion_general_r,
            'estado_r' => $r->estado_r,
            'id_i' => $r->id_i,
            'inscripcion' => $i ? [
                'id_i' => $i->id_i,
                'usuario' => $i->usuario ? [
                    'nombre_u' => $i->usuario->nombre_u,
                    'apellido_u' => $i->usuario->apellido_u,
                ] : null,
                'evento' => $i->evento ? [
                    'id_e' => $i->evento->id_e,
                    'nombre_e' => $i->evento->nombre_e,
                    'categoria_e' => $i->evento->categoria_e,
                    'fecha_e' => $i->evento->fecha_e?->toDateString(),
                ] : null,
            ] : null,
            'categoria_resultado' => $r->categoriaResultado ? [
                'posicion_categoria' => $r->categoriaResultado->posicion_categoria,
                'categoria' => $r->categoriaResultado->categoria ? ['nombre_cc' => $r->categoriaResultado->categoria->nombre_cc] : null,
            ] : null,
        ];
    }

    public function show($id)
    {
        $resultado = Resultado::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'categoriaResultado.categoria',
        ])->find($id);

        // Público: solo resultados oficiales y sin datos personales (ver index).
        if (!$resultado || (!$this->veTodoLosResultados() && $resultado->estado_r !== 'oficial')) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        return response()->json([
            'resultado' => $this->veTodoLosResultados() ? $resultado : $this->presentarPublico($resultado)
        ]);
    }

    /** Administrador deportivo, super administrador o asistente de Deportivo: ven todos los datos. */
    private function veTodoLosResultados(): bool
    {
        $quien = auth('sanctum')->user();

        return $quien instanceof \App\Models\User
            && ($quien->hasRole('adminDeportivo') || $quien->esSuperAdmin() || $quien->hasRoleInContext('asistente', 'deportivo'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tiempo_final_r' => 'required|date_format:H:i:s',
            'posicion_general_r' => 'nullable|integer|min:1',
            'estado_r' => 'required|in:oficial,en revision,descalificado',
            'id_i' => 'required|exists:inscripcion,id_i',

            'posicion_categoria' => 'nullable|integer|min:1',
            'estado_competidor' => 'nullable|in:clasificado,descalificado',
            'id_cc' => 'nullable|exists:categoria_competencia,id_cc',
        ]);

        $inscripcion = Inscripcion::find($validated['id_i']);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        // El participante debe tener la inscripción confirmada
        if ($inscripcion->estado_i !== 'confirmada') {
            return response()->json([
                'message' => 'Solo se puede registrar resultado de una inscripción confirmada',
                'estado_i' => $inscripcion->estado_i
            ], 422);
        }

        // Evitar resultados duplicados
        $resultadoExistente = Resultado::where(
            'id_i',
            $validated['id_i']
        )->exists();

        if ($resultadoExistente) {
            return response()->json([
                'message' => 'Esta inscripción ya tiene un resultado'
            ], 409);
        }

        $resultado = Resultado::create([
            'tiempo_final_r' => $validated['tiempo_final_r'],
            'posicion_general_r' => $validated['posicion_general_r'] ?? null,
            'estado_r' => $validated['estado_r'],
            'id_i' => $validated['id_i'],
        ]);

        // Registrar posición dentro de la categoría
        if (
            isset($validated['posicion_categoria']) &&
            isset($validated['id_cc'])
        ) {
            CategoriaResultado::create([
                'posicion_categoria' => $validated['posicion_categoria'],
                'estado_competidor' => $validated['estado_competidor'] ?? 'clasificado',
                'id_cc' => $validated['id_cc'],
                'id_r' => $resultado->id_r,
            ]);
        }

        if ($validated['estado_r'] === 'oficial') {
    $inscripcion->loadMissing('evento');
    \App\Services\NotificacionService::crearSeguro(
        (int) $inscripcion->id_u,
        'Resultado publicado',
        'Ya está disponible tu resultado de «' . ($inscripcion->evento->nombre_e ?? 'el evento') . '».',
        'resultado'
    );
}

        return response()->json([
            'message' => 'Resultado registrado correctamente',
            'resultado' => $resultado->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'categoriaResultado.categoria',
            ])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $resultado = Resultado::find($id);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'tiempo_final_r' => 'sometimes|date_format:H:i:s',
            'posicion_general_r' => 'sometimes|nullable|integer|min:1',
            'estado_r' => 'sometimes|in:oficial,en revision,descalificado',
        ]);

        // Actualizar el resultado
        $resultado->update($validated);

        if (($validated['estado_r'] ?? null) === 'oficial' && $resultado->wasChanged('estado_r')) {
    $resultado->loadMissing('inscripcion.evento');
    \App\Services\NotificacionService::crearSeguro(
        (int) $resultado->inscripcion->id_u,
        'Resultado publicado',
        'Ya está disponible tu resultado de «' . ($resultado->inscripcion->evento->nombre_e ?? 'el evento') . '».',
        'resultado'
    );
}


        return response()->json([
            'message' => 'Resultado actualizado correctamente',
            'resultado' => $resultado->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'categoriaResultado.categoria',
            ])
        ]);
    }

    public function destroy($id)
    {
        $resultado = Resultado::find($id);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        $resultado->delete();

        return response()->json([
            'message' => 'Resultado eliminado correctamente'
        ]);
    }

    /**
     * GET /eventos/{eventoId}/resultados/tablero (admin)
     *
     * Todos los inscritos confirmados del evento con su categoría (nivel),
     * dorsal y el resultado que ya tengan, para registrar los resultados
     * de todo el evento en una sola pantalla.
     */
    public function tablero(int $eventoId)
    {
        $evento = \App\Models\EventoDeportivo::with('categorias')->find($eventoId);
        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $filas = Inscripcion::with(['usuario', 'categoria', 'resultado.categoriaResultado.categoria'])
            ->where('id_e', $eventoId)
            ->where('estado_i', 'confirmada')
            ->orderBy('id_i')
            ->get()
            ->map(fn (Inscripcion $i) => [
                'id_i' => $i->id_i,
                'nombre' => trim(($i->usuario->nombre_u ?? '') . ' ' . ($i->usuario->apellido_u ?? '')) ?: 'Participante',
                'documento' => $i->usuario->documento_u ?? null,
                'dorsal' => $i->numero_dorsal_i,
                // Categoría de la inscripción; si no la tiene, la que ya quedó en su resultado.
                'id_cc' => $i->id_cc ?? $i->resultado?->categoriaResultado?->id_cc,
                'categoria' => $i->categoria->nombre_cc
                    ?? $i->resultado?->categoriaResultado?->categoria?->nombre_cc,
                'resultado' => $i->resultado ? [
                    'id_r' => $i->resultado->id_r,
                    'tiempo_final_r' => $i->resultado->tiempo_final_r,
                    'estado_r' => $i->resultado->estado_r,
                    'posicion_general_r' => $i->resultado->posicion_general_r,
                    'posicion_categoria' => $i->resultado->categoriaResultado->posicion_categoria ?? null,
                ] : null,
            ]);

        return response()->json([
            'evento' => ['id' => $evento->id_e, 'nombre' => $evento->nombre_e, 'fecha' => $evento->fecha_e?->toDateString()],
            'categorias' => $evento->categorias->map(fn ($c) => ['id_cc' => $c->id_cc, 'nombre' => $c->nombre_cc])->values(),
            'participantes' => $filas,
        ]);
    }

    /**
     * POST /eventos/{eventoId}/resultados/lote (admin)
     *
     * Guarda de una vez los tiempos de varios participantes y calcula solo
     * las posiciones: la general (todo el evento) y la de cada categoría
     * (nivel), del menor tiempo al mayor. Los descalificados quedan sin
     * posición. Así el administrador ve a la vez quiénes son los ganadores.
     *
     * Cuerpo: { estado_r: "oficial"|"en revision", filas: [{ id_i, tiempo_final_r: "HH:MM:SS", descalificado: bool }] }
     */
    public function guardarLote(Request $request, int $eventoId)
    {
        $evento = \App\Models\EventoDeportivo::find($eventoId);
        if (!$evento) {
            return response()->json(['message' => 'Evento no encontrado'], 404);
        }

        $validated = $request->validate([
            'estado_r' => 'required|in:oficial,en revision',
            'filas' => 'required|array|min:1|max:1000',
            'filas.*.id_i' => 'required|integer|distinct',
            'filas.*.tiempo_final_r' => ['required', 'date_format:H:i:s', 'not_in:00:00:00'],
            'filas.*.descalificado' => 'sometimes|boolean',
        ], [
            'filas.required' => 'Escribe el tiempo de al menos un participante.',
            'filas.*.tiempo_final_r.required' => 'Cada participante necesita su tiempo (HH:MM:SS).',
            'filas.*.tiempo_final_r.date_format' => 'Los tiempos deben tener el formato HH:MM:SS (por ejemplo 02:15:30).',
            'filas.*.tiempo_final_r.not_in' => 'Un tiempo no puede ser 00:00:00.',
            'filas.*.id_i.distinct' => 'Hay un participante repetido.',
        ]);

        // Todas las inscripciones deben ser de este evento y estar confirmadas.
        $ids = collect($validated['filas'])->pluck('id_i')->all();
        $inscripciones = Inscripcion::whereIn('id_i', $ids)->get()->keyBy('id_i');
        foreach ($ids as $id) {
            $ins = $inscripciones->get($id);
            if (!$ins || (int) $ins->id_e !== $eventoId) {
                return response()->json(['message' => 'Uno de los participantes no pertenece a este evento.'], 422);
            }
            if ($ins->estado_i !== 'confirmada') {
                return response()->json(['message' => 'Solo se registran resultados de inscripciones confirmadas.'], 422);
            }
        }

        $estadoLote = $validated['estado_r'];
        $nuevosOficiales = [];

        \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $estadoLote, $eventoId, &$nuevosOficiales) {
            foreach ($validated['filas'] as $fila) {
                $estado = !empty($fila['descalificado']) ? 'descalificado' : $estadoLote;
                $resultado = Resultado::where('id_i', $fila['id_i'])->first();
                $antes = $resultado?->estado_r;

                if ($resultado) {
                    $resultado->update(['tiempo_final_r' => $fila['tiempo_final_r'], 'estado_r' => $estado]);
                } else {
                    $resultado = Resultado::create([
                        'tiempo_final_r' => $fila['tiempo_final_r'],
                        'estado_r' => $estado,
                        'id_i' => $fila['id_i'],
                    ]);
                }

                if ($estado === 'oficial' && $antes !== 'oficial') {
                    $nuevosOficiales[] = (int) $fila['id_i'];
                }
            }

            $this->recalcularPosiciones($eventoId);
        });

        // Aviso a quienes ya tienen su resultado oficial.
        if ($nuevosOficiales) {
            $nombre = $evento->nombre_e;
            Inscripcion::whereIn('id_i', $nuevosOficiales)->pluck('id_u')->unique()
                ->each(fn ($uid) => \App\Services\NotificacionService::crearSeguro(
                    (int) $uid,
                    'Resultado publicado',
                    'Ya está disponible tu resultado de «' . $nombre . '».',
                    'resultado'
                ));
        }

        return response()->json([
            'message' => $estadoLote === 'oficial'
                ? 'Resultados publicados como oficiales. Las posiciones se calcularon por tiempo.'
                : 'Resultados guardados en revisión. Las posiciones se calcularon por tiempo.',
            'guardados' => count($validated['filas']),
        ]);
    }

    /**
     * Posiciones de todo el evento a partir de los tiempos: general y por
     * categoría (nivel). Menor tiempo = mejor puesto; los descalificados no
     * reciben posición. Recalcula también los resultados que ya existían,
     * para que nunca queden dos personas con el mismo puesto.
     */
    private function recalcularPosiciones(int $eventoId): void
    {
        $resultados = Resultado::with(['inscripcion', 'categoriaResultado'])
            ->whereHas('inscripcion', fn ($q) => $q->where('id_e', $eventoId))
            ->get();

        $validos = $resultados->where('estado_r', '!=', 'descalificado')
            ->sortBy(fn ($r) => (string) $r->tiempo_final_r)
            ->values();

        foreach ($validos as $i => $r) {
            if ((int) $r->posicion_general_r !== $i + 1) {
                $r->update(['posicion_general_r' => $i + 1]);
            }
        }

        foreach ($resultados->where('estado_r', 'descalificado') as $r) {
            if ($r->posicion_general_r !== null) {
                $r->update(['posicion_general_r' => null]);
            }
            if ($r->categoriaResultado) {
                $r->categoriaResultado->update(['estado_competidor' => 'descalificado']);
            }
        }

        // Por categoría (nivel): la de la inscripción o, si no tiene, la que ya tenía su resultado.
        $categoriaDe = fn ($r) => $r->inscripcion?->id_cc ?? $r->categoriaResultado?->id_cc;
        $validos->filter(fn ($r) => $categoriaDe($r))
            ->groupBy($categoriaDe)
            ->each(function ($grupo, $idCc) {
                foreach ($grupo->values() as $i => $r) {
                    CategoriaResultado::updateOrCreate(
                        ['id_r' => $r->id_r],
                        ['id_cc' => (int) $idCc, 'posicion_categoria' => $i + 1, 'estado_competidor' => 'clasificado']
                    );
                }
            });
    }
}