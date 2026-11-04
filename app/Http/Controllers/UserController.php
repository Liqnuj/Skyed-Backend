<?php

namespace App\Http\Controllers;

use App\Models\Role;
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
            ->visiblesPara($request->user())
            ->orderBy('id_u') // En orden de ID: sin esto PostgreSQL los devuelve en cualquier orden.
            ->paginate($this->perPage($request));

        // La foto de perfil, para mostrarla en la tabla de usuarios del panel.
        $users->getCollection()->each->append('foto_url');

        return response()->json($users);
    }

    /**
     * Mostrar un usuario específico.
     */
    public function show(Request $request, $id)
    {
        $user = User::with('roles')->visiblesPara($request->user())->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'user' => $user->append('foto_url')
        ]);
    }

    /**
     * Crear un nuevo usuario.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        // No se puede crear a alguien con un rol que el actor no puede dar
        // (p. ej. un admin social creando un admin deportivo).
        $rol = Role::find($validated['id_rol']);
        if ($rol && !$request->user()->puedeGestionarRol($rol->nombre_rol)) {
            return response()->json([
                'message' => 'No tienes permisos para asignar ese rol'
            ], 403);
        }

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
        $user = User::visiblesPara($request->user())->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $actor = $request->user();
        $esPropio = $user->id_u === $actor->id_u;

        // Un admin de módulo no edita a otros administradores: cambiándoles el
        // correo o la contraseña podría quedarse con su cuenta.
        if (!$esPropio && $user->esAdministrador() && !$actor->esSuperAdmin()) {
            return response()->json([
                'message' => 'Solo el super administrador puede editar a otros administradores.'
            ], 403);
        }

        $validated = $request->validated();

        if ($esPropio && ($validated['estado_u'] ?? 'activo') === 'inactivo') {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta de administrador desde aquí'
            ], 422);
        }

        // Si viene contraseña nueva, la encriptamos.
        // Si no viene, no la tocamos.
        if (array_key_exists('contrasena_u', $validated)) {
            $validated['contrasena_u'] = Hash::make($validated['contrasena_u']);
        }

        $seDesactiva = ($validated['estado_u'] ?? null) === 'inactivo' && $user->estado_u !== 'inactivo';

        $user->update($validated);

        // Igual que al desactivar desde DELETE: se cierran sus sesiones abiertas.
        if ($seDesactiva) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Usuario actualizado correctamente',
            'user' => $user->fresh()->load('roles')
        ]);
    }

    /**
     * "Eliminar" un usuario.
     *
     * FIX: la ruta DELETE /users/{id} existía pero este método no, así que
     * respondía 500. No se borra el registro (tiene inscripciones, pagos,
     * reservas y PQR asociados): se desactiva y se cierran sus sesiones,
     * igual que cuando el usuario desactiva su propia cuenta. Un admin
     * puede reactivarlo con PUT /users/{id} y estado_u = activo.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::visiblesPara($request->user())->find($id);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        if ($user->id_u === $request->user()->id_u) {
            return response()->json([
                'message' => 'No puedes desactivar tu propia cuenta de administrador desde aquí'
            ], 422);
        }

        $user->estado_u = 'inactivo';
        $user->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Usuario desactivado correctamente'
        ]);
    }
}