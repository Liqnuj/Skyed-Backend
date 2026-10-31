<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificacionService;
use App\Services\ReservaFlujo;
use App\Services\ReservaTrazabilidad;
use App\Support\ReservaDetalle;
use App\Support\ReservaFechas;

class ReservaController extends Controller
{
    /**
     * Lo que ve el CLIENTE del historial de su reserva: solo lo visible
     * (nunca notas internas) y solo las columnas que necesita (sin id del admin).
     */
    private function historialCliente(): \Closure
    {
        return fn ($q) => $q->visiblesParaUsuario()
            ->select(['id_seguimiento', 'id_rese', 'fecha_actualizacion', 'comentario'])
            ->orderBy('fecha_actualizacion')
            ->orderBy('id_seguimiento');
    }

    /**
     * Listar reservas. Un admin social ve todas (con filtros); un usuario
     * normal solo ve las suyas.
     *
     * Filtros del admin (todos opcionales): estado, etapa, id_tipo_eves,
     * id_responsable, id_a, lugar, desde, hasta, q (texto), orden
     * (recientes | fecha | fecha_desc | estado), ocultas, per_page.
     */
    public function index(Request $request)
    {
        $ocultas = $request->boolean('ocultas');

        if (!$request->user()->hasRole('adminSocial')) {
            $query = Reserva::with([
                'usuario',
                'evento',
                'tipoEvento',
                'seguimientos' => $this->historialCliente(),
            ])
                ->where('id_u', $request->user()->id_u)
                ->where('oculta_usuario', $ocultas)
                ->orderByDesc('creado_en_rese')
                ->orderByDesc('id_rese');

            return response()->json(
                $query->paginate($this->perPage($request))
            );
        }

        $request->validate([
            'estado' => 'sometimes|in:pendiente,confirmada,cancelada,completada',
            'etapa' => 'sometimes|in:' . implode(',', \App\Models\ReservaGestion::ETAPAS),
            'id_tipo_eves' => 'sometimes|integer',
            'id_responsable' => 'sometimes|integer',
            'id_a' => 'sometimes|integer',
            'lugar' => 'sometimes|nullable|string|max:120',
            'desde' => 'sometimes|date',
            'hasta' => 'sometimes|date|after_or_equal:desde',
            'q' => 'sometimes|nullable|string|max:100',
            'orden' => 'sometimes|in:recientes,fecha,fecha_desc,estado',
        ], [
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
        ]);

        $query = ReservaFechas::unirGestion(Reserva::query())
            ->with(ReservaDetalle::relacionesListado())
            ->where('reserva.oculta_admin', $ocultas);

        if ($request->filled('estado')) {
            $query->where('reserva.estado_rese', $request->input('estado'));
        }

        if ($request->filled('etapa')) {
            $query->where('reserva_gestion.etapa_gestion', $request->input('etapa'));
        }

        if ($request->filled('id_tipo_eves')) {
            $query->where('reserva.id_tipo_eves', $request->input('id_tipo_eves'));
        }

        if ($request->filled('id_responsable')) {
            $query->where('reserva_gestion.id_responsable', $request->input('id_responsable'));
        }

        if ($request->filled('id_a')) {
            $query->where('reserva_gestion.id_a', $request->input('id_a'));
        }

        if ($request->filled('lugar')) {
            $query->whereRaw('LOWER(reserva.ubicacion_rese) LIKE ?', ['%' . mb_strtolower(trim($request->input('lugar'))) . '%']);
        }

        if ($request->filled('desde') || $request->filled('hasta')) {
            ReservaFechas::aplicarRango(
                $query,
                $request->input('desde', '0001-01-01'),
                $request->input('hasta', '9999-12-31')
            );
        }

        if ($request->filled('q')) {
            $texto = mb_strtolower(trim($request->input('q')));
            $like = '%' . $texto . '%';

            $query->where(function ($w) use ($like, $texto) {
                $w->whereRaw("LOWER(COALESCE(reserva.nombre_rese, '')) LIKE ?", [$like])
                    ->orWhereRaw('LOWER(reserva.ubicacion_rese) LIKE ?', [$like])
                    ->orWhereHas('usuario', function ($u) use ($like) {
                        $u->whereRaw("LOWER(nombre_u || ' ' || apellido_u) LIKE ?", [$like])
                            ->orWhereRaw('LOWER(correo_u) LIKE ?', [$like])
                            ->orWhereRaw('CAST(telefono_u AS TEXT) LIKE ?', [$like]);
                    });

                if (preg_match('/^#?(\d+)$/', $texto, $m)) {
                    $w->orWhere('reserva.id_rese', (int) $m[1]);
                }
            });
        }

        match ($request->input('orden', 'recientes')) {
            'fecha' => $query->orderByRaw('COALESCE(reserva_gestion.fecha_confirmada, reserva_gestion.fecha_propuesta, reserva.fecha_evento_rese) ASC'),
            'fecha_desc' => $query->orderByRaw('COALESCE(reserva_gestion.fecha_confirmada, reserva_gestion.fecha_propuesta, reserva.fecha_evento_rese) DESC'),
            'estado' => $query->orderBy('reserva.estado_rese'),
            default => null,
        };

        $query->orderByDesc('reserva.creado_en_rese')->orderByDesc('reserva.id_rese');

        $pagina = $query->paginate($this->perPage($request));

        // Cómo se dibuja cada reserva (nueva, en planificación, confirmada...).
        $pagina->through(function (Reserva $r) {
            $r->setAttribute('estado_visual', ReservaFlujo::estadoVisual($r));

            return $r;
        });

        return response()->json($pagina);
    }

