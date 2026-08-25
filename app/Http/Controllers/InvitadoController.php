<?php

namespace App\Http\Controllers;

use App\Models\Invitado;
use Illuminate\Http\Request;

class InvitadoController extends Controller
{
    /**
     * Listar invitados.
     */
    public function index(Request $request)
    {
        $invitados = Invitado::paginate(
            $request->input('per_page', 15)
        );

        return response()->json($invitados);
    }

    /**
     * Mostrar un invitado específico.
     */
    public function show($id)
    {
        $invitado = Invitado::with('usuarios')->find($id);

        if (!$invitado) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        return response()->json([
            'invitado' => $invitado
        ]);
    }

    /**
     * Crear un invitado.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_documento' => 'required|string|max:30',
            'documento_inv' => 'required|integer|unique:invitados,documento_inv',
            'nombre_inv' => 'required|string|max:50',
            'apellido_inv' => 'required|string|max:50',
            'rh_inv' => 'required|string|max:5',
            'telefono_inv' => 'required|string|max:50|unique:invitados,telefono_inv',
            'fecha_nacimiento_inv' => 'required|date',
            'correo_inv' => 'nullable|email|max:80|unique:invitados,correo_inv',
        ]);

        $invitado = Invitado::create($validated);

        return response()->json([
            'message' => 'Invitado creado correctamente',
            'invitado' => $invitado
        ], 201);
    }

    /**
     * Actualizar un invitado.
     */
    public function update(Request $request, $id)
    {
        $invitado = Invitado::find($id);

        if (!$invitado) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'tipo_documento' => 'sometimes|required|string|max:30',
            'documento_inv' => 'sometimes|required|integer|unique:invitados,documento_inv,' . $id . ',id_inv',
            'nombre_inv' => 'sometimes|required|string|max:50',
            'apellido_inv' => 'sometimes|required|string|max:50',
            'rh_inv' => 'sometimes|required|string|max:5',
            'telefono_inv' => 'sometimes|required|string|max:50|unique:invitados,telefono_inv,' . $id . ',id_inv',
            'fecha_nacimiento_inv' => 'sometimes|required|date',
            'correo_inv' => 'nullable|email|max:80|unique:invitados,correo_inv,' . $id . ',id_inv',
        ]);

        $invitado->update($validated);

        return response()->json([
            'message' => 'Invitado actualizado correctamente',
            'invitado' => $invitado
        ]);
    }

    /**
     * Eliminar un invitado.
     *
     * No se puede borrar si sigue vinculado a un usuario (usuario.id_inv),
     * para no dejar esa referencia huérfana de forma silenciosa.
     */
    public function destroy($id)
    {
        $invitado = Invitado::find($id);

        if (!$invitado) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        if ($invitado->usuarios()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: hay un usuario vinculado a este invitado'
            ], 409);
        }

        $invitado->delete();

        return response()->json([
            'message' => 'Invitado eliminado correctamente'
        ]);
    }
}
