<?php

namespace App\Http\Controllers;

use App\Models\Resultado;
use App\Models\Inscripcion;
use App\Models\CategoriaResultado;
use Illuminate\Http\Request;

class ResultadoController extends Controller
{
    public function index(Request $request)
    {
        $resultados = Resultado::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'categoriaResultado.categoria',
        ])->paginate($request->input('per_page', 15));

        return response()->json([
            'resultados' => $resultados
        ]);
    }

    public function show($id)
    {
        $resultado = Resultado::with([
            'inscripcion.usuario',
            'inscripcion.evento',
            'categoriaResultado.categoria',
        ])->find($id);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        return response()->json([
            'resultado' => $resultado
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tiempo_final_r' => 'required|date_format:H:i:s',
            'posicion_general_r' => 'nullable|integer|min:1',
            'estado_r' => 'required|in:oficial,en revision,descalificado',
            'id_i' => 'required|exists:inscripcion,id_i',

            'posicion_categoria' => 'nullable|integer|min:1',
            'estado_competidor' => 'nullable|in:clasificado,descalificado',
            'id_cc' => 'nullable|exists:categoria_competencia,id_cc',
        ]);

        $inscripcion = Inscripcion::find($validated['id_i']);

        if (!$inscripcion) {
            return response()->json([
                'message' => 'Inscripción no encontrada'
            ], 404);
        }

        // El participante debe tener la inscripción confirmada
        if ($inscripcion->estado_i !== 'confirmada') {
            return response()->json([
                'message' => 'Solo se puede registrar resultado de una inscripción confirmada',
                'estado_i' => $inscripcion->estado_i
            ], 422);
        }

        // Evitar resultados duplicados
        $resultadoExistente = Resultado::where(
            'id_i',
            $validated['id_i']
        )->exists();

        if ($resultadoExistente) {
            return response()->json([
                'message' => 'Esta inscripción ya tiene un resultado'
            ], 409);
        }

        $resultado = Resultado::create([
            'tiempo_final_r' => $validated['tiempo_final_r'],
            'posicion_general_r' => $validated['posicion_general_r'] ?? null,
            'estado_r' => $validated['estado_r'],
            'id_i' => $validated['id_i'],
        ]);

        // Registrar posición dentro de la categoría
        if (
            isset($validated['posicion_categoria']) &&
            isset($validated['id_cc'])
        ) {
            CategoriaResultado::create([
                'posicion_categoria' => $validated['posicion_categoria'],
                'estado_competidor' => $validated['estado_competidor'] ?? 'clasificado',
                'id_cc' => $validated['id_cc'],
                'id_r' => $resultado->id_r,
            ]);
        }

        return response()->json([
            'message' => 'Resultado registrado correctamente',
            'resultado' => $resultado->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'categoriaResultado.categoria',
            ])
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $resultado = Resultado::find($id);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'tiempo_final_r' => 'sometimes|date_format:H:i:s',
            'posicion_general_r' => 'sometimes|nullable|integer|min:1',
            'estado_r' => 'sometimes|in:oficial,en revision,descalificado',
        ]);

        // Actualizar el resultado
        $resultado->update($validated);

        return response()->json([
            'message' => 'Resultado actualizado correctamente',
            'resultado' => $resultado->fresh()->load([
                'inscripcion.usuario',
                'inscripcion.evento',
                'categoriaResultado.categoria',
            ])
        ]);
    }

    public function destroy($id)
    {
        $resultado = Resultado::find($id);

        if (!$resultado) {
            return response()->json([
                'message' => 'Resultado no encontrado'
            ], 404);
        }

        $resultado->delete();

        return response()->json([
            'message' => 'Resultado eliminado correctamente'
        ]);
    }
}