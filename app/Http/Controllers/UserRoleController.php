<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Ver los roles actuales de un usuario.
     */
    public function index(Request $request, $userId)
    {
        $user = User::with('roles')->visiblesPara($request->user())->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'roles' => $user->roles
        ]);
    }

    /**
     * Asignar un rol (en un contexto) a un usuario.
     * Si ya lo tenía exactamente igual (mismo rol + mismo contexto),
     * no lo duplica.
     */
    public function store(AssignRoleRequest $request, $userId)
    {
        $user = User::visiblesPara($request->user())->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validated = $request->validated();

        if (!$request->user()->puedeGestionarRol($validated['nombre_rol'])) {
            return response()->json([
                'message' => 'No tienes permisos para asignar ese rol'
            ], 403);
        }

        $rol = Role::where('nombre_rol', $validated['nombre_rol'])->first();

        $yaLoTiene = $user->roles()
            ->where('nombre_rol', $validated['nombre_rol'])
            ->wherePivot('contexto', $validated['contexto'])
            ->exists();

        if (!$yaLoTiene) {
            $user->roles()->attach($rol->id_rol, [
                'contexto' => $validated['contexto']
            ]);
        }

        return response()->json([
            'message' => 'Rol asignado correctamente',
            'roles' => $user->fresh()->roles
        ], 201);
    }

    /**
     * Quitar un rol (en un contexto específico) a un usuario.
     */
    public function destroy(Request $request, $userId, $rolId)
    {
        $user = User::visiblesPara($request->user())->find($userId);

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $rol = Role::find($rolId);
        if ($rol && !$request->user()->puedeGestionarRol($rol->nombre_rol)) {
            return response()->json([
                'message' => 'No tienes permisos para quitar ese rol'
            ], 403);
        }

        $contexto = $request->query('contexto');

        $query = $user->roles();

        if ($contexto) {
            $query->wherePivot('contexto', $contexto);
        }

        $query->detach($rolId);

        return response()->json([
            'message' => 'Rol removido correctamente',
            'roles' => $user->fresh()->roles
        ]);
    }
}
