<?php

namespace App\Http\Controllers;

use App\Mail\InscripcionQrMail;
use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Invitado;
use App\Models\Kit;
use App\Models\Pago;
use App\Models\QrEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Http\Requests\StoreInscripcionRequest;
use App\Http\Requests\UpdateInscripcionRequest;
use App\Http\Resources\InscripcionResource;
use App\Http\Resources\EventoDeportivoResource;

class InscripcionController extends Controller
{
    /**
     * Listar las inscripciones de un evento. Un admin deportivo ve
     * todas; un usuario normal solo ve las suyas.
     */
    
    public function misInscripciones(Request $request)
    {
        $user = $request->user();

        $inscripciones = Inscripcion::with([
            'evento.kit',
            'pago',
            'qr',
            'invitado',
        ])
            ->where('id_u', $user->id_u)
            ->orderByDesc('fecha_i')
            ->get();

        // Estado de la entrega del kit por evento (dónde y cuándo recogerlo
        // sale del kit del evento; aquí solo se agrega si ya se entregó).
        $entregas = DB::table('entrega_kit')
            ->where('id_u', $user->id_u)
            ->get()
            ->keyBy('id_e');

        $items = $inscripciones->map(function ($insc) use ($request, $entregas) {
            $data = (new InscripcionResource($insc))->resolve($request);
            $entrega = $entregas->get($insc->id_e);
            $data['entrega_kit'] = $entrega ? [
                'estado' => $entrega->estado_ek,
                'fecha_entrega_real' => $entrega->fecha_entrega_real_ek,
            ] : null;
            return $data;
        })->values();

        return response()->json([
            'inscripciones' => $items,
        ]);
    }
    public function index(Request $request, int $eventoId)
    {
        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $query = Inscripcion::with([
            'usuario',
            'pago',
            'qr',
            'invitado',
        ])->where('id_e', $eventoId);

        if (!$request->user()->hasRole('adminDeportivo')) {
            $query->where('id_u', $request->user()->id_u);
        }

        return response()->json([
            'evento' => new EventoDeportivoResource($evento),
            'inscripciones' => InscripcionResource::collection(
                $query->paginate($this->perPage($request))
            ),
        ]);
    }
    /**
     * Mostrar una inscripción específica. Un usuario normal solo
     * puede ver la suya.
     */
    public function show(Request $request,int $id)
    {
        $inscripcion = Inscripcion::with([
            'usuario',
            'evento',
            'pago',
            'qr',
            'invitado',
        ])->find($id);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        $this->authorize('view', $inscripcion);

        return response()->json([
            'inscripcion' => new InscripcionResource($inscripcion),
        ]);
    }

