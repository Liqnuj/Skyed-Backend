<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\EventoRealizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReservaController extends Controller
{
    public function index()
    {
        return response()->json([
            'reservas' => Reserva::with([
                'usuario',
                'evento',
                'seguimientos',
            ])->get()
        ]);
    }

    public function show($id)
    {
        $reserva = Reserva::with([
            'usuario',
            'evento',
            'seguimientos',
        ])->find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        return response()->json([
            'reserva' => $reserva
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'fecha_evento_rese' => 'required|date',
            'invitados_rese' => 'required|integer|min:1',
            'presupuesto_rese' => 'required|numeric|min:0',
            'ubicacion_rese' => 'required|string|max:120',
            'Observaciones_rese' => 'required|string|max:255',
            'total_rese' => 'nullable|numeric|min:0',
            'id_er' => 'required|exists:evento_realizado,id_er',
        ]);

        $reserva = DB::transaction(function () use (
            $validated,
            $request
        ) {
            $reserva = Reserva::create([
                'fecha_evento_rese' => $validated['fecha_evento_rese'],
                'invitados_rese' => $validated['invitados_rese'],
                'presupuesto_rese' => $validated['presupuesto_rese'],
                'ubicacion_rese' => $validated['ubicacion_rese'],
                'Observaciones_rese' => $validated['Observaciones_rese'],
                'total_rese' => $validated['total_rese'] ?? 0,
                'estado_rese' => 'pendiente',
                'creado_en_rese' => now(),
                'id_u' => $request->user()->id_u,
                'id_er' => $validated['id_er'],
            ]);

            $reserva->seguimientos()->create([
                'fecha_actualizacion' => now(),
                'comentario' => 'Reserva creada',
            ]);

            return $reserva->load([
                'usuario',
                'evento',
                'seguimientos',
            ]);
        });

        return response()->json([
            'message' => 'Reserva creada correctamente',
            'reserva' => $reserva
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'fecha_evento_rese' => 'sometimes|date',
            'invitados_rese' => 'sometimes|integer|min:1',
            'presupuesto_rese' => 'sometimes|numeric|min:0',
            'ubicacion_rese' => 'sometimes|string|max:120',
            'Observaciones_rese' => 'sometimes|string|max:255',
            'total_rese' => 'sometimes|numeric|min:0',
            'estado_rese' => 'sometimes|in:pendiente,confirmada,cancelada,completada',
            'id_er' => 'sometimes|exists:evento_realizado,id_er',
        ]);

        $reserva->update($validated);

        if (isset($validated['estado_rese'])) {
            $reserva->seguimientos()->create([
                'fecha_actualizacion' => now(),
                'comentario' => 'Estado cambiado a: ' . $validated['estado_rese'],
            ]);
        }

        return response()->json([
            'message' => 'Reserva actualizada correctamente',
            'reserva' => $reserva->fresh()->load([
                'usuario',
                'evento',
                'seguimientos',
            ])
        ]);
    }

    public function destroy($id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $reserva->delete();

        return response()->json([
            'message' => 'Reserva eliminada correctamente'
        ]);
    }

    public function seguimiento(Request $request, $id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'comentario' => 'required|string',
        ]);

        $seguimiento = $reserva->seguimientos()->create([
            'fecha_actualizacion' => now(),
            'comentario' => $validated['comentario'],
        ]);

        return response()->json([
            'message' => 'Seguimiento registrado correctamente',
            'seguimiento' => $seguimiento
        ], 201);
    }
}