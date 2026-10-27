<?php

namespace App\Http\Controllers;

use App\Models\Invitado;
use App\Support\ReglasUsuario;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvitadoController extends Controller
{
    /**
     * Listar invitados.
     */
    public function index(Request $request)
    {
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

        $invitados = Invitado::paginate(
            $this->perPage($request)
        );

        return response()->json($invitados);
    }

    /**
     * Mostrar un invitado específico.
     */
    public function show(Request $request, $id)
    {
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

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
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

        $validated = $request->validate([
            'tipo_documento' => 'required|in:CC,TI,CE,PA',
            'documento_inv' => 'required|integer|digits_between:5,12|unique:invitados,documento_inv',
            'nombre_inv' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_inv' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'rh_inv' => ['required', Rule::in(ReglasUsuario::RH)],
            'telefono_inv' => 'required|digits_between:7,15|unique:invitados,telefono_inv',
            'fecha_nacimiento_inv' => 'required|date|after:1900-01-01|before_or_equal:today',
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
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

        $invitado = Invitado::find($id);

        if (!$invitado) {
            return response()->json([
                'message' => 'Invitado no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'tipo_documento' => 'sometimes|required|in:CC,TI,CE,PA',
            'documento_inv' => 'sometimes|required|integer|digits_between:5,12|unique:invitados,documento_inv,' . $id . ',id_inv',
            'nombre_inv' => ['sometimes', 'required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_inv' => ['sometimes', 'required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'rh_inv' => ['sometimes', 'required', Rule::in(ReglasUsuario::RH)],
            'telefono_inv' => 'sometimes|required|digits_between:7,15|unique:invitados,telefono_inv,' . $id . ',id_inv',
            'fecha_nacimiento_inv' => 'sometimes|required|date|after:1900-01-01|before_or_equal:today',
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
    public function destroy(Request $request, $id)
    {
        if ($denegado = $this->exigirRol($request, 'adminDeportivo')) {
            return $denegado;
        }

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
