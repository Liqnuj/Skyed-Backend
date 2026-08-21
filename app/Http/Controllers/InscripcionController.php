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

class InscripcionController extends Controller
{
    /**
     * Listar las inscripciones de un evento.
     */
    public function index($eventoId)
    {
        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $inscripciones = Inscripcion::with([
            'usuario',
            'pago',
            'qr',
            'invitado',
        ])
            ->where('id_e', $eventoId)
            ->get();

        return response()->json([
            'evento' => $evento,
            'inscripciones' => $inscripciones,
        ]);
    }

    /**
     * Mostrar una inscripción específica.
     */
    public function show($id)
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

        return response()->json([
            'inscripcion' => $inscripcion,
        ]);
    }

    /**
     * Crear una inscripción.
     */
    public function store(Request $request, $eventoId)
    {
        $validated = $request->validate([
            'cupo_i' => 'required|integer|min:1',
            'precio_pagado_i' => 'nullable|numeric|min:0',

            'contacto_emergencia_nombre' => 'required|string|max:100',
            'contacto_emergencia_telefono' => 'required|string|max:15',
            'contacto_emergencia_parentesco' => 'required|string|max:50',

            'metodo_pago_p' => 'nullable|string|max:50',
            'referencia_p' => 'nullable|string|max:100',
            'comprobante_p' => 'nullable|string|max:255',

            // Invitado opcional (acompañante que asiste con el titular).
            // Envía "invitado" solo si aplica; si no, la inscripción
            // queda solo a nombre del usuario autenticado.
            'invitado' => 'nullable|array',
            'invitado.tipo_documento' => 'required_with:invitado|string|max:30',
            'invitado.documento_inv' => 'required_with:invitado|integer|unique:invitados,documento_inv',
            'invitado.nombre_inv' => 'required_with:invitado|string|max:50',
            'invitado.apellido_inv' => 'required_with:invitado|string|max:50',
            'invitado.rh_inv' => 'required_with:invitado|string|max:5',
            'invitado.telefono_inv' => 'required_with:invitado|string|max:50|unique:invitados,telefono_inv',
            'invitado.fecha_nacimiento_inv' => 'required_with:invitado|date',
            'invitado.correo_inv' => 'nullable|email|max:80|unique:invitados,correo_inv',
        ]);

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
                'cupo_i' => $validated['cupo_i'],
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
            'inscripcion' => $resultado,
        ], 201);
    }

    /**
     * Cancelar una inscripción.
     */
    public function destroy(Request $request, $id)
    {
        $inscripcion = Inscripcion::find($id);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        $esDueno = $inscripcion->id_u === $request->user()->id_u;
        $esAdminDeportivo = $request->user()->hasRole('adminDeportivo');

        if (!$esDueno && !$esAdminDeportivo) {
            return response()->json([
                'message' => 'No tienes permiso para cancelar esta inscripción'
            ], 403);
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
        });

        return response()->json([
            'message' => 'Inscripción cancelada correctamente'
        ]);
    }
}