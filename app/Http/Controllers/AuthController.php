<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
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
    public function login(LoginRequest $request): JsonResponse
    {
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
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Verificación de duplicados para el Toast de React
        if (User::where('correo_u', $validated['correo_u'])->exists()) {
            return response()->json([
                'message' => 'Este correo electrónico ya se encuentra registrado.'
            ], 400);
        }

        if (User::where('documento_u', $validated['documento_u'])->exists()) {
            return response()->json([
                'message' => 'Este número de documento ya se encuentra registrado.'
            ], 400);
        }

        if (User::where('telefono_u', $validated['telefono_u'])->exists()) {
            return response()->json([
                'message' => 'Este número de teléfono ya se encuentra registrado.'
            ], 400);
        }

        // 2. Procesamiento normal del usuario
        $contexto = $validated['contexto'] ?? 'deportivo';
        unset($validated['contexto']);

        $validated['contrasena_u'] = Hash::make($validated['contrasena_u']);
        $validated['estado_u'] = 'activo';
        $validated['rh_u'] = 'N/A';

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
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
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
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('correo_u', $validated['correo_u'])->first();

        if (!$user || $user->codigo !== $validated['token']) {
            return response()->json([
                'message' => 'El código ingresado es incorrecto o no existe'
            ], 400);
        }

        if (now()->greaterThan($user->codigo_expira_at)) {
            return response()->json([
                'message' => 'El código ha expirado. Por favor, solicita uno nuevo.'
            ], 400);
        }

        $user->contrasena_u = Hash::make($validated['contrasena_u']);
        
        $user->codigo = null;
        $user->codigo_expira_at = null;
        $user->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ], 200);
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
    public function enviarCodigoRecuperacion(ForgotPasswordRequest $request)
    {
        $user = User::where('correo_u', $request->correo_u)->first();

        if (!$user) {
            return response()->json(['message' => 'El correo no está registrado en Skyed'], 404);
        }

        $codigoGenerado = rand(100000, 999999);
        $user->codigo = $codigoGenerado;
        $user->codigo_expira_at = now()->addMinutes(15);
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