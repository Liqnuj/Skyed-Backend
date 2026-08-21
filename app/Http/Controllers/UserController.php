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
    public function index()
    {
        $users = User::with('roles')->get();

        return response()->json([
            'users' => $users
        ]);
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
            'tipo_documento_u' => 'sometimes|required|string',
            'documento_u' => 'sometimes|required|integer|unique:usuario,documento_u,' . $id . ',id_u',
            'nombre_u' => 'sometimes|required|string',
            'apellido_u' => 'sometimes|required|string',
            'rh_u' => 'sometimes|required|string',
            'telefono_u' => 'sometimes|required|string',
            'correo_u' => 'sometimes|required|email|unique:usuario,correo_u,' . $id . ',id_u',
            'contrasena_u' => 'sometimes|required|string|min:8',
            'fecha_nacimiento_u' => 'sometimes|required|date',
            'estado_u' => 'sometimes|required|string',

            // Rol y contexto (opcionales: solo si se quiere reasignar)
            'id_rol' => 'sometimes|required|exists:roles,id_rol',
            'contexto' => 'sometimes|required|string',
        ]);

        // Encriptar contraseña solo si viene en la petición
        if (isset($validated['contrasena_u'])) {
            $validated['contrasena_u'] = Hash::make(
                $validated['contrasena_u']
            );
        }

        // Separamos rol/contexto porque no pertenecen a la tabla usuario
        $idRol = $validated['id_rol'] ?? null;
        $contexto = $validated['contexto'] ?? null;
        unset($validated['id_rol'], $validated['contexto']);

        $user->update($validated);

        // Si se envió rol + contexto, se reemplaza la asignación
        // (evita duplicar filas para el mismo usuario/rol/contexto)
        if ($idRol !== null && $contexto !== null) {
            $user->roles()->syncWithoutDetaching([
                $idRol => ['contexto' => $contexto]
            ]);
        }

        $user->load('roles');

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user
        ]);
    }

    /**
     * Eliminar un usuario.
     */
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Revoca todos los tokens de acceso del usuario antes de borrarlo
        $user->tokens()->delete();
        $user->roles()->detach();
        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado correctamente'
        ]);
    }
}