    /**
     * Crear una inscripción.
     */
    public function store(StoreInscripcionRequest $request, $eventoId)
    {
        $validated = $request->validated();

        // Talla de camiseta (opcional). Se valida aquí para no depender de
        // que StoreInscripcionRequest la incluya. El formulario de inscripción
        // la envía como "talla_kit"; "talla_camiseta" se sigue aceptando.
        $tallas = $request->validate([
            'talla_camiseta' => 'nullable|string|max:10',
            'talla_kit' => 'nullable|string|max:10',
        ]);
        $talla = $tallas['talla_kit'] ?? $tallas['talla_camiseta'] ?? null;

        // Dorsal elegido por el participante (opcional). Si lo deja vacío
        // queda null en numero_dorsal_i.
        $dorsal = $request->validate([
            'numero_dorsal_preferido' => 'nullable|integer|min:1|max:99999',
        ])['numero_dorsal_preferido'] ?? null;

        $user = $request->user();

        // El formulario manda desea_jersey=false cuando el participante no
        // quiere kit. Si no llega el campo se mantiene el comportamiento anterior.
        $deseaKit = $request->boolean('desea_jersey', true);

        // Kit del invitado (opcional). Solo cuenta si hay invitado y el evento tiene kit.
        $extraInv = $request->validate([
            'invitado.desea_kit' => 'nullable|boolean',
            'invitado.talla_kit' => 'nullable|string|max:10',
        ]);
        $invDeseaKit = (bool) data_get($extraInv, 'invitado.desea_kit', false);
        $invTalla = data_get($extraInv, 'invitado.talla_kit');

        if ($invDeseaKit && !$invTalla) {
            return response()->json([
                'message' => 'Selecciona la talla del kit del invitado'
            ], 422);
        }

        $resultado = DB::transaction(function () use (
            $validated,
            $user,
            $eventoId,
            $talla,
            $dorsal,
            $deseaKit,
            $invDeseaKit,
            $invTalla
        ) {
            // Bloqueamos el evento durante la operación
            // para evitar problemas de cupos simultáneos.
            $evento = EventoDeportivo::where('id_e', $eventoId)
                ->lockForUpdate()
                ->first();

            if (!$evento) {
                return response()->json([
                    'message' => 'Evento no encontrado'
                ], 404);
            }

            if ($evento->estado_e !== 'activo') {
                return response()->json([
                    'message' => 'El evento no está activo'
                ], 422);
            }

            // Evitar doble inscripción activa.
            $yaInscrito = Inscripcion::where('id_u', $user->id_u)
                ->where('id_e', $evento->id_e)
                ->whereIn('estado_i', ['pendiente', 'confirmada'])
                ->exists();

            if ($yaInscrito) {
                return response()->json([
                    'message' => 'El usuario ya está inscrito en este evento'
                ], 409);
            }

            // Comprobar cupos.
            if ($evento->cupos_disponibles_e <= 0) {
                return response()->json([
                    'message' => 'No hay cupos disponibles'
                ], 422);
            }

            // Invitado opcional: se crea primero para poder vincularlo
            // a la inscripción.
            $kit = $evento->id_k ? Kit::find($evento->id_k) : null;

            $invitadoId = null;
            if (!empty($validated['invitado'])) {
                $datosInvitado = $validated['invitado'];
                if ($kit && $invDeseaKit) {
                    $datosInvitado['desea_kit_inv'] = true;
                    $datosInvitado['talla_kit_inv'] = $invTalla;
                }
                $invitado = Invitado::create($datosInvitado);
                $invitadoId = $invitado->id_inv;
            }

            // FIX: el precio lo decide el servidor con el precio del evento.
            // Antes se guardaba el precio_pagado_i que mandaba el navegador,
            // así que se podía inscribir pagando $1 a un evento de $90.000.
            // El total incluye: inscripción del titular, su kit si lo pidió, y si hay
            // invitado, su inscripción (mismo precio del evento) y su kit si lo pidió.
            $precioEvento = (float) $evento->precio_e;
            $precioKit = $kit ? (float) $kit->precio_k : 0.0;

            $precio = $precioEvento;
            if ($deseaKit) {
                $precio += $precioKit;
            }
            if ($invitadoId) {
                $precio += $precioEvento;
                if ($kit && $invDeseaKit) {
                    $precio += $precioKit;
                }
            }

            // Crear inscripción.
            $inscripcion = Inscripcion::create([
                'cupo_i' => 1,
                'estado_i' => 'pendiente',
                'fecha_i' => now(),
                'precio_pagado_i' => $precio,

                'contacto_emergencia_nombre' =>
                    $validated['contacto_emergencia_nombre'],

                'contacto_emergencia_telefono' =>
                    $validated['contacto_emergencia_telefono'],

                'contacto_emergencia_parentesco' =>
                    $validated['contacto_emergencia_parentesco'],

                'talla_camiseta_i' => $talla,
                'numero_dorsal_i' => $dorsal,

                'id_u' => $user->id_u,
                'id_e' => $evento->id_e,
                'id_inv' => $invitadoId,
            ]);

            // Descontar un cupo.
            $evento->decrement('cupos_disponibles_e');

            // Crear pago.
            Pago::create([
                'metodo_pago_p' =>
                    $validated['metodo_pago_p'] ?? null,

                'referencia_p' =>
                    $validated['referencia_p'] ?? null,

                // La imagen solo entra por POST /pagos/{id}/comprobante. Antes se
                // guardaba el texto que mandara el navegador, y poniendo
                // "comprobantes/<archivo de otra persona>" el endpoint de
                // verComprobante entregaba el comprobante ajeno.
                'comprobante_p' => null,

                'monto_p' => $precio,

                'fecha_p' => now(),
                'estado_p' => 'pendiente',
                'id_i' => $inscripcion->id_i,
            ]);

            // Crear QR.
            QrEntrada::create([
                'codigo_qr' => (string) Str::uuid(),
                'qr_imagen_qr' => null,
                'fecha_generacion_qr' => now(),
                'fecha_uso_qr' => null,
                'estado_qr' => 'pendiente',
                'id_i' => $inscripcion->id_i,
            ]);

            // Historial del participante: queda "inscrito" hasta que se
            // escanee su QR (asistio) o se cargue su resultado (finalizado).
            DB::table('historial_participacion')->insert([
                'fecha_hp' => now(),
                'estado_hp' => 'inscrito',
                'observaciones_hp' => null,
                'id_u' => $user->id_u,
                'id_e' => $evento->id_e,
                'id_i' => $inscripcion->id_i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Si el evento tiene kit, queda una entrega pendiente para que
            // el participante vea dónde y cuándo recogerlo.
            if ($evento->id_k && $deseaKit) {
                $yaTieneEntrega = DB::table('entrega_kit')
                    ->where('id_u', $user->id_u)
                    ->where('id_e', $evento->id_e)
                    ->exists();

                if (!$yaTieneEntrega) {
                    DB::table('entrega_kit')->insert([
                        'estado_ek' => 'pendiente',
                        'id_k' => $evento->id_k,
                        'id_u' => $user->id_u,
                        'id_e' => $evento->id_e,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $inscripcion->load([
                'usuario',
                'evento',
                'pago',
                'qr',
                'invitado',
            ]);
        });

        // Si la transacción devolvió una respuesta de error.
        if ($resultado instanceof \Illuminate\Http\JsonResponse) {
            return $resultado;
        }

        // FIX: la inscripción ya quedó guardada; si el correo falla (sin
        // internet, SMTP caído) no debe responder 500, porque el usuario
        // creería que no se inscribió y el cupo ya se descontó. El QR
        // igual queda disponible en "Mi entrada".
        try {
            Mail::to($resultado->usuario->correo_u)->send(new InscripcionQrMail($resultado));
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo con el QR de la inscripción ' . $resultado->id_i . ': ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Inscripción creada correctamente',
            'inscripcion' => new InscripcionResource($resultado),
        ], 201);
    }




        /**
     * Actualizar los datos de contacto de emergencia de una
     * inscripción. Solo el dueño o un adminDeportivo pueden hacerlo.
     * No se puede tocar estado_i aquí a propósito: ese cambia
     * únicamente a través de PagoController, QrEntradaController
     * y ResultadoController, que validan el proceso completo.
     */
    public function update(UpdateInscripcionRequest $request, $id)
    {
        $inscripcion = Inscripcion::find($id);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        $this->authorize('update', $inscripcion);

        $inscripcion->update($request->validated());

        return response()->json([
            'message' => 'Datos de contacto actualizados correctamente',
            'inscripcion' => new InscripcionResource($inscripcion->fresh()->load([
                'usuario',
                'evento',
                'pago',
                'qr',
                'invitado',
            ]))
        ]);
    }
    

    /**
     * Cancelar una inscripción.
     */
    public function destroy(Request $request,int $id)
    {
        $inscripcion = Inscripcion::find($id);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        $this->authorize('delete', $inscripcion);

        // FIX: si la inscripción ya estaba cancelada, cancelarla de
        // nuevo (p. ej. un doble clic o una llamada repetida al
        // endpoint) volvía a incrementar cupos_disponibles_e, "liberando"
        // un cupo que en realidad ya se había devuelto la primera vez.
        if ($inscripcion->estado_i === 'cancelada') {
            return response()->json([
                'message' => 'Esta inscripción ya estaba cancelada'
            ], 422);
        }

        DB::transaction(function () use ($inscripcion) {
            $evento = EventoDeportivo::where('id_e', $inscripcion->id_e)
                ->lockForUpdate()
                ->first();

            $inscripcion->update([
                'estado_i' => 'cancelada'
            ]);

            if ($evento) {
                $evento->increment('cupos_disponibles_e');
            }

            if ($inscripcion->qr) {
                $inscripcion->qr->update([
                    'estado_qr' => 'cancelado'
                ]);
            }

            // FIX: el pago quedaba "pendiente" y aparecía en el panel como
            // pago por validar de una inscripción que ya no existe.
            if ($inscripcion->pago && $inscripcion->pago->estado_p === 'pendiente') {
                $inscripcion->pago->update([
                    'estado_p' => 'cancelado'
                ]);
            }

            // Una inscripción cancelada no es participación: se quita su
            // fila "inscrito" del historial y la entrega de kit pendiente.
            // Si ya había asistido o entregado, se conserva el registro.
            DB::table('historial_participacion')
                ->where('id_i', $inscripcion->id_i)
                ->where('estado_hp', 'inscrito')
                ->delete();

            DB::table('entrega_kit')
                ->where('id_u', $inscripcion->id_u)
                ->where('id_e', $inscripcion->id_e)
                ->where('estado_ek', 'pendiente')
                ->delete();
        });

        return response()->json([
            'message' => 'Inscripción cancelada correctamente'
        ]);
    }
}