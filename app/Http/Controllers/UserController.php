<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

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
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

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
    public function update(UpdateUserRequest $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validated = $request->validated();

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