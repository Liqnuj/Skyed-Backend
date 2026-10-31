<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\ReservaGestion;
use App\Models\ReservaServicio;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Ambiente;
use App\Services\NotificacionService;
use App\Services\ReservaFlujo;
use App\Services\ReservaTrazabilidad;
use App\Support\CamposEvento;
use App\Support\ReservaDetalle;
use App\Support\ReservaFechas;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Gestión de reservas y eventos por el Administrador Social.
 * Todas las acciones exigen el rol adminSocial (en la ruta y aquí otra vez).
 * Nunca modifican los datos que escribió el cliente en la tabla reserva.
 */
class ReservaGestionController extends Controller
{
    private const ETIQUETAS_CAMPOS = [
        'id_responsable' => 'responsable',
        'fecha_propuesta' => 'fecha propuesta',
        'fecha_confirmada' => 'fecha confirmada',
        'hora_inicio' => 'hora de inicio',
        'hora_fin' => 'hora de finalización',
        'fecha_limite_preparacion' => 'fecha límite de preparación',
        'id_a' => 'lugar confirmado',
        'asistentes_final' => 'asistentes finales',
        'distribucion_espacio' => 'distribución del espacio',
        'necesidades_especiales' => 'necesidades especiales',
        'requerimientos_adicionales' => 'requerimientos adicionales',
        'decoracion' => 'decoración',
        'logistica' => 'logística',
        'datos_tipo' => 'datos del tipo de evento',
    ];

    private const MENSAJES = [
        'fecha_propuesta.date' => 'La fecha propuesta no es válida.',
        'fecha_confirmada.date' => 'La fecha confirmada no es válida.',
        'fecha_limite_preparacion.date' => 'La fecha límite de preparación no es válida.',
        'hora_inicio.date_format' => 'La hora de inicio debe tener el formato HH:MM.',
        'hora_fin.date_format' => 'La hora de finalización debe tener el formato HH:MM.',
        'id_a.exists' => 'El lugar seleccionado no existe.',
        'asistentes_final.integer' => 'El número de asistentes debe ser un número entero.',
        'asistentes_final.min' => 'El número de asistentes debe ser al menos 1.',
        'asistentes_final.max' => 'El número de asistentes es demasiado alto.',
        'estado.in' => 'El estado no es válido.',
        'etapa.in' => 'La etapa no es válida.',
        'medio.in' => 'El medio de contacto no es válido.',
        'medio.required' => 'Indica por qué medio contactaste al usuario.',
        'nota.required' => 'Escribe la nota.',
        'proximo_contacto_en.after' => 'El próximo contacto debe ser una fecha futura.',
        'nombre_servicio.required_without' => 'Indica el servicio del catálogo o escribe su nombre.',
        'id_s.exists' => 'El servicio seleccionado no existe.',
        'cantidad.min' => 'La cantidad debe ser al menos 1.',
        'desde.required' => 'Indica la fecha inicial del calendario.',
        'hasta.required' => 'Indica la fecha final del calendario.',
        'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
    ];

    /* ------------------------------------------------------------ */
    /*  Consultas de apoyo                                            */
    /* ------------------------------------------------------------ */

    /** GET /reservas/responsables: administradores sociales que pueden ser responsables. */
    public function responsables(Request $request): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $admins = User::whereHas('roles', fn ($q) => $q->where('nombre_rol', 'adminSocial'))
            ->orderBy('nombre_u')
            ->get(['id_u', 'nombre_u', 'apellido_u']);

