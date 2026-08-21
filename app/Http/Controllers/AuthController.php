<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

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
            'user' => $this->formatUser($user),
        ]);
    }

    /**
     * Registro público (autoservicio de participantes/clientes).
     *
     * Por defecto asigna el rol "Participante" en contexto "deportivo".
     * Si el frontend necesita registrar clientes del módulo social,
     * envía "contexto": "social" en el body.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_documento_u' => 'required|string',
            'documento_u' => 'required|integer|unique:usuario,documento_u',
            'nombre_u' => 'required|string',
            'apellido_u' => 'required|string',
            'rh_u' => 'required|string',
            'telefono_u' => 'required|string|unique:usuario,telefono_u',
            'correo_u' => 'required|email|unique:usuario,correo_u',
            'contrasena_u' => 'required|string|min:8|confirmed',
            'fecha_nacimiento_u' => 'required|date',
            'contexto' => 'sometimes|string|in:deportivo,social',
        ]);

        $contexto = $validated['contexto'] ?? 'deportivo';
        unset($validated['contexto']);

        $validated['contrasena_u'] = Hash::make($validated['contrasena_u']);
        $validated['estado_u'] = 'activo';

        $user = User::create($validated);

        $rolParticipante = Role::firstOrCreate(['nombre_rol' => 'Participante']);
        $user->roles()->attach($rolParticipante->id_rol, ['contexto' => $contexto]);

        $user->load('roles');

        $token = $user->createToken('skyed-token')->plainTextToken;

        return response()->json([
            'message' => 'Registro exitoso',
            'token' => $token,
            'user' => $this->formatUser($user),
        ], 201);
    }

    /**
     * Solicitar enlace/código de recuperación de contraseña.
     *
     * Genera un token, lo guarda en password_reset_tokens y lo envía
     * por correo. Con MAIL_MAILER=log (valor por defecto en .env.example)
     * el correo no se envía de verdad, solo queda escrito en
     * storage/logs/laravel.log — útil para probar en desarrollo antes
     * de configurar un mailer real.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'correo_u' => 'required|email',
        ]);

        $user = User::where('correo_u', $request->correo_u)->first();

        // Respuesta genérica aunque el correo no exista, para no revelar
        // qué correos están registrados.
        if (!$user) {
            return response()->json([
                'message' => 'Si el correo existe, se enviaron instrucciones de recuperación'
            ]);
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->correo_u],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $frontendUrl = config('app.frontend_reset_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $resetLink = "{$frontendUrl}/reset-password?token={$token}&email=" . urlencode($user->correo_u);

        Mail::raw(
            "Hola {$user->nombre_u},\n\n" .
            "Recibimos una solicitud para restablecer tu contraseña en SkyEd.\n" .
            "Usa este enlace (válido por 60 minutos):\n{$resetLink}\n\n" .
            "Si no fuiste tú, ignora este correo.",
            function ($message) use ($user) {
                $message->to($user->correo_u)
                    ->subject('Recuperación de contraseña - SkyEd');
            }
        );

        return response()->json([
            'message' => 'Si el correo existe, se enviaron instrucciones de recuperación'
        ]);
    }

    /**
     * Restablecer la contraseña con el token recibido por correo.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'correo_u' => 'required|email',
            'token' => 'required|string',
            'contrasena_u' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $validated['correo_u'])
            ->first();

        if (!$record || !Hash::check($validated['token'], $record->token)) {
            return response()->json([
                'message' => 'Token inválido o expirado'
            ], 400);
        }

        $expiraEn = 60; // minutos
        if (Carbon::parse($record->created_at)->addMinutes($expiraEn)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $validated['correo_u'])->delete();

            return response()->json([
                'message' => 'Token inválido o expirado'
            ], 400);
        }

        $user = User::where('correo_u', $validated['correo_u'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Token inválido o expirado'
            ], 400);
        }

        $user->update([
            'contrasena_u' => Hash::make($validated['contrasena_u']),
        ]);

        // Invalida el token y todas las sesiones activas del usuario
        DB::table('password_reset_tokens')->where('email', $validated['correo_u'])->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ]);
    }

    /**
     * Obtener el usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()),
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

    /**
     * Formato consistente del usuario para las respuestas de auth.
     */
    private function formatUser(User $user): array
    {
        return [
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
        ];
    }
}
