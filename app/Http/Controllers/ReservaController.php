<?php

namespace App\Http\Controllers;

use App\Models\Reserva;
use App\Models\EventoRealizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificacionService;

class ReservaController extends Controller
{
    /**
     * Listar reservas. Un admin social ve todas; un usuario normal
     * solo ve las suyas.
     */
    public function index(Request $request)
    {
        $query = Reserva::with([
            'usuario',
            'evento',
            'seguimientos',
        ]);

        if (!$request->user()->hasRole('adminSocial')) {
            $query->where('id_u', $request->user()->id_u);
        }

        return response()->json(
            $query->paginate($request->input('per_page', 15))
        );
    }

    /**
     * Mostrar una reserva. Un usuario normal solo puede ver la suya.
     */
    public function show(Request $request, $id)
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

        $esDueno = $reserva->id_u === $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminSocial');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta reserva'
            ], 403);
        }

        return response()->json([
            'reserva' => $reserva
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                    'id_u' => 'required|exists:usuario,id_u', // Para vincular la reserva al usuario logueado
                    'id_tipo_eves' => 'required|exists:tipo_evento,id_tipo_eves', // Para saber qué tipo de evento es
                    'fecha_evento_rese' => 'required|date',
                    'invitados_rese' => 'required|integer|min:1',
                    'presupuesto_rese' => 'required|numeric|min:0',
                    'ubicacion_rese' => 'required|string|max:120',
                    'Observaciones_rese' => 'required|string|max:255', // También puedes poner 'nullable' si no es obligatorio
                    'total_rese' => 'nullable|numeric|min:0',
                    'id_er' => 'nullable|exists:evento_realizado,id_er', // Aquí está la corrección principal
                    'nombre_rese' => 'nullable|string|max:80', // Nombre que el cliente le pone a su reserva
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
                        'nombre_rese' => isset($validated['nombre_rese']) && trim($validated['nombre_rese']) !== ''
                            ? trim($validated['nombre_rese'])
                            : null,
                        'id_u' => $request->user()->id_u,
                        'id_tipo_eves' => $validated['id_tipo_eves'], 
                        'id_er' => $validated['id_er'] ?? null, 
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

            NotificacionService::crear(
                $reserva->id_u,
                'Actualización de tu reserva',
                'El estado de tu reserva cambió a: ' . $validated['estado_rese'],
                'reserva'
            );
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

    /**
     * El cliente le pone (o cambia) el nombre a su propia reserva.
     * Un nombre vacío lo quita.
     */
    public function renombrar(Request $request, $id)
    {
        $reserva = Reserva::find($id);

        if (!$reserva) {
            return response()->json([
                'message' => 'Reserva no encontrada'
            ], 404);
        }

        if ((int) $reserva->id_u !== (int) $request->user()->id_u) {
            return response()->json([
                'message' => 'Solo puedes cambiar el nombre de tus propias reservas.'
            ], 403);
        }

        $validated = $request->validate([
            'nombre_rese' => 'nullable|string|max:80',
        ]);

        $nombre = trim($validated['nombre_rese'] ?? '');

        $reserva->nombre_rese = $nombre !== '' ? $nombre : null;
        $reserva->save();

        return response()->json([
            'message' => 'Nombre guardado correctamente',
            'reserva' => $reserva->only(['id_rese', 'nombre_rese']),
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