    /**
     * Mostrar una reserva. Un usuario normal solo puede ver la suya
     * (sin información administrativa ni notas internas); el admin social
     * recibe el detalle completo y la información de apoyo (meta).
     */
    public function show(Request $request, $id)
    {
        $esAdmin = $request->user()->hasRole('adminSocial');

        $reserva = Reserva::with(
            $esAdmin
                ? ReservaDetalle::relacionesAdmin()
                : ['usuario', 'evento', 'tipoEvento', 'seguimientos' => $this->historialCliente()]
        )->find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $esDueno = (int) $reserva->id_u === (int) $request->user()->id_u;

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta reserva'
            ], 403);
        }

        if ($esAdmin) {
            return response()->json([
                'reserva' => $reserva,
                'meta' => ReservaDetalle::meta($reserva),
            ]);
        }

        return response()->json([
            'reserva' => $reserva
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                    // id_u ya no se recibe: la reserva siempre queda a nombre del usuario
                    // autenticado. Antes se exigía en el body (aunque se ignoraba al guardar).
                    'id_tipo_eves' => 'required|exists:tipo_evento,id_tipo_eves', // Para saber qué tipo de evento es
                    'fecha_evento_rese' => 'required|date|after_or_equal:today',
                    'invitados_rese' => 'required|integer|min:1|max:100000',
                    'presupuesto_rese' => 'required|numeric|min:0|max:9999999999',
                    'ubicacion_rese' => 'required|string|max:120',
                    'Observaciones_rese' => 'required|string|max:255', // También puedes poner 'nullable' si no es obligatorio
                    'id_er' => 'nullable|exists:evento_realizado,id_er', // Aquí está la corrección principal
                    'nombre_rese' => 'nullable|string|max:80', // Nombre que el cliente le pone a su reserva

                    // Opcionales: ayudan a planificar (hora, duración, rango de fechas y servicios).
                    'fecha_hasta_rese' => 'nullable|date|after_or_equal:fecha_evento_rese',
                    'hora_aprox_rese' => 'nullable|date_format:H:i',
                    'duracion_horas_rese' => 'nullable|integer|min:1|max:72',
                    'servicios_solicitados' => 'nullable|array|max:20',
                    'servicios_solicitados.*.id_s' => 'nullable|integer|exists:servicio,id_s',
                    'servicios_solicitados.*.nombre_servicio' => 'required_without:servicios_solicitados.*.id_s|nullable|string|max:100',
                    'servicios_solicitados.*.cantidad' => 'nullable|integer|min:1|max:1000',
                ]);

        $reserva = DB::transaction(function () use (
            $validated,
            $request
        ) {
        $reserva = Reserva::create([
                        'fecha_evento_rese' => $validated['fecha_evento_rese'],
                        'fecha_hasta_rese' => $validated['fecha_hasta_rese'] ?? null,
                        'hora_aprox_rese' => $validated['hora_aprox_rese'] ?? null,
                        'duracion_horas_rese' => $validated['duracion_horas_rese'] ?? null,
                        'invitados_rese' => $validated['invitados_rese'],
                        'presupuesto_rese' => $validated['presupuesto_rese'],
                        'ubicacion_rese' => $validated['ubicacion_rese'],
                        'Observaciones_rese' => $validated['Observaciones_rese'],
                        // El total lo define el administrador al revisar la reserva
                        // (PUT /reservas/{id}); el cliente no lo puede fijar.
                        'total_rese' => 0,
                        'estado_rese' => 'pendiente',
                        'creado_en_rese' => now(),
                        'nombre_rese' => isset($validated['nombre_rese']) && trim($validated['nombre_rese']) !== ''
                            ? trim($validated['nombre_rese'])
                            : null,
                        'id_u' => $request->user()->id_u,
                        'id_tipo_eves' => $validated['id_tipo_eves'], 
                        'id_er' => $validated['id_er'] ?? null, 
                    ]);

            // Ficha administrativa vacía: así la reserva llega a la bandeja del admin.
            $reserva->gestion()->create([
                'etapa_gestion' => 'nueva',
                'contactado' => false,
            ]);

            foreach ($validated['servicios_solicitados'] ?? [] as $s) {
                $nombre = trim((string) ($s['nombre_servicio'] ?? ''));

                if ($nombre === '' && !empty($s['id_s'])) {
                    $nombre = (string) Servicio::find($s['id_s'])?->nombre_s;
                }

                $reserva->servicios()->create([
                    'id_s' => $s['id_s'] ?? null,
                    'nombre_servicio' => $nombre,
                    'cantidad' => $s['cantidad'] ?? 1,
                    'origen' => 'cliente',
                    'estado' => 'pendiente',
                ]);
            }

            ReservaTrazabilidad::registrar($reserva, 'creacion', 'Reserva creada');

            return $reserva->load([
                'usuario',
                'evento',
                'seguimientos',
            ]);
        });

        // Aviso a los administradores sociales. Si falla, la reserva ya quedó creada.
        try {
            $cliente = trim($request->user()->nombre_u . ' ' . $request->user()->apellido_u);
            $tipo = $reserva->tipoEvento->nombre_tipo_eves ?? 'evento';

            User::whereHas('roles', fn ($q) => $q->where('nombre_rol', 'adminSocial'))
                ->pluck('id_u')
                ->each(fn ($idAdmin) => NotificacionService::crear(
                    (int) $idAdmin,
                    'Nueva reserva',
                    "{$cliente} solicitó una reserva de {$tipo} para el " . $reserva->fecha_evento_rese->format('d/m/Y') . '.',
                    'reserva_admin'
                ));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Reserva creada correctamente',
            'reserva' => $reserva
        ], 201);
    }

    /**
     * PUT /reservas/{id} (solo adminSocial). Se mantiene por compatibilidad,
     * pero ahora solo permite cambiar el estado y el total: los datos que
     * escribió el cliente (fecha, invitados, lugar...) no se pueden modificar.
     * La planificación se guarda con PUT /reservas/{id}/gestion.
     */
    public function update(Request $request, $id)
    {
        // Cambiar estado o total es trabajo del admin social. El cliente solo
        // puede renombrar su reserva (PUT /reservas/{id}/nombre).
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $validated = $request->validate([
            'total_rese' => 'sometimes|numeric|min:0|max:9999999999',
            'estado_rese' => 'sometimes|in:pendiente,confirmada,cancelada,completada',
        ]);

        $adminId = (int) $request->user()->id_u;

        $existe = DB::transaction(function () use ($id, $validated, $adminId) {
            $reserva = Reserva::lockForUpdate()->find($id);

            if (!$reserva) {
                return false;
            }

            if (isset($validated['total_rese'])) {
                $reserva->update(['total_rese' => $validated['total_rese']]);

                ReservaTrazabilidad::registrar(
                    $reserva,
                    'edicion',
                    'Total de la reserva actualizado.',
                    $adminId,
                    null,
                    null,
                    false
                );
            }

            if (isset($validated['estado_rese'])) {
                // Sin validar el flujo: conserva el comportamiento anterior.
                ReservaFlujo::cambiarEstado($reserva, $validated['estado_rese'], $adminId, null, null, false);
            }

            return true;
        });

        if (!$existe) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        return response()->json([
            'message' => 'Reserva actualizada correctamente',
            'reserva' => Reserva::with([
                'usuario',
                'evento',
                'seguimientos',
            ])->find($id)
        ]);
    }

    /**
     * El cliente le pone (o cambia) el nombre a su propia reserva.
     * Un nombre vacío lo quita.
     */
    public function renombrar(Request $request, $id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        if ((int) $reserva->id_u !== (int) $request->user()->id_u) {
            return response()->json([
                'message' => 'Solo puedes cambiar el nombre de tus propias reservas.'
            ], 403);
        }

        $validated = $request->validate([
            'nombre_rese' => 'nullable|string|max:80',
        ]);

        $nombre = trim($validated['nombre_rese'] ?? '');

        $reserva->nombre_rese = $nombre !== '' ? $nombre : null;
        $reserva->save();

        return response()->json([
            'message' => 'Nombre guardado correctamente',
            'reserva' => $reserva->only(['id_rese', 'nombre_rese']),
        ]);
    }

    /**
     * POST /reservas/{id}/ocultar
     *
     * Oculta (o vuelve a mostrar) la reserva en la lista de quien la pide:
     *  - vista=usuario (por defecto): solo el dueño; no afecta al admin.
     *  - vista=admin: solo un admin; no afecta al usuario.
     *  - oculta=false: la vuelve a mostrar.
     * Solo se pueden ocultar reservas canceladas o completadas.
     */
    public function ocultar(Request $request, $id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json(['message' => 'Reserva no encontrada'], 404);
        }

        $request->validate([
            'vista' => 'sometimes|in:usuario,admin',
            'oculta' => 'sometimes|boolean',
        ]);

        $vista = $request->input('vista', 'usuario');
        $oculta = $request->boolean('oculta', true);

        if ($vista === 'admin') {
            if (!$request->user()->hasRole('adminSocial')) {
                return response()->json(['message' => 'No tienes permisos.'], 403);
            }
            $columna = 'oculta_admin';
        } else {
            if ((int) $reserva->id_u !== (int) $request->user()->id_u) {
                return response()->json(['message' => 'Solo puedes ocultar tus propias reservas.'], 403);
            }
            $columna = 'oculta_usuario';
        }

        if ($oculta && !in_array($reserva->estado_rese, ['cancelada', 'completada'], true)) {
            return response()->json([
                'message' => 'Solo se pueden ocultar reservas canceladas o completadas.'
            ], 422);
        }

        $reserva->forceFill([$columna => $oculta])->save();

        return response()->json(['message' => $oculta ? 'Reserva oculta' : 'Reserva visible']);
    }


    /**
 * POST /reservas/{id}/cancelar (solo el dueño de la reserva).
 *
 * Solo se puede cancelar una reserva pendiente o confirmada cuyo evento
 * todavía no haya pasado. Body: motivo (opcional).
 */
