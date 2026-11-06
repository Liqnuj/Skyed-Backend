<?php

namespace App\Http\Controllers;

use App\Models\EntregaKit;
use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use Illuminate\Http\Request;

class EntregaKitController extends Controller
{
    /**
     * Listar las entregas de kit de un evento. Un admin deportivo ve
     * todas; un usuario normal solo ve la suya.
     *
     * FIX: antes cualquier usuario autenticado podía ver la lista
     * completa de entregas (nombre, documento vía la relación
     * `usuario`) de todos los participantes del evento.
     */
       public function index(Request $request, $eventoId)
    {
        $request->validate([
            'q' => 'sometimes|nullable|string|max:100',
            'estado' => 'sometimes|in:pendiente,entregado,devolucion',
            'per_page' => 'sometimes|integer|min:1|max:500',
        ]);

        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $query = EntregaKit::with([
            'kit',
            'usuario',
            'evento',
            'inscripcion',
            'invitado'
        ])->where('id_e', $eventoId);

        if (!$request->user()->hasRole('adminDeportivo')) {
            $query->where('id_u', $request->user()->id_u);
        }

        // Solo inscripciones confirmadas (las canceladas no entregan kit).
        $query->where(function ($w) {
            $w->whereNull('id_i')->orWhereHas('inscripcion', function ($i) {
                $i->where('estado_i', 'confirmada');
            });
        });

        // Búsqueda por documento o nombre del titular, o documento o nombre del
        // invitado. Se busca la inscripción y se devuelven TODAS sus filas, así
        // el admin siempre ve juntos el kit del titular y el del invitado.
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

            $query->whereIn('id_i', Inscripcion::select('id_i')
                ->where('id_e', $eventoId)
                ->where(function ($w) use ($like) {
                    $w->whereIn('id_u', function ($s) use ($like) {
                        $s->select('id_u')->from('usuario')->where(function ($x) use ($like) {
                            $x->whereRaw("CAST(documento_u AS TEXT) ILIKE ? ESCAPE '!'", [$like])
                              ->orWhereRaw("(nombre_u || ' ' || apellido_u) ILIKE ? ESCAPE '!'", [$like]);
                        });
                    })->orWhereIn('id_inv', function ($s) use ($like) {
                        $s->select('id_inv')->from('invitados')->where(function ($x) use ($like) {
                            $x->whereRaw("CAST(documento_inv AS TEXT) ILIKE ? ESCAPE '!'", [$like])
                              ->orWhereRaw("(nombre_inv || ' ' || apellido_inv) ILIKE ? ESCAPE '!'", [$like]);
                        });
                    });
                }));
        }

        $estado = $request->query('estado');
        if (in_array($estado, ['pendiente', 'entregado', 'devolucion'], true)) {
            $query->where('estado_ek', $estado);
        }

        // Titular primero y su invitado justo debajo.
        $query->orderBy('id_i')->orderByRaw('id_inv ASC NULLS FIRST');

        $entregas = $query->paginate($this->perPage($request));

