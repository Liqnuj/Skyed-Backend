<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use App\Models\Invitado;
use App\Models\Pago;
use App\Models\QrEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $inscripciones = Inscripcion::with([
            'evento',
            'pago',
            'qr',
            'invitado',
        ])
            ->where('id_u', $request->user()->id_u)
            ->orderByDesc('fecha_i')
            ->get();

        return response()->json([
            'inscripciones' => InscripcionResource::collection($inscripciones),
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
                $query->paginate($request->input('per_page', 15))
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

        $user = $request->user();

        $resultado = DB::transaction(function () use (
            $validated,
            $user,
            $eventoId
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
            $invitadoId = null;
            if (!empty($validated['invitado'])) {
                $invitado = Invitado::create($validated['invitado']);
                $invitadoId = $invitado->id_inv;
            }

            // Crear inscripción.
            $inscripcion = Inscripcion::create([
                'cupo_i' => 1,
                'estado_i' => 'pendiente',
                'fecha_i' => now(),
                'precio_pagado_i' => $validated['precio_pagado_i'] ?? null,

                'contacto_emergencia_nombre' =>
                    $validated['contacto_emergencia_nombre'],

                'contacto_emergencia_telefono' =>
                    $validated['contacto_emergencia_telefono'],

                'contacto_emergencia_parentesco' =>
                    $validated['contacto_emergencia_parentesco'],

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

                'comprobante_p' =>
                    $validated['comprobante_p'] ?? null,

                'monto_p' =>
                    $validated['precio_pagado_i'] ?? 0,

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
        });

        return response()->json([
            'message' => 'Inscripción cancelada correctamente'
        ]);
    }
}
