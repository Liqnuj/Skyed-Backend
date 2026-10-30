<?php

namespace App\Http\Controllers;

use App\Models\QrEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QrEntradaController extends Controller
{
    /**
     * Mostrar un QR específico. Solo el dueño de la inscripción
     * asociada o un adminDeportivo pueden verlo.
     *
     * FIX: antes cualquier usuario autenticado podía consultar el QR
     * (código, estado, fecha de uso, datos del dueño) de cualquier
     * otra inscripción con solo cambiar el id en la URL (IDOR).
     */
    public function show(Request $request, $id)
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

        $esDueno = $qr->inscripcion && $qr->inscripcion->id_u === $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminDeportivo');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver este QR'
            ], 403);
        }

        return response()->json([
            'qr' => $qr,
        ]);
    }

    /**
     * Validar un QR mediante su código.
     *
     * Cada QR permite tantas entradas como usos_permitidos_qr:
     * 1 si la inscripción es solo del titular, 2 si lleva invitado.
     * El QR sigue "activo" mientras le queden usos y pasa a "usado"
     * cuando se agotan.
     */
    public function validar(Request $request)
    {
        $validated = $request->validate([
            'codigo_qr' => 'required|string|max:255',
        ]);

        $resultado = DB::transaction(function () use ($validated) {

            // Bloquear el QR durante toda la validación para que dos
            // escaneos simultáneos no gasten el mismo uso.
            $qr = QrEntrada::with([
                'inscripcion.usuario',
                'inscripcion.evento',
                'inscripcion.invitado',
            ])
                ->where('codigo_qr', $validated['codigo_qr'])
                ->lockForUpdate()
                ->first();

            if (!$qr) {
                return response()->json([
                    'message' => 'Código QR no encontrado'
                ], 404);
            }

            // El QR debe estar disponible.
            if ($qr->estado_qr !== 'activo') {
                return response()->json([
                    'message' => $qr->estado_qr === 'usado'
                        ? 'Este QR ya se usó completo'
                        : 'El QR no está disponible',
                    'estado_qr' => $qr->estado_qr,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_realizados' => $qr->usos_realizados_qr,
                ], 422);
            }

            // Defensa extra: aunque el estado diga "activo", no puede
            // haber más entradas que usos permitidos.
            if ($qr->usos_realizados_qr >= $qr->usos_permitidos_qr) {
                return response()->json([
                    'message' => 'Este QR ya se usó completo',
                    'estado_qr' => $qr->estado_qr,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_realizados' => $qr->usos_realizados_qr,
                ], 422);
            }

            $inscripcion = $qr->inscripcion;

            if (!$inscripcion) {
                return response()->json([
                    'message' => 'La inscripción asociada no existe'
                ], 404);
            }

            // La inscripción debe estar confirmada.
            if ($inscripcion->estado_i !== 'confirmada') {
                return response()->json([
                    'message' => 'La inscripción no está confirmada',
                    'estado_i' => $inscripcion->estado_i,
                ], 422);
            }

            $evento = $inscripcion->evento;

            if (!$evento) {
                return response()->json([
                    'message' => 'El evento asociado no existe'
                ], 404);
            }

            // El evento debe estar activo.
            if ($evento->estado_e !== 'activo') {
                return response()->json([
                    'message' => 'El evento no está activo',
                    'estado_e' => $evento->estado_e,
                ], 422);
            }

            // Registrar la entrada dentro de la misma transacción.
            $usosRealizados = $qr->usos_realizados_qr + 1;
            $agotado = $usosRealizados >= $qr->usos_permitidos_qr;

            $qr->update([
                'usos_realizados_qr' => $usosRealizados,
                'fecha_uso_qr' => now(),
                'estado_qr' => $agotado ? 'usado' : 'activo',
            ]);

            // Datos para que el admin verifique a las personas contra
            // su documento en la puerta.
            $usuario = $inscripcion->usuario;
            $invitado = $inscripcion->invitado;

            $personas = [];

            if ($usuario) {
                $personas[] = [
                    'rol' => 'titular',
                    'nombre' => trim($usuario->nombre_u . ' ' . $usuario->apellido_u),
                    'documento' => $usuario->documento_u,
                ];
            }

            if ($invitado) {
                $personas[] = [
                    'rol' => 'invitado',
                    'nombre' => trim($invitado->nombre_inv . ' ' . $invitado->apellido_inv),
                    'documento' => $invitado->documento_inv,
                ];
            }

            return response()->json([
                'message' => $agotado
                    ? 'QR validado correctamente. Ya ingresaron todas las personas.'
                    : 'QR validado correctamente. Falta que ingrese una persona más.',
                'qr' => $qr->fresh()->load([
                    'inscripcion.usuario',
                    'inscripcion.evento',
                    'inscripcion.invitado',
                ]),
                'acceso' => [
                    'ingreso_numero' => $usosRealizados,
                    'usos_permitidos' => $qr->usos_permitidos_qr,
                    'usos_restantes' => $qr->usos_permitidos_qr - $usosRealizados,
                    'personas' => $personas,
                ],
            ]);
        });

        return $resultado;
    }
}