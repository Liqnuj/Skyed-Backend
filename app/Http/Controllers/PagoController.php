<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificacionService;

class PagoController extends Controller
{
    /**
     * Listar todos los pagos.
     */
    public function index()
    {
        $pagos = Pago::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'inscripcion.qr',
        ])->get();

        return response()->json([
            'pagos' => $pagos,
        ]);
    }

    /**
     * Mostrar un pago específico.
     */
    public function show($id)
    {
        $pago = Pago::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'inscripcion.qr',
        ])->find($id);

        if (!$pago) {
            return response()->json([
                'message' => 'Pago no encontrado'
            ], 404);
        }

        return response()->json([
            'pago' => $pago,
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
                 * Si la inscripción estaba confirmada,
                 * significa que el cupo ya estaba ocupado.
                 *
                 * En ese caso debemos devolverlo.
                 */
                if (
                    $inscripcion->estado_i === 'confirmada' &&
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
}