        return response()->json(['responsables' => $admins]);
    }

    /** GET /reservas/resumen: indicadores del panel principal (datos reales). */
    public function resumen(Request $request): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $hoy = ReservaFechas::hoy();
        $inicioSemana = Carbon::parse($hoy)->startOfWeek()->toDateString();
        $finSemana = Carbon::parse($hoy)->endOfWeek()->toDateString();
        $ahora = now();

        $activas = Reserva::with([
            'gestion',
            'tipoEvento:id_tipo_eves,nombre_tipo_eves',
            'usuario:id_u,nombre_u,apellido_u',
        ])
            ->where('oculta_admin', false)
            ->whereIn('estado_rese', ['pendiente', 'confirmada'])
            ->get();

        $nuevas = 0;
        $porContactar = 0;
        $hoyCount = 0;
        $semana = 0;
        $confirmadas = 0;
        $enPlanificacion = 0;
        $proximos = [];
        $alertas = [];

        foreach ($activas as $r) {
            $g = $r->asegurarGestion();
            $fecha = ReservaFechas::efectiva($r);
            $confirmada = $r->estado_rese === 'confirmada';
            $vigente = $fecha['fin'] >= $hoy;
            $nombreUsuario = trim(($r->usuario->nombre_u ?? '') . ' ' . ($r->usuario->apellido_u ?? '')) ?: 'el usuario';

            if ($r->estado_rese === 'pendiente' && $g->etapa_gestion === 'nueva') {
                $nuevas++;

                if ($r->creado_en_rese && $r->creado_en_rese->lt($ahora->copy()->subDay())) {
                    $alertas[] = ['tipo' => 'sin_revisar', 'nivel' => 'alta', 'id_rese' => $r->id_rese,
                        'mensaje' => "La reserva #{$r->id_rese} de {$nombreUsuario} lleva más de 24 horas sin revisarse."];
                }
            }

            $contactoVencido = $g->proximo_contacto_en && $g->proximo_contacto_en->lte($ahora);

            if (($r->estado_rese === 'pendiente' && ! $g->contactado) || $contactoVencido) {
                $porContactar++;
            }

            if ($contactoVencido) {
                $alertas[] = ['tipo' => 'contacto_vencido', 'nivel' => 'alta', 'id_rese' => $r->id_rese,
                    'mensaje' => "Tenías programado contactar a {$nombreUsuario} (reserva #{$r->id_rese})."];
            }

            if ($confirmada && $vigente) {
                $confirmadas++;

                if ($fecha['inicio'] <= $hoy && $fecha['fin'] >= $hoy) {
                    $hoyCount++;
                }

                if ($fecha['inicio'] <= $finSemana && $fecha['fin'] >= $inicioSemana) {
                    $semana++;
                }
            }

            if (in_array($g->etapa_gestion, ['en_planificacion', 'pendiente_confirmacion', 'reprogramada'], true)) {
                $enPlanificacion++;
            }

            $limite = $g->fecha_limite_preparacion?->toDateString();
            if ($limite && $vigente) {
                $dias = (int) Carbon::parse($hoy)->diffInDays(Carbon::parse($limite), false);

                if ($dias < 0) {
                    $alertas[] = ['tipo' => 'preparacion_vencida', 'nivel' => 'alta', 'id_rese' => $r->id_rese,
                        'mensaje' => "La fecha límite de preparación de la reserva #{$r->id_rese} ya pasó."];
                } elseif ($dias <= 3) {
                    $alertas[] = ['tipo' => 'preparacion_proxima', 'nivel' => 'media', 'id_rese' => $r->id_rese,
                        'mensaje' => "La preparación de la reserva #{$r->id_rese} vence en {$dias} día(s)."];
                }
            }

            if ($vigente) {
                $proximos[] = [
                    'id_rese' => $r->id_rese,
                    'titulo' => $this->titulo($r),
                    'fecha_inicio' => $fecha['inicio'],
                    'fecha_tipo' => $fecha['tipo'],
                    'estado_rese' => $r->estado_rese,
                    'estado_visual' => ReservaFlujo::estadoVisual($r),
                ];
            }
        }

        usort($proximos, fn ($a, $b) => [$a['fecha_inicio'], $a['id_rese']] <=> [$b['fecha_inicio'], $b['id_rese']]);

        usort($alertas, fn ($a, $b) => ($a['nivel'] === 'alta' ? 0 : 1) <=> ($b['nivel'] === 'alta' ? 0 : 1));

        $porEstado = Reserva::where('oculta_admin', false)
            ->select('estado_rese', DB::raw('count(*) as total'))
            ->groupBy('estado_rese')
            ->pluck('total', 'estado_rese');

        return response()->json([
            'resumen' => [
                'nuevas' => $nuevas,
                'por_contactar' => $porContactar,
                'eventos_hoy' => $hoyCount,
                'eventos_semana' => $semana,
                'confirmadas' => $confirmadas,
                'en_planificacion' => $enPlanificacion,
                'por_estado' => $porEstado,
                'proximos' => array_slice($proximos, 0, 5),
                'alertas' => array_slice($alertas, 0, 12),
            ],
        ]);
    }

    /** GET /reservas/calendario?desde=Y-m-d&hasta=Y-m-d (eventos que tocan ese rango). */
    public function calendario(Request $request): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $v = $request->validate([
            'desde' => 'required|date',
            'hasta' => 'required|date|after_or_equal:desde',
            'estado' => 'sometimes|in:pendiente,confirmada,cancelada,completada',
            'id_tipo_eves' => 'sometimes|integer',
        ], self::MENSAJES);

        $desde = Carbon::parse($v['desde'])->toDateString();
        $hasta = Carbon::parse($v['hasta'])->toDateString();

        if (Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) > 100) {
            return response()->json(['message' => 'El rango del calendario no puede superar 100 días.'], 422);
        }

        $query = ReservaFechas::unirGestion(Reserva::query())
            ->where('reserva.oculta_admin', false);

        ReservaFechas::aplicarRango($query, $desde, $hasta);

        if (isset($v['estado'])) {
            $query->where('reserva.estado_rese', $v['estado']);
        }

        if (isset($v['id_tipo_eves'])) {
            $query->where('reserva.id_tipo_eves', $v['id_tipo_eves']);
        }

        $reservas = $query
            ->with([
                'usuario:id_u,nombre_u,apellido_u',
                'tipoEvento:id_tipo_eves,nombre_tipo_eves',
                'gestion.responsable:id_u,nombre_u,apellido_u',
                'gestion.ambiente:id_a,nombre_a',
            ])
            ->orderBy('reserva.id_rese')
            ->get();

        $items = $reservas->map(function (Reserva $r) {
            $fecha = ReservaFechas::efectiva($r);
            $horas = ReservaFechas::horas($r);
            $g = $r->gestion;

            return [
                'id_rese' => $r->id_rese,
                'titulo' => $this->titulo($r),
                'fecha_inicio' => $fecha['inicio'],
                'fecha_fin' => $fecha['fin'],
                'fecha_tipo' => $fecha['tipo'],       // confirmada | propuesta | solicitada
                'aproximada' => $fecha['aproximada'],
                'hora_inicio' => $horas['hora_inicio'],
                'hora_fin' => $horas['hora_fin'],
                'estado_rese' => $r->estado_rese,
                'etapa_gestion' => $g?->etapa_gestion,
                'estado_visual' => ReservaFlujo::estadoVisual($r),
                'tipo_evento' => $r->tipoEvento
                    ? ['id_tipo_eves' => $r->tipoEvento->id_tipo_eves, 'nombre' => $r->tipoEvento->nombre_tipo_eves]
                    : null,
                'lugar' => $g?->ambiente?->nombre_a ?: $r->ubicacion_rese,
                'usuario' => trim(($r->usuario->nombre_u ?? '') . ' ' . ($r->usuario->apellido_u ?? '')),
                'invitados' => $g?->asistentes_final ?: $r->invitados_rese,
                'responsable' => $g?->responsable
                    ? trim($g->responsable->nombre_u . ' ' . $g->responsable->apellido_u)
                    : null,
            ];
        })->values();

        return response()->json(['desde' => $desde, 'hasta' => $hasta, 'eventos' => $items]);
    }

    /* ------------------------------------------------------------ */
    /*  Acciones sobre una reserva                                    */
    /* ------------------------------------------------------------ */

    /**
     * POST /reservas/{id}/abrir
     * El administrador abre la reserva: si todavía es nueva pasa a
     * "En revisión" y queda registrado. Se puede abrir las veces que se quiera.
     */
    public function abrir(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $cambio = false;

        $existe = DB::transaction(function () use ($request, $id, &$cambio) {
            $reserva = Reserva::lockForUpdate()->find($id);

            if (! $reserva) {
                return false;
            }

            $g = $reserva->asegurarGestion();

            if ($reserva->estado_rese === 'pendiente' && $g->etapa_gestion === 'nueva') {
                $g->update(['etapa_gestion' => 'en_revision']);

                ReservaTrazabilidad::registrar(
                    $reserva,
                    'cambio_etapa',
                    'Un integrante del equipo está revisando tu reserva.',
                    (int) $request->user()->id_u,
                    'nueva',
                    'en_revision'
                );

                $cambio = true;
            }

            return true;
        });

        if (! $existe) {
            return $this->noEncontrada();
        }

        return $this->respuesta((int) $id, $cambio ? 'La reserva pasó a «En revisión».' : 'Reserva consultada.');
    }

    /** PUT /reservas/{id}/gestion: guarda la información administrativa. */
    public function actualizar(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::with(['tipoEvento', 'gestion'])->find($id);

        if (! $reserva) {
            return $this->noEncontrada();
        }

        if (! ReservaFlujo::estaAbierta($reserva)) {
            return $this->cerrada();
        }

        $grupo = CamposEvento::grupoPara($reserva->tipoEvento?->nombre_tipo_eves);
        $validated = $request->validate($this->reglasGestion($grupo), self::MENSAJES);

        $this->validarCoherencia($reserva, $validated);

        $adminId = (int) $request->user()->id_u;

        $resultado = DB::transaction(function () use ($id, $validated, $adminId) {
            $reserva = Reserva::with('tipoEvento')->lockForUpdate()->find($id);

            if (! $reserva || ! ReservaFlujo::estaAbierta($reserva)) {
                return null;
            }

            $g = $reserva->asegurarGestion();
            $antes = [
                'fecha_confirmada' => $g->fecha_confirmada?->toDateString(),
                'fecha_propuesta' => $g->fecha_propuesta?->toDateString(),
            ];

            $g->fill($this->normalizar($validated));
            $sucios = array_keys($g->getDirty());

            if (! $sucios) {
                return ['cambio' => false];
            }

            $g->save();

            $confirmada = $reserva->estado_rese === 'confirmada';

            if (in_array('fecha_confirmada', $sucios, true)) {
                $nueva = $g->fecha_confirmada?->toDateString();
                $ant = $antes['fecha_confirmada'];

                if ($nueva) {
                    $texto = $ant
                        ? 'La fecha del evento cambió del ' . $this->fmt($ant) . ' al ' . $this->fmt($nueva) . '.'
                        : 'La fecha del evento quedó fijada para el ' . $this->fmt($nueva) . '.';

                    // Solo se le muestra al cliente cuando la reserva ya está confirmada.
                    ReservaTrazabilidad::registrar($reserva, 'cambio_fecha', $texto, $adminId, $ant, $nueva, $confirmada);

                    if ($confirmada) {
                        NotificacionService::crear((int) $reserva->id_u, 'Actualización de tu reserva', $texto, 'reserva');
                    }

                    if ($ant && $confirmada && $g->etapa_gestion !== 'reprogramada') {
                        $etapaAnterior = $g->etapa_gestion;
                        $g->update(['etapa_gestion' => 'reprogramada']);

                        ReservaTrazabilidad::registrar(
                            $reserva,
                            'cambio_etapa',
                            'Etapa: ' . (ReservaGestion::ETIQUETAS_ETAPA[$etapaAnterior] ?? $etapaAnterior) . ' → Reprogramada',
                            $adminId,
                            $etapaAnterior,
                            'reprogramada',
                            false
                        );
                    }
                } else {
                    ReservaTrazabilidad::registrar(
                        $reserva,
                        'cambio_fecha',
                        'Se quitó la fecha confirmada' . ($ant ? ' (era el ' . $this->fmt($ant) . ').' : '.'),
                        $adminId,
                        $ant,
                        null,
                        false
                    );
                }
            }

            if (in_array('fecha_propuesta', $sucios, true)) {
                $nueva = $g->fecha_propuesta?->toDateString();
                $ant = $antes['fecha_propuesta'];

                if ($nueva) {
                    $texto = 'Nuestro equipo te propone realizar el evento el ' . $this->fmt($nueva) . '.';

                    ReservaTrazabilidad::registrar($reserva, 'cambio_fecha', $texto, $adminId, $ant, $nueva, true);
                    NotificacionService::crear((int) $reserva->id_u, 'Propuesta de fecha para tu reserva', $texto, 'reserva');
                } else {
                    ReservaTrazabilidad::registrar($reserva, 'cambio_fecha', 'Se quitó la fecha propuesta.', $adminId, $ant, null, false);
                }
            }

            $otros = array_values(array_diff($sucios, ['fecha_confirmada', 'fecha_propuesta']));

            if ($otros) {
                $etiquetas = array_map(fn ($c) => self::ETIQUETAS_CAMPOS[$c] ?? $c, $otros);

                ReservaTrazabilidad::registrar(
                    $reserva,
                    'edicion',
                    'Planificación actualizada: ' . implode(', ', $etiquetas) . '.',
                    $adminId,
                    null,
                    null,
                    false
                );
            }

            return ['cambio' => true];
        });

        if ($resultado === null) {
            return $this->cerrada();
        }

        return $this->respuesta((int) $id, $resultado['cambio'] ? 'Planificación guardada.' : 'No hubo cambios que guardar.');
    }

    /** POST /reservas/{id}/etapa: cambia la etapa de planificación. */
    public function cambiarEtapa(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $v = $request->validate([
            'etapa' => ['required', Rule::in(ReservaGestion::ETAPAS)],
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ], self::MENSAJES);

        $adminId = (int) $request->user()->id_u;

        $r = DB::transaction(function () use ($id, $v, $adminId) {
            $reserva = Reserva::lockForUpdate()->find($id);

            if (! $reserva) {
                return ['http' => 404, 'message' => 'Reserva no encontrada'];
            }

            $error = ReservaFlujo::cambiarEtapa($reserva, $v['etapa'], $adminId, $v['nota_interna'] ?? null);

            return $error ? ['http' => 422, 'message' => $error] : ['http' => 200];
        });

        if ($r['http'] !== 200) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        return $this->respuesta((int) $id, 'Etapa actualizada.');
    }

    /** POST /reservas/{id}/estado: confirmar, finalizar o cancelar. */
    public function cambiarEstado(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $v = $request->validate([
            'estado' => 'required|in:confirmada,completada,cancelada',
            'comentario_publico' => 'sometimes|nullable|string|max:500',
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ], self::MENSAJES);

        $adminId = (int) $request->user()->id_u;

        $r = DB::transaction(function () use ($id, $v, $adminId) {
            $reserva = Reserva::with('tipoEvento')->lockForUpdate()->find($id);

            if (! $reserva) {
                return ['http' => 404, 'message' => 'Reserva no encontrada'];
            }

            $error = ReservaFlujo::cambiarEstado(
                $reserva,
                $v['estado'],
                $adminId,
                $v['comentario_publico'] ?? null,
                $v['nota_interna'] ?? null,
                true
            );

            return $error ? ['http' => 422, 'message' => $error] : ['http' => 200];
        });

        if ($r['http'] !== 200) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        return $this->respuesta((int) $id, 'Estado actualizado.');
    }

    /** POST /reservas/{id}/contacto: registra un contacto con el usuario. */
    public function registrarContacto(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $v = $request->validate([
            'medio' => ['required', Rule::in(ReservaGestion::MEDIOS_CONTACTO)],
            'observaciones' => 'nullable|string|max:1000',
            'compromisos' => 'nullable|string|max:1000',
            'proximo_contacto_en' => 'nullable|date|after:now',
        ], self::MENSAJES);

        $adminId = (int) $request->user()->id_u;

        $r = DB::transaction(function () use ($id, $v, $adminId) {
            $reserva = Reserva::lockForUpdate()->find($id);

            if (! $reserva) {
                return ['http' => 404, 'message' => 'Reserva no encontrada'];
            }

            if (! ReservaFlujo::estaAbierta($reserva)) {
                return ['http' => 422, 'message' => 'La reserva ya está cerrada.'];
            }

            $g = $reserva->asegurarGestion();
            $proximo = isset($v['proximo_contacto_en']) ? Carbon::parse($v['proximo_contacto_en']) : null;
            $compromisos = trim((string) ($v['compromisos'] ?? ''));

            $g->update([
                'contactado' => true,
                'ultimo_contacto_en' => now(),
                'medio_ultimo_contacto' => $v['medio'],
                // Registrar un contacto cierra el seguimiento anterior.
                'proximo_contacto_en' => $proximo,
                'compromisos' => $compromisos !== '' ? $compromisos : $g->compromisos,
            ]);

            $texto = 'Contacto por ' . str_replace('_', ' ', $v['medio']) . '.';
            $obs = trim((string) ($v['observaciones'] ?? ''));
            if ($obs !== '') {
                $texto .= ' ' . $obs;
            }
            if ($compromisos !== '') {
                $texto .= ' Compromisos: ' . $compromisos;
            }
            if ($proximo) {
                $texto .= ' Próximo contacto: ' . $proximo->format('d/m/Y H:i') . '.';
            }

            ReservaTrazabilidad::registrar($reserva, 'contacto', $texto, $adminId, null, null, false);

            // Un contacto significa que ya se está atendiendo.
            if (in_array($g->etapa_gestion, ['nueva', 'en_revision'], true)) {
                ReservaFlujo::cambiarEtapa($reserva, 'contactando_usuario', $adminId);
            }

            return ['http' => 200];
        });

        if ($r['http'] !== 200) {
            return response()->json(['message' => $r['message']], $r['http']);
        }

        return $this->respuesta((int) $id, 'Contacto registrado.');
    }

    /** POST /reservas/{id}/nota: nota interna (el cliente nunca la ve). */
    public function nota(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $v = $request->validate([
            'nota' => 'required|string|max:1000',
        ], self::MENSAJES);

        $reserva = Reserva::find($id);

        if (! $reserva) {
            return $this->noEncontrada();
        }

        ReservaTrazabilidad::registrar(
            $reserva,
            'nota_interna',
            trim($v['nota']),
            (int) $request->user()->id_u,
            null,
            null,
            false
        );

        return $this->respuesta((int) $id, 'Nota guardada.', 201);
    }

    /* ------------------------------------------------------------ */
    /*  Servicios de la reserva                                       */
    /* ------------------------------------------------------------ */

    public function agregarServicio(Request $request, $id): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::find($id);

        if (! $reserva) {
            return $this->noEncontrada();
        }

        if (! ReservaFlujo::estaAbierta($reserva)) {
            return $this->cerrada();
        }

        $v = $request->validate([
            'id_s' => 'nullable|integer|exists:servicio,id_s',
            'nombre_servicio' => 'required_without:id_s|nullable|string|max:100',
            'cantidad' => 'sometimes|integer|min:1|max:100000',
            'estado' => ['sometimes', Rule::in(ReservaServicio::ESTADOS)],
            'id_responsable' => ['sometimes', 'nullable', 'integer', $this->reglaResponsable()],
            'observaciones' => 'sometimes|nullable|string|max:255',
        ], self::MENSAJES);

        $nombre = trim((string) ($v['nombre_servicio'] ?? ''));

        if ($nombre === '' && ! empty($v['id_s'])) {
            $nombre = (string) Servicio::find($v['id_s'])?->nombre_s;
        }

        $servicio = $reserva->servicios()->create([
            'id_s' => $v['id_s'] ?? null,
            'nombre_servicio' => $nombre,
            'cantidad' => $v['cantidad'] ?? 1,
            'origen' => 'admin',
            'estado' => $v['estado'] ?? 'pendiente',
            'id_responsable' => $v['id_responsable'] ?? null,
            'observaciones' => $v['observaciones'] ?? null,
        ]);

        ReservaTrazabilidad::registrar(
            $reserva,
            'servicio',
            "Servicio agregado: {$servicio->nombre_servicio} (x{$servicio->cantidad}).",
            (int) $request->user()->id_u,
            null,
            null,
            false
        );

        return $this->respuesta((int) $id, 'Servicio agregado.', 201);
    }

    public function actualizarServicio(Request $request, $id, $servicioId): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::find($id);

        if (! $reserva) {
            return $this->noEncontrada();
        }

        if (! ReservaFlujo::estaAbierta($reserva)) {
            return $this->cerrada();
        }

        $servicio = ReservaServicio::where('id_rese', $reserva->id_rese)->where('id_rs', $servicioId)->first();

        if (! $servicio) {
            return response()->json(['message' => 'Servicio de la reserva no encontrado'], 404);
        }

        $v = $request->validate([
            'nombre_servicio' => 'sometimes|string|min:1|max:100',
            'cantidad' => 'sometimes|integer|min:1|max:100000',
            'estado' => ['sometimes', Rule::in(ReservaServicio::ESTADOS)],
            'id_responsable' => ['sometimes', 'nullable', 'integer', $this->reglaResponsable()],
            'observaciones' => 'sometimes|nullable|string|max:255',
        ], self::MENSAJES);

        $estadoAnterior = $servicio->estado;
        $servicio->fill($v);

        if (! $servicio->isDirty()) {
            return $this->respuesta((int) $id, 'No hubo cambios que guardar.');
        }

        $servicio->save();

        $texto = $estadoAnterior !== $servicio->estado
            ? "Servicio {$servicio->nombre_servicio}: {$estadoAnterior} → {$servicio->estado}."
            : "Servicio actualizado: {$servicio->nombre_servicio}.";

        ReservaTrazabilidad::registrar($reserva, 'servicio', $texto, (int) $request->user()->id_u, null, null, false);

        return $this->respuesta((int) $id, 'Servicio actualizado.');
    }

    public function quitarServicio(Request $request, $id, $servicioId): JsonResponse
    {
        if ($denegado = $this->exigirRol($request, 'adminSocial')) {
            return $denegado;
        }

        $reserva = Reserva::find($id);

        if (! $reserva) {
            return $this->noEncontrada();
        }

        if (! ReservaFlujo::estaAbierta($reserva)) {
            return $this->cerrada();
        }

        $servicio = ReservaServicio::where('id_rese', $reserva->id_rese)->where('id_rs', $servicioId)->first();

        if (! $servicio) {
            return response()->json(['message' => 'Servicio de la reserva no encontrado'], 404);
        }

        $nombre = $servicio->nombre_servicio;
        $servicio->delete();

        ReservaTrazabilidad::registrar($reserva, 'servicio', "Servicio quitado: {$nombre}.", (int) $request->user()->id_u, null, null, false);

        return $this->respuesta((int) $id, 'Servicio quitado.');
    }

    /* ------------------------------------------------------------ */
    /*  Utilidades internas                                           */
    /* ------------------------------------------------------------ */

    private function reglasGestion(string $grupo): array
    {
        $hora = 'sometimes|nullable|date_format:H:i,H:i:s';
        $texto = fn (int $max) => 'sometimes|nullable|string|max:' . $max;

        return array_merge([
            'id_responsable' => ['sometimes', 'nullable', 'integer', $this->reglaResponsable()],
            'fecha_propuesta' => 'sometimes|nullable|date',
            'fecha_confirmada' => 'sometimes|nullable|date',
            'hora_inicio' => $hora,
            'hora_fin' => $hora,
            'fecha_limite_preparacion' => 'sometimes|nullable|date',
            'id_a' => 'sometimes|nullable|integer|exists:ambiente,id_a',
            'asistentes_final' => 'sometimes|nullable|integer|min:1|max:100000',
            'distribucion_espacio' => $texto(2000),
            'necesidades_especiales' => $texto(2000),
            'requerimientos_adicionales' => $texto(2000),

            'decoracion' => 'sometimes|nullable|array',
            'decoracion.requiere' => 'nullable|boolean',
            'decoracion.tipo' => 'nullable|string|max:100',
            'decoracion.tematica' => 'nullable|string|max:100',
            'decoracion.colores' => 'nullable|string|max:100',
            'decoracion.elementos' => 'nullable|string|max:500',
            'decoracion.montaje' => 'nullable|string|max:255',
            'decoracion.observaciones' => 'nullable|string|max:500',
            'decoracion.estado' => 'nullable|in:pendiente,en_proceso,listo,no_aplica',

            'logistica' => 'sometimes|nullable|array',
            'logistica.montaje' => 'nullable|string|max:500',
            'logistica.desmontaje' => 'nullable|string|max:500',
            'logistica.personal' => 'nullable|string|max:500',
            'logistica.equipos' => 'nullable|string|max:500',
            'logistica.materiales' => 'nullable|string|max:500',
            'logistica.transporte' => 'nullable|string|max:500',
            'logistica.especiales' => 'nullable|string|max:500',

            'datos_tipo' => 'sometimes|nullable|array',
        ], CamposEvento::reglas($grupo));
    }

    /** El responsable debe ser un administrador social. */
    private function reglaResponsable(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            if ($valor === null) {
                return;
            }

            $usuario = User::find($valor);

            if (! $usuario || ! $usuario->hasRole('adminSocial')) {
                $fallar('El responsable debe ser un administrador social.');
            }
        };
    }

    /**
     * Reglas que dependen de varios campos a la vez y de lo ya guardado.
     * Las fechas pasadas solo se rechazan si el valor CAMBIA (así se puede
     * guardar una reserva antigua sin tocar sus fechas).
     */
    private function validarCoherencia(Reserva $reserva, array $v): void
    {
        $g = $reserva->asegurarGestion();
        $hoy = ReservaFechas::hoy();
        $errores = [];

        foreach (['fecha_confirmada', 'fecha_propuesta'] as $campo) {
            if (! array_key_exists($campo, $v) || $v[$campo] === null) {
                continue;
            }

            $nueva = Carbon::parse($v[$campo])->toDateString();
            $actual = $g->{$campo}?->toDateString();

            if ($nueva !== $actual && $nueva < $hoy) {
                $errores[$campo] = $campo === 'fecha_confirmada'
                    ? 'La fecha confirmada no puede ser anterior a hoy.'
                    : 'La fecha propuesta no puede ser anterior a hoy.';
            }
        }

        $inicio = array_key_exists('hora_inicio', $v) ? $v['hora_inicio'] : $g->hora_inicio;
        $fin = array_key_exists('hora_fin', $v) ? $v['hora_fin'] : $g->hora_fin;

        if ($inicio && $fin && substr((string) $fin, 0, 5) <= substr((string) $inicio, 0, 5)) {
            $errores['hora_fin'] = 'La hora de finalización debe ser posterior a la hora de inicio.';
        }

        $limite = array_key_exists('fecha_limite_preparacion', $v)
            ? $v['fecha_limite_preparacion']
            : $g->fecha_limite_preparacion?->toDateString();

        $confirmada = array_key_exists('fecha_confirmada', $v) ? $v['fecha_confirmada'] : $g->fecha_confirmada?->toDateString();
        $propuesta = array_key_exists('fecha_propuesta', $v) ? $v['fecha_propuesta'] : $g->fecha_propuesta?->toDateString();
        $fechaEvento = $confirmada ?: ($propuesta ?: $reserva->fecha_evento_rese?->toDateString());

        if ($limite && $fechaEvento && Carbon::parse($limite)->toDateString() > Carbon::parse($fechaEvento)->toDateString()) {
            $errores['fecha_limite_preparacion'] = 'La fecha límite de preparación no puede ser posterior a la fecha del evento.';
        }

        $asistentes = array_key_exists('asistentes_final', $v) ? $v['asistentes_final'] : $g->asistentes_final;
        $idAmbiente = array_key_exists('id_a', $v) ? $v['id_a'] : $g->id_a;

        if ($asistentes && $idAmbiente) {
            $capacidad = Ambiente::find($idAmbiente)?->capacidad_a;

            if ($capacidad && $asistentes > $capacidad) {
                $errores['asistentes_final'] = "Los asistentes ({$asistentes}) superan la capacidad del lugar ({$capacidad}).";
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    /** Limpia espacios y deja las horas como HH:MM:SS antes de guardar. */
    private function normalizar(array $v): array
    {
        foreach (['hora_inicio', 'hora_fin'] as $campo) {
            if (isset($v[$campo]) && strlen($v[$campo]) === 5) {
                $v[$campo] .= ':00';
            }
        }

        foreach (['distribucion_espacio', 'necesidades_especiales', 'requerimientos_adicionales'] as $campo) {
            if (isset($v[$campo]) && is_string($v[$campo])) {
                $v[$campo] = trim($v[$campo]) !== '' ? trim($v[$campo]) : null;
            }
        }

        return $v;
    }

    private function titulo(Reserva $r): string
    {
        if ($r->nombre_rese) {
            return $r->nombre_rese;
        }

        $tipo = $r->tipoEvento->nombre_tipo_eves ?? 'Evento';
        $nombre = trim($r->usuario->nombre_u ?? '');

        return $nombre !== '' ? "{$tipo} · {$nombre}" : $tipo;
    }

    private function fmt(string $fecha): string
    {
        return Carbon::parse($fecha)->format('d/m/Y');
    }

    private function respuesta(int $id, string $mensaje, int $http = 200): JsonResponse
    {
        $reserva = Reserva::with(ReservaDetalle::relacionesAdmin())->find($id);

        return response()->json([
            'message' => $mensaje,
            'reserva' => $reserva,
            'meta' => ReservaDetalle::meta($reserva),
        ], $http);
    }

    private function noEncontrada(): JsonResponse
    {
        return response()->json(['message' => 'Reserva no encontrada'], 404);
    }

    private function cerrada(): JsonResponse
    {
        return response()->json(['message' => 'La reserva ya está cerrada (cancelada o finalizada) y no se puede modificar.'], 422);
    }
}