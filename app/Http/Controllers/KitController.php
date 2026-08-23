<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use Illuminate\Http\Request;

class KitController extends Controller
{
    /**
     * Listar kits.
     */
    public function index()
    {
        return response()->json([
            'kits' => Kit::all()
        ]);
    }

    /**
     * Mostrar un kit específico.
     */
    public function show($id)
    {
        $kit = Kit::find($id);

        if (!$kit) {
            return response()->json([
                'message' => 'Kit no encontrado'
            ], 404);
        }

        return response()->json([
            'kit' => $kit
        ]);
    }

    /**
     * Crear un kit.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_k' => 'required|string|max:45',
            'stock_k' => 'required|integer|min:0',
            'fecha_entrega_k' => 'nullable|date',
            'lugar_entrega_k' => 'nullable|string|max:45',
            'contenido_k' => 'nullable|string|max:255',
            'talla_camiseta_k' => 'nullable|string|max:10',
            'numero_dorsal_k' => 'nullable|integer',
        ]);

        $kit = Kit::create($validated);

        return response()->json([
            'message' => 'Kit creado correctamente',
            'kit' => $kit
        ], 201);
    }

    /**
     * Actualizar un kit.
     */
    public function update(Request $request, $id)
    {
        $kit = Kit::find($id);

        if (!$kit) {
            return response()->json([
                'message' => 'Kit no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_k' => 'sometimes|required|string|max:45',
            'stock_k' => 'sometimes|required|integer|min:0',
            'fecha_entrega_k' => 'nullable|date',
            'lugar_entrega_k' => 'nullable|string|max:45',
            'contenido_k' => 'nullable|string|max:255',
            'talla_camiseta_k' => 'nullable|string|max:10',
            'numero_dorsal_k' => 'nullable|integer',
        ]);

        $kit->update($validated);

        return response()->json([
            'message' => 'Kit actualizado correctamente',
            'kit' => $kit
        ]);
    }

    /**
     * Eliminar un kit.
     *
     * Bloquea el borrado si el kit sigue referenciado desde
     * eventoDeportivo o entrega_kit, para no dejar FKs huérfanas.
     */
    public function destroy($id)
    {
        $kit = Kit::find($id);

        if (!$kit) {
            return response()->json([
                'message' => 'Kit no encontrado'
            ], 404);
        }

        if ($kit->eventos()->exists() || $kit->entregas()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el kit está en uso por un evento o una entrega'
            ], 409);
        }

        $kit->delete();

        return response()->json([
            'message' => 'Kit eliminado correctamente'
        ]);
    }
}
