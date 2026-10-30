<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    /**
     * Un administrador no puede cambiarle los roles a otro administrador
     * (solo el super administrador puede). Sobre sí mismo sí puede asignar.
     */
    private function esOtroAdmin(User $quien, User $objetivo): bool
    {
        return $quien->id_u !== $objetivo->id_u
            && !$quien->esSuperAdmin()
            && $objetivo->esAdministrador();
    }

    private function respuestaOtroAdmin()
    {
        return response()->json([
            'message' => 'No puedes cambiar los roles de otro administrador.'
        ], 403);
    }

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

        if ($this->esOtroAdmin($request->user(), $user)) {
            return $this->respuestaOtroAdmin();
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

        if ($this->esOtroAdmin($request->user(), $user)) {
            return $this->respuestaOtroAdmin();
        }

        $rol = Role::find($rolId);

        // Nadie puede quitarse a sí mismo un rol de administrador (se
        // quedaría fuera del panel).
        if ($rol && $request->user()->id_u === $user->id_u
            && str_starts_with(mb_strtolower($rol->nombre_rol), 'admin')) {
            return response()->json([
                'message' => 'No puedes quitarte tu propio rol de administrador.'
            ], 403);
        }

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
