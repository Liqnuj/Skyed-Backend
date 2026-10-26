<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\ForgotPasswordDocumentRequest;
use Illuminate\Support\Facades\Password;
use App\Mail\CodigoVerificacionMail;
use App\Models\User;
use App\Models\Role;
use App\Support\ReglasUsuario;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Iniciar sesión
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = ReglasUsuario::buscarPorCorreo($request->correo_u);

        if (!$user || !Hash::check($request->contrasena_u, $user->contrasena_u)) {
            return response()->json([
                'message' => 'Correo o contraseña incorrectos'
            ], 401);
        }

        // FIX: una cuenta desactivada (por el propio usuario desde su
        // perfil o por un administrador) podía volver a iniciar sesión.
        if ($user->estado_u === 'inactivo') {
            return response()->json([
                'message' => 'Tu cuenta está desactivada. Comunícate con el administrador de Skyed para reactivarla.'
            ], 403);
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

        $contexto = $validated['contexto'] ?? 'deportivo';
        unset($validated['contexto']);

        $validated['contrasena_u'] = Hash::make($validated['contrasena_u']);
        $validated['estado_u'] = 'activo';
        $validated['rh_u'] = 'N/A';

        $user = User::create($validated);

        $nombreRol = $contexto === 'social' ? 'cliente' : 'participante';
        $rol = Role::firstOrCreate(['nombre_rol' => $nombreRol]);
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
     * Recuperación de contraseña mediante documento (tipo y número).
     */
    public function enviarCodigoPorDocumento(ForgotPasswordDocumentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Buscar al usuario por documento
        $user = User::where('tipo_documento_u', $validated['tipo_documento_u'])
                    ->where('documento_u', $validated['documento_u'])
                    ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No se encontró ninguna cuenta asociada a este documento.'
            ], 404);
        }

        $codigoGenerado = random_int(100000, 999999);
        $user->codigo = $codigoGenerado;
        $user->codigo_expira_at = now()->addMinutes(15);
        $user->save();
        RateLimiter::clear($this->claveIntentosCodigo($user));
        
        $emailParts = explode('@', $user->correo_u);
        $emailEnmascarado = substr($emailParts[0], 0, 2) . '***@' . $emailParts[1];

        try {
            Mail::to($user->correo_u)->send(new CodigoVerificacionMail((string)$codigoGenerado));
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No hay conexión a internet o el servidor de correos no responde. Por favor, intenta de nuevo.'
            ], 500);
        }

        return response()->json([
            'message' => 'Código de verificación enviado con éxito',
            'email_real' => $user->correo_u,
            'email_enmascarado' => $emailEnmascarado
        ], 200);
    }

    /**
     * Solicitar enlace/código de recuperación de contraseña.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = ReglasUsuario::buscarPorCorreo($request->correo_u);

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
     * Verificar que el código de 6 dígitos sea correcto antes de cambiar contraseña
     */
    public function verificarCodigo(Request $request): JsonResponse
    {
        $request->validate([
            'correo_u' => 'required|email',
            'token' => 'required|string'
        ]);

        $user = ReglasUsuario::buscarPorCorreo($request->correo_u);

        if ($error = $this->validarCodigoRecuperacion($user, $request->token)) {
            return $error;
        }

        return response()->json(['message' => 'Código válido'], 200);
    }

    /**
     * Restablecer la contraseña con el token recibido por correo.
     */
    /**
     * Restablecer la contraseña con el token recibido por correo.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = ReglasUsuario::buscarPorCorreo($validated['correo_u']);

        if ($error = $this->validarCodigoRecuperacion($user, $validated['token'])) {
            return $error;
        }

        if (Hash::check($validated['contrasena_u'], $user->contrasena_u)) {
            return response()->json([
                'message' => 'La nueva contraseña no puede ser igual a la contraseña actual.'
            ], 400);
        }

        $historial = DB::table('historial_contrasena')
                        ->where('id_u', $user->id_u)
                        ->orderBy('created_at', 'desc')
                        ->take(3) 
                        ->get();

        foreach ($historial as $registro) {
            if (Hash::check($validated['contrasena_u'], $registro->contrasena_hash)) {
                return response()->json([
                    'message' => 'No puedes reutilizar contraseñas recientes. Por favor, elige una nueva.'
                ], 400);
            }
        }

        DB::table('historial_contrasena')->insert([
            'id_u' => $user->id_u,
            'contrasena_hash' => $user->contrasena_u,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->contrasena_u = Hash::make($validated['contrasena_u']);
        $user->codigo = null;
        $user->codigo_expira_at = null;
        $user->save();
        
        $user->tokens()->delete();
        RateLimiter::clear($this->claveIntentosCodigo($user));

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
        $user = ReglasUsuario::buscarPorCorreo($request->correo_u);

        if (!$user) {
            return response()->json(['message' => 'El correo no está registrado en Skyed'], 404);
        }

        // random_int es criptográficamente seguro; rand() es predecible.
        $codigoGenerado = random_int(100000, 999999);
        $user->codigo = $codigoGenerado;
        $user->codigo_expira_at = now()->addMinutes(15);
        $user->save();
        RateLimiter::clear($this->claveIntentosCodigo($user));
        
        try {
            Mail::to($user->correo_u)->send(new CodigoVerificacionMail((string)$codigoGenerado));
            
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'No hay conexión a internet o el servidor de correos no responde. Por favor, intenta de nuevo.'
            ], 500);
        }
        return response()->json([
            'message' => 'Código de verificación enviado con éxito'
        ], 200);
    }

    /**
     * Valida el código de recuperación de 6 dígitos. Devuelve la respuesta
     * de error, o null si el código es correcto y vigente.
     *
     * Tras 5 intentos fallidos el código se invalida: así no se puede
     * adivinar por fuerza bruta aunque se cambie de IP (el throttle de la
     * ruta solo limita por IP).
     */
    private function validarCodigoRecuperacion(?User $user, string $token): ?JsonResponse
    {
        $incorrecto = response()->json([
            'message' => 'El código ingresado es incorrecto o no existe'
        ], 400);

        if (!$user || !$user->codigo) {
            return $incorrecto;
        }

        $clave = $this->claveIntentosCodigo($user);

        if (!hash_equals((string) $user->codigo, trim($token))) {
            RateLimiter::hit($clave, 15 * 60);

            if (RateLimiter::attempts($clave) >= 5) {
                $user->codigo = null;
                $user->codigo_expira_at = null;
                $user->save();
                RateLimiter::clear($clave);

                return response()->json([
                    'message' => 'Demasiados intentos fallidos. Por seguridad el código se anuló: solicita uno nuevo.'
                ], 429);
            }

            return $incorrecto;
        }

        if (!$user->codigo_expira_at || now()->greaterThan($user->codigo_expira_at)) {
            return response()->json([
                'message' => 'El código ha expirado. Por favor, solicita uno nuevo.'
            ], 400);
        }

        return null;
    }

    private function claveIntentosCodigo(User $user): string
    {
        return 'codigo-recuperacion:' . $user->id_u;
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
            'ciudad_u' => $user->ciudad_u,
            'telefono_u' => $user->telefono_u,
            'tipo_documento_u' => $user->tipo_documento_u,
            'documento_u' => $user->documento_u,
            'fecha_nacimiento_u' => $user->fecha_nacimiento_u,
            'foto_url' => $user->foto_u ? asset('storage/' . $user->foto_u) : null,
            'roles' => $user->roles->map(function ($role) {
                return [
                    'id_rol' => $role->id_rol,
                    'nombre_rol' => $role->nombre_rol,
                    'contexto' => $role->pivot->contexto,
                ];
            }),
        ];
    }

        /**
     * Actualizar los datos personales del usuario logueado
     * (autoservicio — no requiere rol de admin).
     */
    public function updatePerfil(Request $request): JsonResponse
    {
        $user = $request->user();

        // Mismas reglas que el registro (antes aquí se podía poner un
        // nombre con números, un teléfono con letras o un documento con
        // decimales, que además rompía la columna numérica).
        $request->merge(ReglasUsuario::limpiar($request->only([
            'nombre_u', 'apellido_u', 'telefono_u', 'documento_u', 'correo_u', 'ciudad_u',
        ])));

        $validated = $request->validate([
            'nombre_u' => ['sometimes', 'required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_u' => ['sometimes', 'required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'correo_u' => ['sometimes', 'required', 'email', 'max:80', ReglasUsuario::correoUnico($user->id_u)],
            'telefono_u' => 'sometimes|required|digits_between:7,15|unique:usuario,telefono_u,' . $user->id_u . ',id_u',
            'ciudad_u' => 'sometimes|nullable|string|max:80',
            'tipo_documento_u' => 'sometimes|required|string|max:30',
            'documento_u' => 'sometimes|required|integer|digits_between:5,12|unique:usuario,documento_u,' . $user->id_u . ',id_u',
            'fecha_nacimiento_u' => 'sometimes|required|date|after:1900-01-01|before_or_equal:' . ReglasUsuario::fechaMaximaNacimiento(),
        ], ReglasUsuario::mensajes());

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $this->formatUser($user->fresh()->load('roles')),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'contrasena_actual' => 'required|string',
            // Misma regla que el registro y la recuperación de contraseña.
            'nueva_contrasena' => array_merge(ReglasUsuario::contrasena(), ['different:contrasena_actual']),
        ], array_merge(ReglasUsuario::mensajes('nueva_contrasena'), [
            'contrasena_actual.required' => 'Escribe tu contraseña actual.',
            'nueva_contrasena.different' => 'La nueva contraseña debe ser distinta a la actual.',
        ]));

        $user = $request->user();

        if (!Hash::check($request->contrasena_actual, $user->contrasena_u)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta'
            ], 400);
        }

        $user->update([
            'contrasena_u' => Hash::make($request->nueva_contrasena)
        ]);

        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return response()->json([
            'message' => 'Contraseña actualizada correctamente'
        ], 200);
    }

    /**
     * Subir/actualizar la foto de perfil del usuario logueado.
     */
    public function updateFoto(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:3072',
        ], [
            'foto.uploaded' => 'El servidor no pudo recibir la foto. Intenta con otra imagen o reinicia "php artisan serve".',
            'foto.max' => 'La foto no debe superar 3 MB.',
            'foto.image' => 'El archivo debe ser una imagen.',
        ]);

        $user = $request->user();
        $archivo = $request->file('foto');

        // Borra la foto anterior si existía, para no dejar basura en el disco
        if ($user->foto_u) {
            Storage::disk('public')->delete($user->foto_u);
        }

        // extension() se deduce del contenido real del archivo, no del nombre que
        // manda el navegador (evita guardar algo como "foto.php").
        $nombreArchivo = uniqid() . '.' . $archivo->extension();
        $contenido = $archivo->get(); // lee el contenido en memoria, sin depender de getRealPath()

        Storage::disk('public')->put('fotos_perfil/' . $nombreArchivo, $contenido);
        $path = 'fotos_perfil/' . $nombreArchivo;

        $user->update(['foto_u' => $path]);

        return response()->json([
            'message' => 'Foto de perfil actualizada correctamente',
            'foto_url' => asset('storage/' . $path),
        ]);
    }


    public function desactivarCuenta(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->estado_u = 'inactivo';
        $user->save();

        // Revoca todos los tokens activos, para cerrar sesión en todos lados
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Cuenta desactivada correctamente'
        ]);
    }

}