<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\NotificacionService;
use App\Support\PermisosAsistente;

class PagoController extends Controller
{
    /**
     * Listar pagos. Un admin deportivo ve todos; un usuario normal
     * solo ve los de sus propias inscripciones.
     *
     * FIX: antes esta ruta vivía en la "zona general" (cualquier
     * usuario autenticado) sin ningún filtro, por lo que cualquier
     * participante podía listar los pagos (montos, comprobantes,
     * método de pago) de TODOS los demás usuarios.
     */
    public function index(Request $request)
    {
        $query = Pago::with([
            'inscripcion.usuario',
            'inscripcion.evento.kit',
            'inscripcion.qr',
            'inscripcion.invitado',
        ]);

        if (!$this->esAdminDeportivo($request)) {
            $query->whereHas('inscripcion', function ($q) use ($request) {
                $q->where('id_u', $request->user()->id_u);
            });
        }

        $pagos = $query->paginate($this->perPage($request));

        // Detalle de lo pagado (inscripción, kit, invitado y kit del invitado).
        $pagos->getCollection()->each(fn ($pago) => $pago->inscripcion?->append('desglose_precios'));

        return response()->json([
            'pagos' => $pagos,
        ]);
    }

    /**
     * Mostrar un pago específico. Solo el dueño de la inscripción
     * asociada o un adminDeportivo pueden verlo.
     *
     * FIX: antes cualquier usuario autenticado podía ver el pago de
     * cualquier otra persona simplemente probando IDs consecutivos
     * (IDOR).
     */
    public function show(Request $request, $id)
    {
        $pago = Pago::with([
            'inscripcion.usuario',
            'inscripcion.evento.kit',
            'inscripcion.qr',
            'inscripcion.invitado',
        ])->find($id);

        if (!$pago) {
            return response()->json([
                'message' => 'Pago no encontrado'
            ], 404);
        }

        $pago->inscripcion?->append('desglose_precios');

        $esDueno = $pago->inscripcion && $pago->inscripcion->id_u === $request->user()->id_u;
        $esAdmin = $this->esAdminDeportivo($request);

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver este pago'
            ], 403);
        }

        return response()->json([
            'pago' => $pago,
        ]);
    }

    /**
     * Subir (o reemplazar) la imagen del comprobante de un pago.
     * Solo el dueño de la inscripción, y solo mientras el pago esté
     * pendiente. La imagen va al disco PRIVADO (no queda en /public):
     * un comprobante es un dato sensible y solo se entrega a través de
     * verComprobante().
     *
     * POST /pagos/{id}/comprobante (multipart/form-data)
     *   - comprobante: archivo (jpg/png/webp, máx 5MB)
     */
    public function subirComprobante(Request $request, $id)
    {
        $pago = Pago::with('inscripcion')->find($id);

        if (!$pago) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        if (!$pago->inscripcion || $pago->inscripcion->id_u !== $request->user()->id_u) {
            return response()->json([
                'message' => 'No tienes permisos para modificar este pago'
            ], 403);
        }

        if ($pago->estado_p !== 'pendiente') {
            return response()->json([
                'message' => 'Solo puedes subir o cambiar el comprobante mientras el pago está pendiente'
            ], 422);
        }

        $request->validate([
            'comprobante' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            // Lo que revisó el navegador (ver utils/comprobantePago.ts del frontend).
            'hash_original' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'referencia_transaccion' => 'nullable|string|max:40',
            'verificacion' => 'nullable|json|max:2000',
        ], [
            'comprobante.required' => 'No llegó ninguna imagen del comprobante.',
            'comprobante.uploaded' => 'El servidor no pudo recibir la imagen. Intenta con otra foto.',
            'comprobante.image' => 'El archivo no es una imagen válida.',
            'comprobante.mimes' => 'Solo se aceptan imágenes JPG, PNG o WEBP.',
            'comprobante.max' => 'El comprobante no debe superar 5 MB.',
        ]);

        $archivo = $request->file('comprobante');

        // Huella de la imagen original (la que manda el navegador) o, si no
        // vino, la del archivo recibido.
        $hash = $request->input('hash_original') ?: hash_file('sha256', $archivo->getRealPath());
        $referencia = $this->normalizarReferencia($request->input('referencia_transaccion'));

        if ($this->comprobanteYaUsado($hash, $referencia, $pago->id_pago)) {
            return response()->json(['message' => self::MENSAJE_REPETIDO], 409);
        }
        // extension() se deduce del contenido real, no del nombre que manda el navegador.
        $ruta = 'comprobantes/' . $pago->id_pago . '_' . uniqid() . '.' . $archivo->extension();

        Storage::disk('local')->put($ruta, $archivo->get());

        // Si ya había un comprobante guardado por este sistema, se borra
        // (los valores viejos, que eran solo el nombre del archivo, se ignoran).
        $anterior = $pago->comprobante_p;
        if ($anterior && str_starts_with($anterior, 'comprobantes/')) {
            Storage::disk('local')->delete($anterior);
        }

        $verificacion = $request->filled('verificacion')
            ? $this->limpiarVerificacion(json_decode($request->input('verificacion'), true))
            : null;

        $pago->update([
            'comprobante_p' => $ruta,
            'hash_comprobante_p' => $hash,
            'referencia_transaccion_p' => $referencia,
            'verificacion_comprobante_p' => $verificacion,
        ]);

        return response()->json([
            'message' => 'Comprobante subido correctamente',
            'tiene_comprobante' => true,
        ], 201);
    }

    private const MENSAJE_REPETIDO = 'Este comprobante ya fue usado en otra inscripción. Sube el comprobante del pago de esta inscripción; si crees que es un error, escríbenos.';

    /**
     * Antes de crear la inscripción, el navegador pregunta si el comprobante
     * ya se usó (por su huella o por el número de la transacción).
     *
     * POST /pagos/comprobante/verificar { hash?, referencia_transaccion? }
     *   200 { usado: false } | 409 { message }
     */
    public function verificarComprobante(Request $request)
    {
        $datos = $request->validate([
            'hash' => ['nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'referencia_transaccion' => 'nullable|string|max:40',
        ]);

        $usado = $this->comprobanteYaUsado(
            $datos['hash'] ?? null,
            $this->normalizarReferencia($datos['referencia_transaccion'] ?? null)
        );

        return $usado
            ? response()->json(['message' => self::MENSAJE_REPETIDO, 'usado' => true], 409)
            : response()->json(['usado' => false]);
    }

    /** ¿Otro pago ya tiene esta imagen o este número de transacción? */
    private function comprobanteYaUsado(?string $hash, ?string $referencia, ?int $menosPago = null): bool
    {
        if (!$hash && !$referencia) {
            return false;
        }

        return Pago::query()
            ->when($menosPago, fn ($q) => $q->where('id_pago', '!=', $menosPago))
            ->where(function ($q) use ($hash, $referencia) {
                if ($hash) {
                    $q->orWhere('hash_comprobante_p', $hash);
                }
                if ($referencia) {
                    $q->orWhere('referencia_transaccion_p', $referencia);
                }
            })
            ->exists();
    }

    /** "m3164 7609" -> "M31647609". Muy corta o sin números no sirve para comparar. */
    private function normalizarReferencia(?string $referencia): ?string
    {
        $limpia = strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string) $referencia));

        return strlen($limpia) >= 6 && preg_match('/\d/', $limpia) ? substr($limpia, 0, 40) : null;
    }

    /** Solo se guardan los campos conocidos, con su tipo (no se confía en lo que manda el navegador). */
    private function limpiarVerificacion(mixed $datos): ?array
    {
        if (!is_array($datos)) {
            return null;
        }

        $bool = fn ($v) => is_bool($v) ? $v : null;
        $num = fn ($v) => is_numeric($v) ? (float) $v : null;

        return [
            'qr' => $bool($datos['qr'] ?? null),
            'qr_legible' => $bool($datos['qr_legible'] ?? null),
            'destino' => $bool($datos['destino'] ?? null),
            'monto' => $bool($datos['monto'] ?? null),
            'monto_leido' => $num($datos['monto_leido'] ?? null),
            'monto_esperado' => $num($datos['monto_esperado'] ?? null),
            'incluye_referencia' => $bool($datos['incluye_referencia'] ?? null),
            'revisado_en' => now()->toIso8601String(),
        ];
    }

    /**
     * Ruta interna (en el disco privado) del comprobante, o null si el pago
     * no tiene una imagen guardada por este sistema. Los pagos antiguos solo
     * guardaron el NOMBRE del archivo, no la imagen.
     */
    private function rutaComprobante(Pago $pago): ?string
    {
        $ruta = $pago->comprobante_p;

        if (
            !$ruta ||
            !str_starts_with($ruta, 'comprobantes/') ||
            !Storage::disk('local')->exists($ruta)
        ) {
            return null;
        }

        return $ruta;
    }

    /**
     * Devuelve una URL FIRMADA y temporal (10 min) para ver la imagen del
     * comprobante. Solo el dueño de la inscripción o un adminDeportivo.
     * Se usa una URL firmada porque una etiqueta <img> no puede enviar el
     * token de sesión.
     *
     * GET /pagos/{id}/comprobante
     */
    public function verComprobante(Request $request, $id)
    {
        $pago = Pago::with('inscripcion')->find($id);

        if (!$pago) {
            return response()->json(['message' => 'Pago no encontrado'], 404);
        }

        $esDueno = $pago->inscripcion && $pago->inscripcion->id_u === $request->user()->id_u;
        $esAdmin = $this->esAdminDeportivo($request);

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver este comprobante'
            ], 403);
        }

        if (!$this->rutaComprobante($pago)) {
            return response()->json([
                'message' => 'Este pago no tiene una imagen de comprobante'
            ], 404);
        }

        return response()->json([
            'url' => URL::temporarySignedRoute(
                'pagos.comprobante',
                now()->addMinutes(10),
                ['id' => $pago->id_pago]
            ),
            'expira_en_minutos' => 10,
        ]);
    }

    /**
     * Entrega el archivo. NO lleva auth:sanctum: la protección es la firma
     * (middleware "signed") y que la URL expira a los 10 minutos.
     *
     * GET /pagos/{id}/comprobante/archivo?signature=...
     */
    public function archivoComprobante(Request $request, $id)
    {
        $pago = Pago::find($id);
        $ruta = $pago ? $this->rutaComprobante($pago) : null;

        if (!$ruta) {
            return response()->json([
                'message' => 'Este pago no tiene una imagen de comprobante'
            ], 404);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disco */
        $disco = Storage::disk('local');

        return $disco->response($ruta, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Cambiar el estado de un pago.
     *
     * confirmado:
     * - Pago -> confirmado
     * - Inscripción -> confirmada
     * - QR -> activo
     *
     * rechazado/cancelado:
     * - Pago -> rechazado/cancelado
     * - Inscripción -> cancelada
     * - QR -> cancelado
     * - Se devuelve el cupo al evento
     */
    public function cambiarEstado(Request $request, $id)
    {
        // Confirmar o rechazar pagos es solo del admin deportivo. Sin esto, si
        // la ruta quedaba sin middleware de rol, un participante podía
        // confirmar su propio pago (y activar su QR) desde la consola.
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

        $validated = $request->validate([
            'estado_p' => [
                'required',
                'string',
                'in:pendiente,confirmado,rechazado,cancelado'
            ],
        ]);

        $pago = Pago::with([
            'inscripcion.evento',
            'inscripcion.qr',
        ])->find($id);

        if (!$pago) {
            return response()->json([
                'message' => 'Pago no encontrado'
            ], 404);
        }

        $nuevoEstado = $validated['estado_p'];
        $estadoAnterior = $pago->estado_p;

        /*
         * No hacer nada si se intenta colocar
         * exactamente el mismo estado.
         */
        if ($estadoAnterior === $nuevoEstado) {
            return response()->json([
                'message' => 'El pago ya tiene este estado',
                'estado_p' => $estadoAnterior,
            ], 422);
        }

        /*
         * Evitar modificar un pago que ya terminó
         * su proceso.
         */
        if (in_array($estadoAnterior, ['rechazado', 'cancelado'])) {
            return response()->json([
                'message' => 'No se puede modificar un pago rechazado o cancelado',
                'estado_p' => $estadoAnterior,
            ], 422);
        }

        // FIX: confirmar el pago de una inscripción cancelada la "revivía"
        // (confirmada y con QR activo) sin volver a descontar el cupo.
        if ($pago->inscripcion?->estado_i === 'cancelada' && $nuevoEstado === 'confirmado') {
            return response()->json([
                'message' => 'La inscripción de este pago fue cancelada; no se puede confirmar.',
            ], 422);
        }

        DB::transaction(function () use (
            $pago,
            $nuevoEstado,
            $estadoAnterior
        ) {

            $inscripcion = $pago->inscripcion;

            /*
             * Actualizar el pago.
             */
            $pago->update([
                'estado_p' => $nuevoEstado,
            ]);

            if (!$inscripcion) {
                return;
            }

            /*
             * ==========================================
             * PAGO CONFIRMADO
             * ==========================================
             */
            if ($nuevoEstado === 'confirmado') {

                $inscripcion->update([
                    'estado_i' => 'confirmada',
                ]);

                /*
                 * Activar QR.
                 */
                if ($inscripcion->qr) {
                    $inscripcion->qr->update([
                        'estado_qr' => 'activo',
                    ]);
                }

            }

            /*
             * ==========================================
             * PAGO RECHAZADO O CANCELADO
             * ==========================================
             */
            if (in_array($nuevoEstado, ['rechazado', 'cancelado'])) {

                /*
                 * FIX: el cupo se descuenta al crear la inscripción
                 * (InscripcionController::store), no al confirmar el pago.
                 * Antes solo se devolvía si estaba confirmada, así que al
                 * rechazar un pago pendiente el cupo se perdía. Se devuelve
                 * siempre que la inscripción no estuviera ya cancelada
                 * (al cancelarla ya se había devuelto).
                 */
                if (
                    $inscripcion->estado_i !== 'cancelada' &&
                    $inscripcion->evento
                ) {
                    $inscripcion->evento->increment(
                        'cupos_disponibles_e',
                        $inscripcion->cupo_i
                    );
                }

                $inscripcion->update([
                    'estado_i' => 'cancelada',
                ]);

                /*
                 * Cancelar QR.
                 */
                if ($inscripcion->qr) {
                    $inscripcion->qr->update([
                        'estado_qr' => 'cancelado',
                    ]);
                }
            }
        });


        $mensajesPorEstado = [
            'confirmado' => 'Tu pago fue confirmado. ¡Tu inscripción quedó lista!',
            'rechazado' => 'Tu pago fue rechazado. Por favor verifica tu comprobante.',
            'cancelado' => 'Tu pago fue cancelado.',
        ];

        if (
            isset($mensajesPorEstado[$nuevoEstado]) &&
            $pago->inscripcion
        ) {
            NotificacionService::crear(
                $pago->inscripcion->id_u,
                'Actualización de tu pago',
                $mensajesPorEstado[$nuevoEstado],
                'pago'
            );
        }



        /*
         * Devolver respuesta completa para React.
         */
        return response()->json([
            'message' => 'Estado del pago actualizado correctamente',
            'pago' => $pago->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'inscripcion.qr',
            ]),
        ]);
    }

    private function esAdminDeportivo(Request $request): bool
    {
        return $request->user()->hasRole('adminDeportivo')
            || PermisosAsistente::puedeVer($request->user(), 'deportivo', 'inscripciones');
    }
}