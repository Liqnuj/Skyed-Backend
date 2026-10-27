<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /** Máximo de filas por página que se acepta desde el cliente. */
    protected const PER_PAGE_MAX = 500;

    /**
     * per_page llega por la URL, o sea que cualquiera lo puede cambiar
     * (?per_page=999999 volcaba la tabla completa en una sola respuesta).
     * Se fuerza a un entero entre 1 y PER_PAGE_MAX.
     */
    protected function perPage(Request $request, int $porDefecto = 15): int
    {
        return max(1, min((int) $request->input('per_page', $porDefecto), self::PER_PAGE_MAX));
    }

    /**
     * Defensa en profundidad: aunque la ruta ya lleve el middleware de rol,
     * la acción vuelve a comprobarlo. Devuelve la respuesta 403 si el usuario
     * no tiene ninguno de los roles (separados por '|'), o null si puede seguir.
     */
    protected function exigirRol(Request $request, string $roles): ?JsonResponse
    {
        $user = $request->user();

        foreach (explode('|', $roles) as $rol) {
            if ($user && $user->hasRole($rol)) {
                return null;
            }
        }

        return response()->json([
            'message' => 'No tienes permisos para realizar esta acción'
        ], 403);
    }
}
