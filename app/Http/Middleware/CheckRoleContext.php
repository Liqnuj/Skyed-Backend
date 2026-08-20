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

        $permitido = $contexto !== null
            ? $user->hasRoleInContext($role, $contexto)
            : $user->hasRole($role);

        if (!$permitido) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}