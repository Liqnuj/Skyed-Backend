<?php

namespace App\Http\Controllers;

use App\Models\EventoDeportivo;
use App\Models\Kit;
use App\Models\KitTalla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KitController extends Controller
{
    /**
     * Listar kits.
     */
    public function index(Request $request)
    {
        // Se incluye el evento al que está asignado cada kit (un kit es de un solo evento)
        // y su inventario por talla.
        $kits = Kit::with(['eventos:id_e,nombre_e,id_k', 'tallas'])->paginate($this->perPage($request));

        return response()->json($kits);
    }

    /**
     * Mostrar un kit específico.
     */
    public function show($id)
    {
        $kit = Kit::with('tallas')->find($id);

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
        $this->normalizarTallas($request);

        $validated = $request->validate([
            'nombre_k' => 'required|string|max:45',
            'precio_k' => 'required|numeric|min:0|max:99999999',
            'fecha_entrega_k' => 'required|date',
            'lugar_entrega_k' => 'required|string|max:45',
            'contenido_k' => 'required|string|max:255',
            'numero_dorsal_k' => 'nullable|integer',
            'imagen_k' => 'required|string|max:255',
            'id_e' => 'required|integer',
            // Inventario: una fila por talla. El stock total del kit es la suma.
            'tallas' => 'required|array|min:1',
            'tallas.*.talla' => 'required|string|max:10|distinct',
            'tallas.*.cantidad' => 'required|integer|min:1|max:100000',
        ], [
            'tallas.required' => 'Agrega al menos una talla con su cantidad.',
            'tallas.min' => 'Agrega al menos una talla con su cantidad.',
            'tallas.*.talla.distinct' => 'No repitas la misma talla.',
            'tallas.*.cantidad.min' => 'La cantidad de cada talla debe ser de al menos 1.',
        ]);

        $idE = $validated['id_e'] ?? null;
        $tallas = $validated['tallas'];
        unset($validated['id_e'], $validated['tallas']);

        if ($error = $this->validarEvento($idE, null)) {
            return $error;
        }

        $kit = DB::transaction(function () use ($validated, $tallas, $idE) {
            $validated['stock_k'] = $this->totalTallas($tallas);

            $kit = Kit::create($validated);
            $this->sincronizarTallas($kit, $tallas);
            $this->asignarEvento($kit, $idE);

            return $kit;
        });

        return response()->json([
            'message' => 'Kit creado correctamente',
            'kit' => $kit->load(['eventos:id_e,nombre_e,id_k', 'tallas'])
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

        $this->normalizarTallas($request);

        $validated = $request->validate([
            'nombre_k' => 'sometimes|required|string|max:45',
            'stock_k' => 'sometimes|integer|min:0', // solo para kits antiguos sin tallas
            'precio_k' => 'sometimes|required|numeric|min:0|max:99999999',
            'fecha_entrega_k' => 'sometimes|required|date',
            'lugar_entrega_k' => 'sometimes|required|string|max:45',
            'contenido_k' => 'sometimes|required|string|max:255',
            'numero_dorsal_k' => 'nullable|integer',
            'imagen_k' => 'sometimes|required|string|max:255',
            'id_e' => 'sometimes|required|integer',
            'tallas' => 'sometimes|required|array|min:1',
            'tallas.*.talla' => 'required|string|max:10|distinct',
            'tallas.*.cantidad' => 'required|integer|min:0|max:100000',
        ], [
            'tallas.min' => 'Agrega al menos una talla con su cantidad.',
            'tallas.*.talla.distinct' => 'No repitas la misma talla.',
        ]);

        // Solo se toca la asignación si el formulario envía el campo id_e.
        $cambiaEvento = $request->exists('id_e');
        $idE = $validated['id_e'] ?? null;
        $tallas = $validated['tallas'] ?? null;
        unset($validated['id_e'], $validated['tallas']);

        if ($cambiaEvento && ($error = $this->validarEvento($idE, $kit->id_k))) {
            return $error;
        }

        DB::transaction(function () use ($kit, $validated, $tallas, $cambiaEvento, $idE) {
            if ($tallas !== null) {
                $validated['stock_k'] = $this->totalTallas($tallas);
            }

            $kit->update($validated);

            if ($tallas !== null) {
                $this->sincronizarTallas($kit, $tallas);
            }

            if ($cambiaEvento) {
                $this->asignarEvento($kit, $idE);
            }
        });

        return response()->json([
            'message' => 'Kit actualizado correctamente',
            'kit' => $kit->load(['eventos:id_e,nombre_e,id_k', 'tallas'])
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

        DB::transaction(function () use ($kit) {
            $kit->tallas()->delete();
            $kit->delete();
        });

        return response()->json([
            'message' => 'Kit eliminado correctamente'
        ]);
    }

    /**
     * Deja las tallas en mayúsculas y sin espacios ("  xl " -> "XL") antes de validar.
     */
    private function normalizarTallas(Request $request): void
    {
        $tallas = $request->input('tallas');

        if (!is_array($tallas)) {
            return;
        }

        $request->merge([
            'tallas' => array_map(function ($t) {
                if (!is_array($t)) {
                    return $t;
                }

                return [
                    'talla' => strtoupper(trim((string) ($t['talla'] ?? ''))),
                    'cantidad' => $t['cantidad'] ?? null,
                ];
            }, $tallas),
        ]);
    }

    private function totalTallas(array $tallas): int
    {
        return (int) array_sum(array_map(fn ($t) => (int) $t['cantidad'], $tallas));
    }

    /**
     * Deja el inventario del kit exactamente como lo envía el formulario:
     * crea o actualiza las tallas enviadas y quita las que ya no están.
     */
    private function sincronizarTallas(Kit $kit, array $tallas): void
    {
        $nombres = array_map(fn ($t) => $t['talla'], $tallas);

        KitTalla::where('id_k', $kit->id_k)->whereNotIn('talla_kt', $nombres)->delete();

        foreach ($tallas as $t) {
            KitTalla::updateOrCreate(
                ['id_k' => $kit->id_k, 'talla_kt' => $t['talla']],
                ['cantidad_kt' => (int) $t['cantidad']]
            );
        }
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