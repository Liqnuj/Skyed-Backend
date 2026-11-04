<?php

namespace App\Http\Middleware;

use App\Support\PermisosAsistente;
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

        // Soporta 'adminDeportivo|adminSocial' -> pasa si el usuario
        // tiene AL MENOS UNO de los roles separados por '|'.
        $rolesPermitidos = explode('|', $role);

        $permitido = false;

        foreach ($rolesPermitidos as $rolPermitido) {
            $permitido = $contexto !== null
                ? $user->hasRoleInContext($rolPermitido, $contexto)
                : $user->hasRole($rolPermitido);

            if ($permitido) {
                break;
            }
        }

        if (!$permitido) {
            $asistente = PermisosAsistente::paraUsuario($user);
            if ($asistente) {
                $moduloRol = $asistente->modulo === 'social' ? 'adminSocial' : 'adminDeportivo';
                if (in_array($moduloRol, $rolesPermitidos, true)
                    && PermisosAsistente::puedeEnRuta($request, $asistente, $asistente->modulo)) {
                    return $next($request);
                }
            }

            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción'
            ], 403);
        }

        return $next($request);
    }
}