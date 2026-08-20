<?php

namespace App\Http\Controllers;

use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * Si el pago se confirma:
     * - La inscripción pasa a confirmada.
     * - El QR pasa a activo.
     *
     * Si el pago se rechaza o cancela:
     * - La inscripción pasa a cancelada.
     * - El QR pasa a cancelado.
     */
    public function cambiarEstado(Request $request, $id)
    {
        $validated = $request->validate([
            'estado_p' => 'required|string|in:pendiente,confirmado,rechazado,cancelado',
        ]);

        $pago = Pago::with('inscripcion')->find($id);

        if (!$pago) {
            return response()->json([
                'message' => 'Pago no encontrado'
            ], 404);
        }

        DB::transaction(function () use ($pago, $validated) {

            $nuevoEstado = $validated['estado_p'];

            // Actualizar estado del pago
            $pago->update([
                'estado_p' => $nuevoEstado,
            ]);

            // Obtener la inscripción relacionada
            $inscripcion = $pago->inscripcion;

            if (!$inscripcion) {
                return;
            }

            // Si el pago fue confirmado
            if ($nuevoEstado === 'confirmado') {

                $inscripcion->update([
                    'estado_i' => 'confirmada',
                ]);

                // Activar el QR
                if ($inscripcion->qr) {
                    $inscripcion->qr->update([
                        'estado_qr' => 'activo',
                    ]);
                }
            }

            // Si el pago fue rechazado o cancelado
            if (in_array($nuevoEstado, ['rechazado', 'cancelado'])) {

                $inscripcion->update([
                    'estado_i' => 'cancelada',
                ]);

                // Cancelar el QR
                if ($inscripcion->qr) {
                    $inscripcion->qr->update([
                        'estado_qr' => 'cancelado',
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'Estado del pago actualizado',
            'pago' => $pago->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'inscripcion.qr',
            ]),
        ]);
    }
}