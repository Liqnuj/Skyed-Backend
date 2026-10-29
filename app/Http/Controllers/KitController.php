<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use App\Models\Kit;
use Illuminate\Http\Request;

class KitController extends Controller
{
    /**
     * Listar kits.
     */
    public function index(Request $request)
    {
        // Se incluye el evento al que está asignado cada kit (un kit es de un solo evento).
        $kits = Kit::with('eventos:id_e,nombre_e,id_k')->paginate($this->perPage($request));

        return response()->json($kits);
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
            'precio_k' => 'required|numeric|min:0|max:99999999',
            'fecha_entrega_k' => 'nullable|date',
            'lugar_entrega_k' => 'nullable|string|max:45',
            'contenido_k' => 'nullable|string|max:255',
            'talla_camiseta_k' => 'nullable|string|max:10',
            'numero_dorsal_k' => 'nullable|integer',
            'imagen_k' => 'nullable|string|max:255',
            'id_e' => 'nullable|integer',
        ]);

        $idE = $validated['id_e'] ?? null;
        unset($validated['id_e']);

        if ($error = $this->validarEvento($idE, null)) {
            return $error;
        }

        $kit = Kit::create($validated);
        $this->asignarEvento($kit, $idE);

        return response()->json([
            'message' => 'Kit creado correctamente',
            'kit' => $kit->load('eventos:id_e,nombre_e,id_k')
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
            'precio_k' => 'sometimes|required|numeric|min:0|max:99999999',
            'fecha_entrega_k' => 'nullable|date',
            'lugar_entrega_k' => 'nullable|string|max:45',
            'contenido_k' => 'nullable|string|max:255',
            'talla_camiseta_k' => 'nullable|string|max:10',
            'numero_dorsal_k' => 'nullable|integer',
            'imagen_k' => 'nullable|string|max:255',
            'id_e' => 'nullable|integer',
        ]);

        // Solo se toca la asignación si el formulario envía el campo id_e.
        $cambiaEvento = $request->exists('id_e');
        $idE = $validated['id_e'] ?? null;
        unset($validated['id_e']);

        if ($cambiaEvento && ($error = $this->validarEvento($idE, $kit->id_k))) {
            return $error;
        }

        $kit->update($validated);

        if ($cambiaEvento) {
            $this->asignarEvento($kit, $idE);
        }

        return response()->json([
            'message' => 'Kit actualizado correctamente',
            'kit' => $kit->load('eventos:id_e,nombre_e,id_k')
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

    /**
     * Verifica que el evento exista y no tenga ya otro kit.
     * Devuelve una respuesta 422 si no es válido, o null si todo está bien.
     */
    private function validarEvento(?int $idE, ?int $idKit)
    {
        if (!$idE) {
            return null;
        }

        $evento = EventoDeportivo::where('id_e', $idE)->first();

        if (!$evento) {
            return response()->json(['message' => 'El evento seleccionado no existe'], 422);
        }

        if ($evento->id_k && (int) $evento->id_k !== (int) $idKit) {
            return response()->json(['message' => 'Ese evento ya tiene otro kit asignado'], 422);
        }

        return null;
    }

    /**
     * Deja el kit asignado únicamente al evento indicado (o a ninguno si es null).
     */
    private function asignarEvento(Kit $kit, ?int $idE): void
    {
        // Libera los eventos que tenían este kit y ya no corresponden.
        EventoDeportivo::where('id_k', $kit->id_k)
            ->when($idE, fn ($q) => $q->where('id_e', '!=', $idE))
            ->update(['id_k' => null]);

        if ($idE) {
            EventoDeportivo::where('id_e', $idE)->update(['id_k' => $kit->id_k]);
        }
    }
}