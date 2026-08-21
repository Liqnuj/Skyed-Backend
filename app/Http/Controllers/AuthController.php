<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use App\Mail\CodigoVerificacionMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;


class AuthController extends Controller
{
    /**
     * Iniciar sesión
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'correo_u' => 'required|email',
            'contrasena_u' => 'required|string',
        ]);

        $user = User::where('correo_u', $request->correo_u)->first();

        if (!$user || !Hash::check($request->contrasena_u, $user->contrasena_u)) {
            return response()->json([
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $token = $user->createToken('skyed-token')->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso',
            'token' => $token,
            'user' => [
                'id_u' => $user->id_u,
                'nombre_u' => $user->nombre_u,
                'apellido_u' => $user->apellido_u,
                'correo_u' => $user->correo_u,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id_rol' => $role->id_rol,
                        'nombre_rol' => $role->nombre_rol,
                        'contexto' => $role->pivot->contexto,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Obtener el usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id_u' => $user->id_u,
                'nombre_u' => $user->nombre_u,
                'apellido_u' => $user->apellido_u,
                'correo_u' => $user->correo_u,
                'roles' => $user->roles->map(function ($role) {
                    return [
                        'id_rol' => $role->id_rol,
                        'nombre_rol' => $role->nombre_rol,
                        'contexto' => $role->pivot->contexto,
                    ];
                }),
            ],
        ]);
    }
    /**
 * Cerrar sesión
 */
public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Sesión cerrada correctamente'
    ]);
}
public function enviarCodigoRecuperacion(Request $request)
{
    $request->validate([
        'correo_u' => 'required|email'
    ]);

    $user = User::where('correo_u', $request->correo_u)->first();

    if (!$user) {
        return response()->json(['message' => 'El correo no está registrado en Skyed'], 404);
    }

    $codigoGenerado = rand(100000, 999999);

    $user->codigo = $codigoGenerado;
    $user->save();
    
    Mail::to($user->correo_u)->send(new CodigoVerificacionMail((string)$codigoGenerado));

    return response()->json([
        'message' => 'Código de verificación enviado con éxito'
    ], 200);
}
}