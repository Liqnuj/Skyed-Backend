<?php

namespace App\Http\Controllers;

use App\Models\EntregaKit;
use App\Models\EventoDeportivo;
use App\Models\Inscripcion;
use Illuminate\Http\Request;

class EntregaKitController extends Controller
{
    /**
     * Listar las entregas de kit de un evento.
     */
    public function index(Request $request, $eventoId)
    {
        $evento = EventoDeportivo::find($eventoId);

        if (!$evento) {
            return response()->json([
                'message' => 'Evento no encontrado'
            ], 404);
        }

        $entregas = EntregaKit::with([
            'kit',
            'usuario',
            'evento'
        ])
            ->where('id_e', $eventoId)
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'evento' => $evento,
            'entregas' => $entregas
        ]);
    }
    /**
     * Mostrar una entrega específica.
     */
    public function show($id)
    {
        $entrega = EntregaKit::with([
            'kit',
            'usuario',
            'evento'
        ])->find($id);

        if (!$entrega) {
            return response()->json([
                'message' => 'Entrega no encontrada'
            ], 404);
        }

        return response()->json([
            'entrega' => $entrega
        ]);
    }

    /**
     * Registrar la entrega de un kit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_k' => 'required|exists:kit,id_k',
            'id_u' => 'required|exists:usuario,id_u',
            'id_e' => 'required|exists:eventoDeportivo,id_e',
            'persona_entrega_ek' => 'required|string|max:80',
            'observaciones_ek' => 'nullable|string|max:255',
        ]);

        /*
         * Verificar que el usuario tenga una inscripción
         * confirmada en el evento.
         */
        $inscripcion = Inscripcion::where('id_u', $validated['id_u'])
            ->where('id_e', $validated['id_e'])
            ->where('estado_i', 'confirmada')
            ->first();

        if (!$inscripcion) {
            return response()->json([
                'message' => 'El usuario no tiene una inscripción confirmada en este evento'
            ], 422);
        }

        /*
         * Verificar que no se haya entregado ya
         * un kit para esta inscripción.
         */
        $yaEntregado = EntregaKit::where('id_u', $validated['id_u'])
            ->where('id_e', $validated['id_e'])
            ->where('estado_ek', 'entregado')
            ->exists();

        if ($yaEntregado) {
            return response()->json([
                'message' => 'El kit ya fue entregado a este usuario'
            ], 409);
        }

        /*
         * Crear la entrega.
         */
        $entrega = EntregaKit::create([
            'fecha_entrega_real_ek' => now(),
            'persona_entrega_ek' => $validated['persona_entrega_ek'],
            'estado_ek' => 'entregado',
            'observaciones_ek' => $validated['observaciones_ek'] ?? null,
            'id_k' => $validated['id_k'],
            'id_u' => $validated['id_u'],
            'id_e' => $validated['id_e'],
        ]);

        return response()->json([
            'message' => 'Kit entregado correctamente',
            'entrega' => $entrega->load([
                'kit',
                'usuario',
                'evento'
            ])
        ], 201);
    }

    /**
     * Actualizar una entrega.
     */
    public function update(Request $request, $id)
    {
        $entrega = EntregaKit::find($id);

        if (!$entrega) {
            return response()->json([
                'message' => 'Entrega no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'estado_ek' => 'sometimes|in:pendiente,entregado,devolucion',
            'persona_entrega_ek' => 'sometimes|string|max:80',
            'observaciones_ek' => 'sometimes|nullable|string|max:255',
        ]);

        $entrega->update($validated);

        return response()->json([
            'message' => 'Entrega actualizada correctamente',
            'entrega' => $entrega->fresh()->load([
                'kit',
                'usuario',
                'evento'
            ])
        ]);
    }
}