public function cancelar(Request $request, $id)
{
    $validated = $request->validate([
        'motivo' => 'nullable|string|max:255',
    ]);

    $resultado = DB::transaction(function () use ($request, $id, $validated) {
        $reserva = Reserva::lockForUpdate()->find($id);

        if (!$reserva) {
            return ['http' => 404, 'message' => 'Reserva no encontrada'];
        }

        if ((int) $reserva->id_u !== (int) $request->user()->id_u) {
            return ['http' => 403, 'message' => 'Solo puedes cancelar tus propias reservas.'];
        }

        if (!in_array($reserva->estado_rese, ['pendiente', 'confirmada'], true)) {
            return ['http' => 422, 'message' => 'Esta reserva ya no se puede cancelar.'];
        }

        if ($reserva->fecha_evento_rese && $reserva->fecha_evento_rese->lt(today())) {
            return ['http' => 422, 'message' => 'El evento de esta reserva ya pasó.'];
        }

        $anterior = $reserva->estado_rese;

        $reserva->update(['estado_rese' => 'cancelada']);

        ReservaTrazabilidad::registrar(
            $reserva,
            'cambio_estado',
            'Estado cambiado a: cancelada',
            null,
            $anterior,
            'cancelada'
        );

        $motivo = trim((string) ($validated['motivo'] ?? ''));

        ReservaTrazabilidad::registrar(
            $reserva,
            'actualizacion',
            $motivo !== ''
                ? 'Cancelada por el cliente. Motivo: ' . $motivo
                : 'Cancelada por el cliente.'
        );

        return ['http' => 200, 'reserva' => $reserva];
    });

    if ($resultado['http'] !== 200) {
        return response()->json(['message' => $resultado['message']], $resultado['http']);
    }

    // Aviso a los administradores sociales (si falla, la cancelación ya quedó hecha).
    try {
        $cliente = trim($request->user()->nombre_u . ' ' . $request->user()->apellido_u);

        User::whereHas('roles', fn ($q) => $q->where('nombre_rol', 'adminSocial'))
            ->pluck('id_u')
            ->each(fn ($idAdmin) => NotificacionService::crear(
                (int) $idAdmin,
                'Reserva cancelada',
                "{$cliente} canceló la reserva #{$resultado['reserva']->id_rese}.",
                'reserva_admin'
            ));
    } catch (\Throwable $e) {
        report($e);
    }

    return response()->json([
        'message' => 'Tu reserva fue cancelada.',
        'reserva' => $resultado['reserva']->fresh()->load([
            'usuario',
            'evento',
            'seguimientos' => $this->historialCliente(),
        ]),
    ]);
}


    public function destroy(Request $request, $id)
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $reserva->delete();

        return response()->json([
            'message' => 'Reserva eliminada correctamente'
        ]);
    }

    /**
     * POST /reservas/{id}/seguimiento (solo adminSocial).
     * Mensaje que SÍ ve el cliente en el historial de su reserva.
     * Para una nota que el cliente no debe ver: POST /reservas/{id}/nota.
     */
    public function seguimiento(Request $request, $id)
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'comentario' => 'required|string|max:500',
        ]);

        $seguimiento = ReservaTrazabilidad::registrar(
            $reserva,
            'actualizacion',
            $validated['comentario'],
            (int) $request->user()->id_u
        );

        return response()->json([
            'message' => 'Seguimiento registrado correctamente',
            'seguimiento' => $seguimiento
        ], 201);
    }
}