        return response()->json([
            'evento' => $evento,
            'entregas' => $entregas
        ]);
    }
    /**
     * Listado general de entregas de kit (todos los eventos) para el panel
     * del admin deportivo: a quién se le entregó (o debe entregarse) cada kit
     * y de qué evento. Filtros opcionales: q (documento o nombre del titular
     * o del invitado), id_e (evento), estado y rol (titular | invitado).
     * Solo cuenta inscripciones confirmadas.
     */
    public function listado(Request $request)
    {
        $query = EntregaKit::with([
            'kit',
            'usuario',
            'evento',
            'inscripcion',
            'invitado',
        ])->whereHas('inscripcion', function ($i) {
            $i->where('estado_i', 'confirmada');
        });

        if ($request->filled('id_e')) {
            $query->where('id_e', (int) $request->query('id_e'));
        }

        $rol = $request->query('rol');
        if ($rol === 'titular') {
            $query->whereNull('id_inv');
        } elseif ($rol === 'invitado') {
            $query->whereNotNull('id_inv');
        }

        // Búsqueda por documento o nombre del titular, o del invitado. Se busca
        // la inscripción y se devuelven todas sus filas (titular e invitado).
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';

            $query->whereIn('id_i', Inscripcion::select('id_i')
                ->where(function ($w) use ($like) {
                    $w->whereIn('id_u', function ($s) use ($like) {
                        $s->select('id_u')->from('usuario')->where(function ($x) use ($like) {
                            $x->whereRaw('CAST(documento_u AS TEXT) ILIKE ?', [$like])
                              ->orWhereRaw("(nombre_u || ' ' || apellido_u) ILIKE ?", [$like]);
                        });
                    })->orWhereIn('id_inv', function ($s) use ($like) {
                        $s->select('id_inv')->from('invitados')->where(function ($x) use ($like) {
                            $x->whereRaw('CAST(documento_inv AS TEXT) ILIKE ?', [$like])
                              ->orWhereRaw("(nombre_inv || ' ' || apellido_inv) ILIKE ?", [$like]);
                        });
                    });
                }));
        }

        // Conteo por estado sobre lo filtrado hasta aquí (sin el filtro de estado),
        // para los totales de arriba de la pantalla.
        $conteo = (clone $query)
            ->selectRaw('estado_ek, COUNT(*) AS total')
            ->groupBy('estado_ek')
            ->pluck('total', 'estado_ek');

        $estado = $request->query('estado');
        if (in_array($estado, ['pendiente', 'entregado', 'devolucion'], true)) {
            $query->where('estado_ek', $estado);
        }

        // Inscripciones más recientes primero; titular y su invitado juntos.
        $query->orderByDesc('id_i')->orderByRaw('id_inv ASC NULLS FIRST');

        $entregas = $query->paginate($this->perPage($request));

        $entregas->getCollection()->transform(function ($e) {
            $esInvitado = $e->id_inv !== null;
            $usuario = $e->usuario;
            $invitado = $e->invitado;

            $titular = $usuario
                ? trim($usuario->nombre_u . ' ' . $usuario->apellido_u)
                : null;

            return [
                'id_ek' => $e->id_ek,
                'id_i' => $e->id_i,
                'id_inv' => $e->id_inv,
                'rol' => $esInvitado ? 'invitado' : 'titular',
                'nombre' => $esInvitado
                    ? ($invitado ? trim($invitado->nombre_inv . ' ' . $invitado->apellido_inv) : null)
                    : $titular,
                'documento' => $esInvitado
                    ? optional($invitado)->documento_inv
                    : optional($usuario)->documento_u,
                'titular' => $titular,
                'evento' => $e->evento
                    ? ['id' => $e->evento->id_e, 'nombre' => $e->evento->nombre_e]
                    : null,
                'kit' => optional($e->kit)->nombre_k,
                'talla' => $esInvitado
                    ? optional($invitado)->talla_kit_inv
                    : optional($e->inscripcion)->talla_camiseta_i,
                'estado' => $e->estado_ek,
                'fecha_entrega' => $e->fecha_entrega_real_ek,
                'entregado_por' => $e->persona_entrega_ek,
            ];
        });

        return response()->json([
            'entregas' => $entregas,
            'resumen' => [
                'pendiente' => (int) $conteo->get('pendiente', 0),
                'entregado' => (int) $conteo->get('entregado', 0),
                'devolucion' => (int) $conteo->get('devolucion', 0),
            ],
        ]);
    }

    /**
     * Mostrar una entrega específica. Solo el dueño o un
     * adminDeportivo pueden verla.
     *
     * FIX: antes no había ningún control de acceso (IDOR).
     */
    public function show(Request $request, $id)
    {
        $entrega = EntregaKit::with([
            'kit',
            'usuario',
            'evento',
            'inscripcion',
            'invitado'
        ])->find($id);

        if (!$entrega) {
            return response()->json([
                'message' => 'Entrega no encontrada'
            ], 404);
        }

        $esDueno = $entrega->id_u === $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminDeportivo');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta entrega'
            ], 403);
        }

        return response()->json([
            'entrega' => $entrega
        ]);
    }

    /**
     * Registrar la entrega de un kit de una inscripción. Sin id_inv es el
     * kit del titular; con id_inv es el del invitado. Si ya existe la fila
     * pendiente (se crea al inscribirse) se actualiza en vez de duplicarla.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_i' => 'required|exists:inscripcion,id_i',
            'id_inv' => 'nullable|exists:invitados,id_inv',
            'persona_entrega_ek' => 'nullable|string|max:80',
            'observaciones_ek' => 'nullable|string|max:255',
        ]);

        $inscripcion = Inscripcion::with(['evento', 'invitado'])->find($validated['id_i']);
        $idInv = $validated['id_inv'] ?? null;

        if ($inscripcion->estado_i !== 'confirmada') {
            return response()->json([
                'message' => 'La inscripción no está confirmada'
            ], 422);
        }

        if ($idInv !== null && (int) $inscripcion->id_inv !== (int) $idInv) {
            return response()->json([
                'message' => 'Ese invitado no pertenece a esta inscripción'
            ], 422);
        }

        $pidioKit = $idInv === null
            ? (bool) $inscripcion->desea_kit_i
            : (bool) optional($inscripcion->invitado)->desea_kit_inv;

        if (!$pidioKit || !$inscripcion->evento || !$inscripcion->evento->id_k) {
            return response()->json([
                'message' => 'Esta persona no pidió kit en la inscripción'
            ], 422);
        }

        $entrega = EntregaKit::where('id_i', $inscripcion->id_i)
            ->where('id_inv', $idInv)
            ->first();

        if ($entrega && $entrega->estado_ek === 'entregado') {
            return response()->json([
                'message' => 'El kit ya fue entregado'
            ], 409);
        }

        $datos = [
            'fecha_entrega_real_ek' => now(),
            'persona_entrega_ek' => $this->quienEntrega($request, $validated),
            'estado_ek' => 'entregado',
            'observaciones_ek' => $validated['observaciones_ek'] ?? null,
        ];

        if ($entrega) {
            $entrega->update($datos);
        } else {
            $entrega = EntregaKit::create($datos + [
                'id_k' => $inscripcion->evento->id_k,
                'id_u' => $inscripcion->id_u,
                'id_e' => $inscripcion->id_e,
                'id_i' => $inscripcion->id_i,
                'id_inv' => $idInv,
            ]);
        }

        $nombreEvento = EventoDeportivo::find($validated['id_e'])?->nombre_e ?? 'tu evento';
\App\Services\NotificacionService::crearSeguro(
    (int) $validated['id_u'],
    'Kit entregado',
    'Registramos la entrega de tu kit para «' . $nombreEvento . '». ¡Prepárate para la carrera!',
    'kit'
);

        return response()->json([
            'message' => 'Kit entregado correctamente',
            'entrega' => $entrega->fresh()->load([
                'kit',
                'usuario',
                'evento',
                'inscripcion',
                'invitado'
            ])
        ], 201);
    }

    /**
     * Actualizar una entrega.
     */
    public function update(Request $request, $id)
    {
        $entrega = EntregaKit::find($id);

        if (!$entrega) {
            return response()->json([
                'message' => 'Entrega no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'estado_ek' => 'sometimes|in:pendiente,entregado,devolucion',
            'persona_entrega_ek' => 'sometimes|string|max:80',
            'observaciones_ek' => 'sometimes|nullable|string|max:255',
        ]);

        if (($validated['estado_ek'] ?? null) === 'entregado') {
            if ($entrega->id_i) {
                $estadoInsc = Inscripcion::where('id_i', $entrega->id_i)->value('estado_i');
                if ($estadoInsc !== 'confirmada') {
                    return response()->json([
                        'message' => 'La inscripción no está confirmada'
                    ], 422);
                }
            }

            $validated['fecha_entrega_real_ek'] = now();
            $validated['persona_entrega_ek'] = $this->quienEntrega($request, $validated);
        } elseif (($validated['estado_ek'] ?? null) === 'pendiente') {
            $validated['fecha_entrega_real_ek'] = null;
        }

        $entrega->update($validated);

        return response()->json([
            'message' => 'Entrega actualizada correctamente',
            'entrega' => $entrega->fresh()->load([
                'kit',
                'usuario',
                'evento',
                'inscripcion',
                'invitado'
            ])
        ]);
    }

    /**
     * Quién entrega: lo que mande el front o, si no llega, el nombre del
     * staff con la sesión iniciada.
     */
    private function quienEntrega(Request $request, array $validated): string
    {
        if (!empty($validated['persona_entrega_ek'])) {
            return $validated['persona_entrega_ek'];
        }

        $u = $request->user();
        return mb_substr(trim(($u->nombre_u ?? '') . ' ' . ($u->apellido_u ?? '')), 0, 80);
    }
}