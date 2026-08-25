<?php

namespace App\Http\Controllers;

use App\Models\Premio;
use App\Models\Resultado;
use Illuminate\Http\Request;

class PremioController extends Controller
{
    public function index(Request $request)
    {
        $premios = Premio::with([
            'resultado.inscripcion.usuario',
            'resultado.inscripcion.evento',
        ])->paginate($request->input('per_page', 15));

        return response()->json([
            'premios' => $premios
        ]);
    }

    public function show($id)
    {
        $premio = Premio::with([
            'resultado.inscripcion.usuario',
            'resultado.inscripcion.evento',
        ])->find($id);

        if (!$premio) {
            return response()->json([
                'message' => 'Premio no encontrado'
            ], 404);
        }

        return response()->json([
            'premio' => $premio
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre_premio' => 'required|string|max:100',
            'descripcion_premio' => 'nullable|string|max:255',
            'posicion_premio' => 'required|integer|min:1',
            'valor_premio' => 'nullable|numeric|min:0',
            'id_r' => 'required|exists:resultado,id_r',
        ]);

        $resultado = Resultado::find($validated['id_r']);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        if ($resultado->estado_r !== 'oficial') {
            return response()->json([
                'message' => 'Solo se puede asignar un premio a un resultado oficial',
                'estado_r' => $resultado->estado_r
            ], 422);
        }

        $premioExistente = Premio::where(
            'id_r',
            $validated['id_r']
        )->exists();

        if ($premioExistente) {
            return response()->json([
                'message' => 'Este resultado ya tiene un premio'
            ], 409);
        }

        $premio = Premio::create([
            'nombre_premio' => $validated['nombre_premio'],
            'descripcion_premio' => $validated['descripcion_premio'] ?? null,
            'posicion_premio' => $validated['posicion_premio'],
            'valor_premio' => $validated['valor_premio'] ?? null,
            'id_r' => $validated['id_r'],
        ]);

        return response()->json([
            'message' => 'Premio registrado correctamente',
            'premio' => $premio->fresh()->load([
                'resultado.inscripcion.usuario',
                'resultado.inscripcion.evento',
            ])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $premio = Premio::find($id);

        if (!$premio) {
            return response()->json([
                'message' => 'Premio no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'nombre_premio' => 'sometimes|string|max:100',
            'descripcion_premio' => 'sometimes|nullable|string|max:255',
            'posicion_premio' => 'sometimes|integer|min:1',
            'valor_premio' => 'sometimes|nullable|numeric|min:0',
        ]);

        $premio->update($validated);

        return response()->json([
            'message' => 'Premio actualizado correctamente',
            'premio' => $premio->fresh()->load([
                'resultado.inscripcion.usuario',
                'resultado.inscripcion.evento',
            ])
        ]);
    }

    public function destroy($id)
    {
        $premio = Premio::find($id);

        if (!$premio) {
            return response()->json([
                'message' => 'Premio no encontrado'
            ], 404);
        }

        $premio->delete();

        return response()->json([
            'message' => 'Premio eliminado correctamente'
        ]);
    }
}