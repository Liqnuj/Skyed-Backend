<?php

namespace App\Http\Controllers;

use App\Models\Pqr;
use App\Models\PqrSeguimiento;
use App\Services\NotificacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PqrController extends Controller
{
    /**
     * Listar PQR. Un admin social ve todas; un usuario normal solo ve
     * las suyas. Un admin puede pasar ?mias=1 para ver únicamente las
     * que él mismo radicó (lo usa su pestaña "Mis PQR" del perfil).
     *
     * Filtros opcionales: ?estado=, ?tipo=, ?per_page= (máx. 100).
     */
    public function index(Request $request)
    {
        $esAdmin = $this->esAdmin($request);

        $request->validate([
            'estado' => ['sometimes', Rule::in(Pqr::ESTADOS)],
            'tipo' => ['sometimes', Rule::in(Pqr::TIPOS)],
        ]);

        $query = Pqr::with('usuario:id_u,nombre_u,apellido_u,correo_u,telefono_u')
            // Fecha de la última actuación visible para quien consulta.
            ->withMax(['seguimientos as ultima_actuacion_en' => function ($q) use ($esAdmin) {
                if (!$esAdmin) {
                    $q->where('tipo_evento', '!=', 'nota_interna');
                }
            }], 'created_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id_pqr');

        $ocultas = $request->boolean('ocultas');

        if (!$esAdmin || $request->boolean('mias')) {
            $query->where('id_u', $request->user()->id_u)
                ->where('oculta_usuario', $ocultas);
        } else {
            $query->where('oculta_admin', $ocultas);
        }

        if ($request->filled('estado')) {
            $query->where('estado_pqr', $request->input('estado'));
        }

        if ($request->filled('tipo')) {
            $query->where('tipo_pqr', $request->input('tipo'));
        }

        $porPagina = min(100, max(1, (int) $this->perPage($request)));
        $pagina = $query->paginate($porPagina);

        $respuesta = $pagina->toArray();

        $respuesta['data'] = collect($pagina->items())
            ->map(fn (Pqr $pqr) => $this->presentar($pqr, $esAdmin))
            ->all();

        if ($esAdmin && !$request->boolean('mias')) {
            // Conteo por fase de TODAS las PQR (no solo de la página).
            $respuesta['resumen'] = Pqr::query()
                ->where('oculta_admin', false)
                ->select('estado_pqr', DB::raw('count(*) as total'))
                ->groupBy('estado_pqr')
                ->pluck('total', 'estado_pqr');
        }

        return response()->json($respuesta);
    }

    /**
     * Mostrar una PQR con su seguimiento completo. Un usuario normal
     * solo puede ver la suya y nunca recibe las notas internas.
     */
    public function show(Request $request, $id)
    {
        $pqr = Pqr::find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        $esDueno = (int) $pqr->id_u === (int) $request->user()->id_u;
        $esAdmin = $this->esAdmin($request);

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta PQR'
            ], 403);
        }

        return response()->json([
            'pqr' => $this->cargarYPresentar($pqr, $esAdmin)
        ]);
    }

    /**
     * Crear una PQR. Queda radicada en fase "abierto" y se registra
     * el primer evento del seguimiento.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_pqr' => ['required', Rule::in(Pqr::TIPOS)],
            'asunto_pqr' => 'required|string|max:150',
            // El formulario limita a 1000 caracteres; sin max se podían
            // guardar textos de varios MB llamando a la API directamente.
            'mensaje_pqr' => 'required|string|max:2000',
        ]);

        $pqr = DB::transaction(function () use ($validated, $request) {
            $pqr = Pqr::create([
                'tipo_pqr' => $validated['tipo_pqr'],
                'asunto_pqr' => $validated['asunto_pqr'],
                'mensaje_pqr' => $validated['mensaje_pqr'],
                'estado_pqr' => 'abierto',
                'respuesta_pqr' => null,
                'respondido_en_pqr' => null,
                'creado_en_pqr' => now(),
                'id_u' => $request->user()->id_u,
            ]);

            $pqr->seguimientos()->create([
                'id_admin' => null,
                'estado_anterior' => null,
                'estado_nuevo' => 'abierto',
                'tipo_evento' => 'radicacion',
                'comentario_publico' => 'Tu solicitud fue radicada y está '
                    . 'en la cola de revisión de nuestro equipo.',
            ]);

            return $pqr;
        });

        return response()->json([
            'message' => 'PQR creada correctamente',
            'pqr' => $this->cargarYPresentar($pqr, false)
        ], 201);
    }

    /**
     * Compatibilidad: PUT /pqr/{id}. Hace lo mismo que
     * POST /pqr/{id}/seguimiento (cambia de fase y/o responde), así que
     * el cambio también queda en el historial del usuario.
     */
    public function update(Request $request, $id)
    {
        return $this->registrarActuacion($request, $id, 200);
    }

    /**
     * POST /pqr/{id}/seguimiento (solo adminSocial).
     *
     * Registra una actuación sobre la PQR. Se puede enviar cualquier
     * combinación de:
     *  - estado_pqr:         nueva fase
     *  - comentario_publico: mensaje que ve el usuario en su seguimiento
     *  - respuesta_pqr:      respuesta oficial (obligatoria al resolver)
     *  - nota_interna:       nota que solo ven los administradores
     */
    public function seguimiento(Request $request, $id)
    {
        return $this->registrarActuacion($request, $id, 201);
    }


    
    /**
     * POST /pqr/{id}/abrir (solo adminSocial).
     *
     * El administrador abre el detalle de una PQR: si todavía está en
     * "abierto" pasa automáticamente a "en_revision". Si ya estaba más
     * adelante no cambia nada (se puede abrir las veces que se quiera).
     */
    public function abrir(Request $request, $id): JsonResponse
    {
        if (!$this->esAdmin($request)) {
            return response()->json([
                'message' => 'No tienes permisos para gestionar PQR.'
            ], 403);
        }

        $cambio = false;
        $texto = 'Un integrante del equipo abrió tu solicitud y la está revisando.';

        $pqr = DB::transaction(function () use ($request, $id, $texto, &$cambio) {
            $pqr = Pqr::lockForUpdate()->find($id);

            if ($pqr && $pqr->estado_pqr === 'abierto') {
                $this->aplicarTransicion(
                    $pqr,
                    (int) $request->user()->id_u,
                    'en_revision',
                    'cambio_estado',
                    $texto
                );
                $cambio = true;
            }

            return $pqr;
        });

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        if ($cambio) {
            $this->notificarUsuario($pqr, true, 'en_revision', $texto);
        }

        return response()->json([
            'message' => $cambio ? 'La PQR pasó a «En revisión».' : 'PQR consultada.',
            'cambio' => $cambio,
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ]);
    }

    /**
     * POST /pqr/{id}/iniciar-gestion (solo adminSocial).
     *
     * Solo válido desde "en_revision": la PQR pasa a "en_proceso".
     * Opcional: comentario_publico (lo ve el usuario) y nota_interna.
     */
    public function iniciarGestion(Request $request, $id): JsonResponse
    {
        if (!$this->esAdmin($request)) {
            return response()->json([
                'message' => 'No tienes permisos para gestionar PQR.'
            ], 403);
        }

        $validated = $request->validate([
            'comentario_publico' => 'sometimes|nullable|string|max:1000',
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ]);

        $texto = $this->textoOpcional($validated['comentario_publico'] ?? null)
            ?? 'Ya estamos gestionando tu solicitud. Te avisaremos cuando tengamos una respuesta.';
        $nota = $this->textoOpcional($validated['nota_interna'] ?? null);

        $resultado = DB::transaction(function () use ($request, $id, $texto, $nota) {
            $pqr = Pqr::lockForUpdate()->find($id);

            if (!$pqr) {
                return ['http' => 404, 'message' => 'PQR no encontrada'];
            }

            if (!in_array('en_proceso', Pqr::TRANSICIONES[$pqr->estado_pqr] ?? [], true)) {
                return [
                    'http' => 422,
                    'message' => $this->mensajeTransicion($pqr->estado_pqr, 'en_proceso'),
                ];
            }

            $this->aplicarTransicion(
                $pqr,
                (int) $request->user()->id_u,
                'en_proceso',
                'cambio_estado',
                $texto,
                $nota
            );

            return ['http' => 200, 'pqr' => $pqr];
        });

        if ($resultado['http'] !== 200) {
            return response()->json(['message' => $resultado['message']], $resultado['http']);
        }

        $pqr = $resultado['pqr'];
        $this->notificarUsuario($pqr, true, 'en_proceso', $texto);

        return response()->json([
            'message' => 'La PQR pasó a «En proceso».',
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ]);
    }

    /**
     * POST /pqr/{id}/resolver (solo adminSocial).
     *
     * Solo válido desde "en_proceso". Guarda la respuesta oficial, la
     * fecha, el administrador responsable y pasa la PQR a "resuelto".
     * Body: respuesta (obligatoria, 10-2000 caracteres), nota_interna (opcional).
     */
    public function resolver(Request $request, $id): JsonResponse
    {
        if (!$this->esAdmin($request)) {
            return response()->json([
                'message' => 'No tienes permisos para gestionar PQR.'
            ], 403);
        }

        $validated = $request->validate([
            'respuesta' => 'required|string|min:10|max:2000',
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ], [
            'respuesta.required' => 'Escribe la respuesta que recibirá el usuario.',
            'respuesta.min' => 'La respuesta debe tener al menos 10 caracteres.',
            'respuesta.max' => 'La respuesta no puede superar los 2000 caracteres.',
        ]);

        $respuesta = $this->textoOpcional($validated['respuesta']);
        $nota = $this->textoOpcional($validated['nota_interna'] ?? null);

        if (!$respuesta || mb_strlen($respuesta) < 10) {
            return $this->rechazo('La respuesta debe tener al menos 10 caracteres.');
        }

        $resultado = DB::transaction(function () use ($request, $id, $respuesta, $nota) {
            $pqr = Pqr::lockForUpdate()->find($id);

            if (!$pqr) {
                return ['http' => 404, 'message' => 'PQR no encontrada'];
            }

            if (!in_array('resuelto', Pqr::TRANSICIONES[$pqr->estado_pqr] ?? [], true)) {
                return [
                    'http' => 422,
                    'message' => $this->mensajeTransicion($pqr->estado_pqr, 'resuelto'),
                ];
            }

            $this->aplicarTransicion(
                $pqr,
                (int) $request->user()->id_u,
                'resuelto',
                'respuesta',
                $respuesta,
                $nota,
                $respuesta
            );

            return ['http' => 200, 'pqr' => $pqr];
        });

        if ($resultado['http'] !== 200) {
            return response()->json(['message' => $resultado['message']], $resultado['http']);
        }

        $pqr = $resultado['pqr'];
        $this->notificarUsuario($pqr, true, 'resuelto', $respuesta);

        return response()->json([
            'message' => 'Respuesta enviada. La PQR quedó «Resuelta».',
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ]);
    }

    /**
     * Cambia la fase y deja el rastro en pqr_seguimientos (con el admin
     * responsable). Debe llamarse dentro de una transacción.
     */
    private function aplicarTransicion(
        Pqr $pqr,
        int $idAdmin,
        string $estadoNuevo,
        string $tipoEvento,
        ?string $textoPublico,
        ?string $nota = null,
        ?string $respuesta = null
    ): void {
        $estadoAnterior = $pqr->estado_pqr;

        $datos = ['estado_pqr' => $estadoNuevo];

        if ($respuesta !== null) {
            $datos['respuesta_pqr'] = $respuesta;
            $datos['respondido_en_pqr'] = now();
        }

        $pqr->update($datos);

        $pqr->seguimientos()->create([
            'id_admin' => $idAdmin,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'tipo_evento' => $tipoEvento,
            'comentario_publico' => $textoPublico,
            'nota_interna' => $nota,
        ]);
    }

    private function mensajeTransicion(string $actual, string $destino): string
    {
        $de = Pqr::ETIQUETAS_ESTADO[$actual] ?? $actual;
        $a = Pqr::ETIQUETAS_ESTADO[$destino] ?? $destino;

        return 'No se puede pasar de «' . $de . '» a «' . $a . '». '
            . 'El orden es: Enviado → En revisión → En proceso → Resuelto.';
    }

    

    /**
     * POST /pqr/{id}/responder (solo el dueño de la PQR).
     *
     * El usuario escribe un mensaje más en su propia PQR mientras no esté
     * resuelta ni cerrada. Queda en el historial como "respuesta_cliente".
     */
    public function responder(Request $request, $id)
    {
        $pqr = Pqr::find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        // Solo la persona que radicó la solicitud puede escribir en ella.
        if ((int) $pqr->id_u !== (int) $request->user()->id_u) {
            return response()->json([
                'message' => 'Solo puedes responder en tus propias solicitudes.'
            ], 403);
        }

        if (in_array($pqr->estado_pqr, Pqr::ESTADOS_FINALES, true)) {
            return $this->rechazo(
                'Esta solicitud ya fue resuelta o cerrada. '
                . 'Si necesitas algo más, radica una nueva PQR.'
            );
        }

        $validated = $request->validate([
            'mensaje' => 'required|string|max:2000',
        ]);

        $mensaje = $this->textoOpcional($validated['mensaje']);

        if (!$mensaje) {
            return $this->rechazo('Escribe tu mensaje.');
        }

        $seguimiento = $pqr->seguimientos()->create([
            'id_admin' => null,
            'estado_anterior' => $pqr->estado_pqr,
            'estado_nuevo' => $pqr->estado_pqr,
            'tipo_evento' => 'respuesta_cliente',
            'comentario_publico' => $mensaje,
            'nota_interna' => null,
        ]);

        return response()->json([
            'message' => 'Tu mensaje fue enviado.',
            'seguimiento' => $this->presentarSeguimiento($seguimiento, false),
        ], 201);
    }

    /**
     * POST /pqr/{id}/abrir (solo adminSocial).
     *
     * El administrador abrió el detalle. Si la PQR estaba "abierto" (Radicada)
     * pasa a "en_revision" y el usuario recibe el aviso. En cualquier otra
     * fase no cambia nada. Responde { pqr, cambio }.
     */
    public function abrir(Request $request, $id)
    {
        $pqr = $this->pqrParaAdmin($request, $id);
        if ($pqr instanceof JsonResponse) {
            return $pqr;
        }

        $cambio = false;

        if ($pqr->estado_pqr === 'abierto') {
            $this->cambiarFase($request, $pqr, 'en_revision', 'cambio_estado');
            $cambio = true;
        }

        return response()->json([
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
            'cambio' => $cambio,
        ]);
    }

    /**
     * POST /pqr/{id}/iniciar-gestion (solo adminSocial): "en_revision" -> "en_proceso".
     */
    public function iniciarGestion(Request $request, $id)
    {
        $pqr = $this->pqrParaAdmin($request, $id);
        if ($pqr instanceof JsonResponse) {
            return $pqr;
        }

        if ($pqr->estado_pqr !== 'en_revision') {
            return $this->rechazo('Solo una PQR en revisión puede pasar a "En proceso".');
        }

        $this->cambiarFase($request, $pqr, 'en_proceso', 'cambio_estado');

        return response()->json([
            'message' => 'La PQR quedó en proceso.',
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ]);
    }

    /**
     * POST /pqr/{id}/resolver (solo adminSocial): guarda la respuesta oficial y
     * pasa "en_proceso" -> "resuelto". Body: respuesta (obligatoria), nota_interna.
     */
    public function resolver(Request $request, $id)
    {
        $pqr = $this->pqrParaAdmin($request, $id);
        if ($pqr instanceof JsonResponse) {
            return $pqr;
        }

        $validated = $request->validate([
            'respuesta' => 'required|string|max:2000',
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ]);

        if ($pqr->estado_pqr !== 'en_proceso') {
            return $this->rechazo('Solo una PQR en proceso puede marcarse como resuelta.');
        }

        $respuesta = $this->textoOpcional($validated['respuesta']);
        if (!$respuesta) {
            return $this->rechazo('Escribe la respuesta que recibirá el usuario.');
        }

        $this->cambiarFase(
            $request,
            $pqr,
            'resuelto',
            'respuesta',
            $respuesta,
            $this->textoOpcional($validated['nota_interna'] ?? null)
        );

        return response()->json([
            'message' => 'PQR resuelta.',
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ]);
    }

    /** Busca la PQR y exige ser adminSocial; devuelve la PQR o una respuesta de error. */
    private function pqrParaAdmin(Request $request, $id): Pqr|JsonResponse
    {
        if (!$this->esAdmin($request)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return Pqr::find($id) ?? response()->json(['message' => 'PQR no encontrada'], 404);
    }

    /** Cambia de fase, deja el paso en el seguimiento y avisa al usuario. */
    private function cambiarFase(
        Request $request,
        Pqr $pqr,
        string $estadoNuevo,
        string $tipoEvento,
        ?string $textoPublico = null,
        ?string $nota = null
    ): void {
        $estadoActual = $pqr->estado_pqr;

        DB::transaction(function () use ($request, $pqr, $estadoActual, $estadoNuevo, $tipoEvento, $textoPublico, $nota) {
            $datos = ['estado_pqr' => $estadoNuevo];

            if ($tipoEvento === 'respuesta' && $textoPublico) {
                $datos['respuesta_pqr'] = $textoPublico;
                $datos['respondido_en_pqr'] = now();
            }

            $pqr->update($datos);

            $pqr->seguimientos()->create([
                'id_admin' => $request->user()->id_u,
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $estadoNuevo,
                'tipo_evento' => $tipoEvento,
                'comentario_publico' => $textoPublico,
                'nota_interna' => $nota,
            ]);
        });

        $this->notificarUsuario($pqr, true, $estadoNuevo, $textoPublico);
    }

    private function registrarActuacion(Request $request, $id, int $status): JsonResponse
    {
        // Antes la ruta quedaba abierta a cualquier usuario con sesión y aquí
        // no se revisaba nada: se exige el rol también dentro del controlador.
        if (!$this->esAdmin($request)) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        $pqr = Pqr::find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'estado_pqr' => ['sometimes', Rule::in(Pqr::ESTADOS)],
            'comentario_publico' => 'sometimes|nullable|string|max:1000',
            'respuesta_pqr' => 'sometimes|nullable|string|max:2000',
            'nota_interna' => 'sometimes|nullable|string|max:1000',
        ]);

        $estadoActual = $pqr->estado_pqr;
        $estadoNuevo = $validated['estado_pqr'] ?? $estadoActual;
        $cambiaEstado = $estadoNuevo !== $estadoActual;

        $comentario = $this->textoOpcional($validated['comentario_publico'] ?? null);
        $respuesta = $this->textoOpcional($validated['respuesta_pqr'] ?? null);
        $nota = $this->textoOpcional($validated['nota_interna'] ?? null);

        if (!$cambiaEstado && !$comentario && !$respuesta && !$nota) {
            return $this->rechazo('No hay ningún cambio ni mensaje para registrar.');
        }

        if (
            in_array($estadoActual, Pqr::ESTADOS_FINALES, true)
            && ($cambiaEstado || $comentario || $respuesta)
        ) {
            return $this->rechazo(
                'Esta PQR ya fue resuelta. Solo se pueden agregar notas internas.'
            );
        }

        if ($cambiaEstado && $estadoNuevo === 'abierto') {
            return $this->rechazo(
                'Una PQR ya radicada no puede volver a la fase "Radicada".'
            );
        }

        
        if (
            $cambiaEstado
            && !in_array($estadoNuevo, Pqr::TRANSICIONES[$estadoActual] ?? [], true)
        ) {
            return $this->rechazo($this->mensajeTransicion($estadoActual, $estadoNuevo));
        }

        if ($respuesta && !($cambiaEstado && $estadoNuevo === 'resuelto')) {
            return $this->rechazo(
                'La respuesta oficial solo se registra al resolver la PQR.'
            );
        }


        if (
            $cambiaEstado
            && $estadoNuevo === 'resuelto'
            && !$respuesta
            && !$pqr->respuesta_pqr
        ) {
            return $this->rechazo(
                'Para marcar la PQR como resuelta escribe la respuesta '
                . 'que recibirá el usuario.'
            );
        }

        $tipoEvento = match (true) {
            (bool) $respuesta => 'respuesta',
            $cambiaEstado => 'cambio_estado',
            (bool) $comentario => 'comentario',
            default => 'nota_interna',
        };

        // Texto que ve el usuario en este paso del seguimiento.
        $textoPublico = implode("\n\n", array_filter([$respuesta, $comentario])) ?: null;

        DB::transaction(function () use (
            $pqr, $request, $estadoActual, $estadoNuevo, $cambiaEstado,
            $respuesta, $textoPublico, $nota, $tipoEvento
        ) {
            $datos = [];

            if ($cambiaEstado) {
                $datos['estado_pqr'] = $estadoNuevo;
            }

            if ($respuesta) {
                $datos['respuesta_pqr'] = $respuesta;
                $datos['respondido_en_pqr'] = now();
            }

            if ($datos) {
                $pqr->update($datos);
            }

            $pqr->seguimientos()->create([
                'id_admin' => $request->user()->id_u,
                'estado_anterior' => $estadoActual,
                'estado_nuevo' => $estadoNuevo,
                'tipo_evento' => $tipoEvento,
                'comentario_publico' => $textoPublico,
                'nota_interna' => $nota,
            ]);
        });

        // Avisar al usuario solo de lo que él puede ver.
        if ($tipoEvento !== 'nota_interna') {
            $this->notificarUsuario($pqr, $cambiaEstado, $estadoNuevo, $textoPublico);
        }

        return response()->json([
            'message' => 'Seguimiento registrado correctamente',
            'pqr' => $this->cargarYPresentar($pqr->fresh(), true),
        ], $status);
    }

    private function notificarUsuario(
        Pqr $pqr,
        bool $cambiaEstado,
        string $estadoNuevo,
        ?string $textoPublico
    ): void {
        $etiqueta = Pqr::ETIQUETAS_ESTADO[$estadoNuevo] ?? $estadoNuevo;

        if ($cambiaEstado) {
            $titulo = 'Tu PQR avanzó a: ' . $etiqueta;
            $mensaje = 'Tu PQR ' . $pqr->radicado . ' "' . $pqr->asunto_pqr
                . '" ahora está en fase "' . $etiqueta . '".';
        } else {
            $titulo = 'Nuevo mensaje en tu PQR';
            $mensaje = 'El equipo escribió en tu PQR ' . $pqr->radicado
                . ' "' . $pqr->asunto_pqr . '".';
        }

        if ($textoPublico) {
            $mensaje .= ' ' . Str::limit($textoPublico, 160);
        }

        NotificacionService::crear($pqr->id_u, $titulo, $mensaje, 'pqr');
    }

    /* ------------------------------------------------------------ */
    /*  Utilidades                                                   */
    /* ------------------------------------------------------------ */

    private function esAdmin(Request $request): bool
    {
        return $request->user()->hasRole('adminSocial');
    }

    private function textoOpcional(?string $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : $texto;
    }

    private function rechazo(string $mensaje): JsonResponse
    {
        return response()->json(['message' => $mensaje], 422);
    }

    /** Carga el seguimiento (sin notas internas para el usuario) y arma la respuesta. */
    private function cargarYPresentar(Pqr $pqr, bool $esAdmin): array
    {
        $pqr->load([
            'usuario:id_u,nombre_u,apellido_u,correo_u,telefono_u',
            'seguimientos' => function ($q) use ($esAdmin) {
                if (!$esAdmin) {
                    $q->where('tipo_evento', '!=', 'nota_interna');
                }
            },
        ]);

        if ($esAdmin) {
            $pqr->seguimientos->load('administrador:id_u,nombre_u,apellido_u');
        }

        return $this->presentar($pqr, $esAdmin);
    }

    /**
     * Convierte la PQR a arreglo controlando qué campos del seguimiento
     * salen: nunca nota_interna ni datos del admin hacia el usuario.
     */
    private function presentar(Pqr $pqr, bool $esAdmin): array
    {
        $data = $pqr->toArray();
        unset($data['seguimientos']);

        if (isset($data['ultima_actuacion_en'])) {
            $data['ultima_actuacion_en'] = Carbon::parse(
                $data['ultima_actuacion_en']
            )->toIso8601String();
        }

        if ($pqr->relationLoaded('seguimientos')) {
            $data['seguimientos'] = $pqr->seguimientos
                ->map(fn (PqrSeguimiento $s) => $this->presentarSeguimiento($s, $esAdmin))
                ->values()
                ->all();
        }

        return $data;
    }

    private function presentarSeguimiento(PqrSeguimiento $s, bool $esAdmin): array
    {
        $item = [
            'id_seguimiento' => $s->id_seguimiento,
            'tipo_evento' => $s->tipo_evento,
            'estado_anterior' => $s->estado_anterior,
            'estado_nuevo' => $s->estado_nuevo,
            'comentario_publico' => $s->comentario_publico,
            'created_at' => $s->created_at?->toIso8601String(),
        ];

        if ($esAdmin) {
            $admin = $s->administrador;

            $item['nota_interna'] = $s->nota_interna;
            $item['autor'] = $admin
                ? trim($admin->nombre_u . ' ' . $admin->apellido_u)
                : ($s->tipo_evento === 'respuesta_cliente' ? 'Usuario' : 'Sistema');
        } else {
            // El usuario ve "Equipo", no el nombre personal del admin.
            $item['autor'] = $s->id_admin ? 'Equipo Skyed Social' : 'Sistema';
        }

        return $item;
    }
}