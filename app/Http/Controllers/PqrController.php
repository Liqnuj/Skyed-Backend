<?php

namespace App\Http\Controllers;

use App\Models\Pqr;
use Illuminate\Http\Request;

class PqrController extends Controller
{
    /**
     * Listar PQR.
     */
    public function index()
    {
        $pqr = Pqr::with('usuario')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'pqr' => $pqr
        ]);
    }

    /**
     * Mostrar una PQR.
     */
    public function show($id)
    {
        $pqr = Pqr::with('usuario')->find($id);

        if (!$pqr) {
            return response()->json([
                'message' => 'PQR no encontrada'
            ], 404);
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

        return response()->json([
            'message' => 'PQR actualizada correctamente',
            'pqr' => $pqr->fresh()->load('usuario')
        ]);
    }
}