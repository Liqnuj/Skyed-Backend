<?php

namespace App\Http\Controllers;

use App\Models\Pqr;
use Illuminate\Http\Request;
use App\Services\NotificacionService;

class PqrController extends Controller
{
    /**
     * Listar PQR. Un admin social ve todas; un usuario normal
     * solo ve las suyas.
     */
    public function index(Request $request)
    {
        $query = Pqr::with('usuario')->orderByDesc('created_at');

        if (!$request->user()->hasRole('adminSocial')) {
            $query->where('id_u', $request->user()->id_u);
        }

        return response()->json(
            $query->paginate($request->input('per_page', 15))
        );
    }

    /**
     * Mostrar una PQR. Un usuario normal solo puede ver la suya.
     */
    public function show(Request $request, $id)
    {
        $pqr = Pqr::with('usuario')->find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        $esDueno = $pqr->id_u === $request->user()->id_u;
        $esAdmin = $request->user()->hasRole('adminSocial');

        if (!$esDueno && !$esAdmin) {
            return response()->json([
                'message' => 'No tienes permisos para ver esta PQR'
            ], 403);
        }

        return response()->json([
            'pqr' => $pqr
        ]);
    }

    /**
     * Crear una PQR.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_pqr' => 'required|in:peticion,queja,reclamo,sugerencia,felicitacion',
            'asunto_pqr' => 'required|string|max:150',
            'mensaje_pqr' => 'required|string',
        ]);

        $pqr = Pqr::create([
            'tipo_pqr' => $validated['tipo_pqr'],
            'asunto_pqr' => $validated['asunto_pqr'],
            'mensaje_pqr' => $validated['mensaje_pqr'],
            'estado_pqr' => 'abierto',
            'respuesta_pqr' => null,
            'respondido_en_pqr' => null,
            'creado_en_pqr' => now(),
            'id_u' => $request->user()->id_u,
        ]);

        return response()->json([
            'message' => 'PQR creada correctamente',
            'pqr' => $pqr->load('usuario')
        ], 201);
    }

    /**
     * Actualizar estado o responder PQR.
     */
    public function update(Request $request, $id)
    {
        $pqr = Pqr::find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
        }

        $validated = $request->validate([
            'estado_pqr' => 'sometimes|in:abierto,en_proceso,resuelto,cerrado',
            'respuesta_pqr' => 'sometimes|nullable|string',
        ]);

        $datos = $validated;

        if (array_key_exists('respuesta_pqr', $validated)) {
            $datos['respondido_en_pqr'] = now();
        }

        $pqr->update($datos);

        // Notificar al usuario si se ha respondido a la PQR.
                if (array_key_exists('respuesta_pqr', $validated)) {
            NotificacionService::crear(
                $pqr->id_u,
                'Respuesta a tu PQR',
                'Tu PQR "' . $pqr->asunto_pqr . '" fue respondida.',
                'pqr'
            );
        }

        return response()->json([
            'message' => 'PQR actualizada correctamente',
            'pqr' => $pqr->fresh()->load('usuario')
        ]);
    }
}