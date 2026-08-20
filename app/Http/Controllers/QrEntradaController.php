<?php

namespace App\Http\Controllers;

use App\Models\QrEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrEntradaController extends Controller
{
    /**
     * Mostrar un QR específico.
     */
    public function show($id)
    {
        $qr = QrEntrada::with([
            'inscripcion.usuario',
            'inscripcion.evento',
        ])->find($id);

        if (!$qr) {
            return response()->json([
                'message' => 'QR no encontrado'
            ], 404);
        }

        return response()->json([
            'qr' => $qr,
        ]);
    }

    /**
     * Validar un QR mediante su código.
     */
    public function validar(Request $request)
    {
        $validated = $request->validate([
            'codigo_qr' => 'required|string|max:255',
        ]);

        $qr = QrEntrada::with([
            'inscripcion.usuario',
            'inscripcion.evento',
        ])->where(
            'codigo_qr',
            $validated['codigo_qr']
        )->first();

        if (!$qr) {
            return response()->json([
                'message' => 'Código QR no encontrado'
            ], 404);
        }

        if ($qr->estado_qr !== 'activo') {
            return response()->json([
                'message' => 'El QR no está disponible',
                'estado_qr' => $qr->estado_qr,
            ], 422);
        }

        $inscripcion = $qr->inscripcion;

        if (!$inscripcion) {
            return response()->json([
                'message' => 'La inscripción asociada no existe'
            ], 404);
        }

        if ($inscripcion->estado_i !== 'confirmada') {
            return response()->json([
                'message' => 'La inscripción no está confirmada',
                'estado_i' => $inscripcion->estado_i,
            ], 422);
        }

        if (!$inscripcion->evento) {
            return response()->json([
                'message' => 'El evento asociado no existe'
            ], 404);
        }

        if ($inscripcion->evento->estado_e !== 'activo') {
            return response()->json([
                'message' => 'El evento no está activo'
            ], 422);
        }

        DB::transaction(function () use ($qr) {
            $qr->update([
                'estado_qr' => 'usado',
                'fecha_uso_qr' => now(),
            ]);
        });

        return response()->json([
            'message' => 'QR validado correctamente',
            'qr' => $qr->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
            ]),
        ]);
    }
}
