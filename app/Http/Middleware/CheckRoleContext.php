<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRoleContext
{
    public function handle(
        Request $request,
        Closure $next,
        string $role,
        ?string $contexto = null
    ): Response {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Permite pasar varios roles separados por "|" para lógica OR,
        // ej: role.context:adminDeportivo|adminSocial
        $rolesPermitidos = explode('|', $role);

        $permitido = collect($rolesPermitidos)->contains(function ($rol) use ($user, $contexto) {
            return $contexto !== null
                ? $user->hasRoleInContext($rol, $contexto)
                : $user->hasRole($rol);
        });

        if (!$permitido) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}