<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Mostrar todos los usuarios.
     */
    public function index(Request $request)
    {
        $users = User::with('roles')
            ->paginate($request->input('per_page', 15));

        return response()->json($users);
    }

    /**
     * Mostrar un usuario específico.
     */
    public function show($id)
    {
        $user = User::with('roles')->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo_documento_u' => 'required|string',
            'documento_u' => 'required|integer|unique:usuario,documento_u',
            'nombre_u' => 'required|string',
            'apellido_u' => 'required|string',
            'rh_u' => 'required|string',
            'telefono_u' => 'required|string',
            'correo_u' => 'required|email|unique:usuario,correo_u',
            'contrasena_u' => 'required|string|min:8',
            'fecha_nacimiento_u' => 'required|date',

            // Rol y contexto
            'id_rol' => 'required|exists:roles,id_rol',
            'contexto' => 'required|string',
        ]);

        // Encriptar contraseña
        $validated['contrasena_u'] = Hash::make(
            $validated['contrasena_u']
        );

        // Estado inicial
        $validated['estado_u'] = 'activo';

        // Guardamos los datos del usuario
        $userData = $validated;

        // Quitamos estos campos porque no pertenecen
        // directamente a la tabla usuario
        unset($userData['id_rol']);
        unset($userData['contexto']);

        // Crear usuario
        $user = User::create($userData);

        // Asignar rol y contexto
        $user->roles()->attach(
            $validated['id_rol'],
            [
                'contexto' => $validated['contexto']
            ]
        );

        // Recargar relaciones
        $user->load('roles');

        return response()->json([
            'message' => 'Usuario creado correctamente',
            'user' => $user
        ], 201);
    }

    /**
     * Actualizar un usuario existente.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validated = $request->validate([
            'tipo_documento_u' => 'sometimes|string',
            'documento_u' => 'sometimes|integer|unique:usuario,documento_u,' . $user->id_u . ',id_u',
            'nombre_u' => 'sometimes|string',
            'apellido_u' => 'sometimes|string',
            'rh_u' => 'sometimes|string',
            'telefono_u' => 'sometimes|string|unique:usuario,telefono_u,' . $user->id_u . ',id_u',
            'correo_u' => 'sometimes|email|unique:usuario,correo_u,' . $user->id_u . ',id_u',
            'contrasena_u' => 'sometimes|string|min:8',
            'fecha_nacimiento_u' => 'sometimes|date',
            'estado_u' => 'sometimes|in:activo,inactivo',
        ]);

        // Si viene contraseña nueva, la encriptamos.
        // Si no viene, no la tocamos.
        if (array_key_exists('contrasena_u', $validated)) {
            $validated['contrasena_u'] = Hash::make($validated['contrasena_u']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user->fresh()->load('roles')
        ]);
    }
}