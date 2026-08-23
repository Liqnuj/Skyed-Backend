<?php

namespace App\Http\Controllers;

use App\Mail\CodigoVerificacionMail;
use App\Models\User;
use App\Models\Role;
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

        $rolNombre = $contexto === 'social' ? 'cliente' : 'participante';
        $rol = Role::firstOrCreate(['nombre_rol' => $rolNombre]);
        $user->roles()->attach($rol->id_rol, ['contexto' => $contexto]);

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
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'correo_u' => 'required|email',
        ]);

        $user = User::where('correo_u', $request->correo_u)->first();

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
     * Enviar código de recuperación
     */
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

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'contrasena_actual' => 'required|string',
            'nueva_contrasena' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->contrasena_actual, $user->contrasena_u)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta'
            ], 400);
        }

        $user->update([
            'contrasena_u' => Hash::make($request->nueva_contrasena)
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ], 200);
    }

}