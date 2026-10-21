<?php

namespace App\Support;

use App\Models\User;
use Closure;

/**
 * Reglas de validación de los datos de un usuario, compartidas por el
 * registro (RegisterRequest) y la edición del perfil (AuthController).
 * Son las mismas que aplica el formulario de registro del frontend, para
 * que la API no acepte por fuera lo que la página no deja escribir.
 */
class ReglasUsuario
{
    /** Letras (con tildes y ñ), espacios, apóstrofe y guion. */
    public const NOMBRE = "/^[\\pL\\s'\\-]+$/u";

    /** Edad mínima para registrarse (igual que RegisterPage.tsx). */
    public const EDAD_MINIMA = 10;

    /**
     * Correo en minúsculas y sin espacios. PostgreSQL compara textos
     * distinguiendo mayúsculas, así que sin esto "Ana@Gmail.com" no podía
     * iniciar sesión como "ana@gmail.com" y se podían crear dos cuentas.
     */
    public static function normalizarCorreo(?string $correo): ?string
    {
        return $correo === null ? null : mb_strtolower(trim($correo));
    }

    /** Busca un usuario por correo sin importar mayúsculas/minúsculas. */
    public static function buscarPorCorreo(?string $correo): ?User
    {
        if (!$correo) {
            return null;
        }

        return User::whereRaw('LOWER(correo_u) = ?', [self::normalizarCorreo($correo)])->first();
    }

    /** Regla "correo no registrado" que ignora mayúsculas. */
    public static function correoUnico(?int $exceptoId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($exceptoId) {
            $existe = User::whereRaw('LOWER(correo_u) = ?', [self::normalizarCorreo((string) $value)])
                ->when($exceptoId, fn ($q) => $q->where('id_u', '!=', $exceptoId))
                ->exists();

            if ($existe) {
                $fail('Este correo electrónico ya está en uso. Intenta iniciar sesión.');
            }
        };
    }

    /** Quita espacios de más en nombres y separadores en números. */
    public static function limpiar(array $datos): array
    {
        foreach (['nombre_u', 'apellido_u', 'ciudad_u'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = preg_replace('/\s+/u', ' ', trim($datos[$campo]));
            }
        }

        // Teléfono: "300 123-4567" o "(300) 1234567" → "3001234567".
        if (isset($datos['telefono_u']) && is_string($datos['telefono_u'])) {
            $datos['telefono_u'] = preg_replace('/[\s\-()]/', '', $datos['telefono_u']);
        }

        // Documento: "1.052.345.678" → "1052345678". Los puntos solo se
        // quitan si son separadores de miles; "1234567.5" se deja igual para
        // que la validación lo rechace (si no, se guardaría otro número).
        if (isset($datos['documento_u']) && is_string($datos['documento_u'])) {
            $documento = preg_replace('/\s/', '', $datos['documento_u']);
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $documento)) {
                $documento = str_replace('.', '', $documento);
            }
            $datos['documento_u'] = $documento;
        }

        if (isset($datos['correo_u']) && is_string($datos['correo_u'])) {
            $datos['correo_u'] = self::normalizarCorreo($datos['correo_u']);
        }

        return $datos;
    }

    /** Contraseña: 8+ caracteres con mayúscula, minúscula y número. */
    public static function contrasena(): array
    {
        return ['required', 'string', 'min:8', 'max:72', 'confirmed', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'];
    }

    public static function mensajes(string $campoContrasena = 'contrasena_u'): array
    {
        return [
            'tipo_documento_u.required' => 'Selecciona el tipo de documento.',
            'documento_u.required' => 'El número de documento es obligatorio.',
            'documento_u.integer' => 'El documento solo puede tener números.',
            'documento_u.digits_between' => 'El documento debe tener entre 5 y 12 dígitos.',
            'documento_u.unique' => 'Este documento ya se encuentra registrado en el sistema.',
            'nombre_u.required' => 'El nombre es obligatorio.',
            'nombre_u.regex' => 'El nombre solo puede tener letras y espacios.',
            'nombre_u.max' => 'El nombre no puede superar 50 caracteres.',
            'apellido_u.required' => 'El apellido es obligatorio.',
            'apellido_u.regex' => 'El apellido solo puede tener letras y espacios.',
            'apellido_u.max' => 'El apellido no puede superar 50 caracteres.',
            'telefono_u.required' => 'El teléfono es obligatorio.',
            'telefono_u.digits_between' => 'El teléfono debe tener solo números, entre 7 y 15 dígitos.',
            'telefono_u.unique' => 'El número de teléfono ya está vinculado a otra cuenta.',
            'correo_u.required' => 'El correo electrónico es obligatorio.',
            'correo_u.email' => 'Ingresa un correo electrónico válido.',
            'correo_u.max' => 'El correo no puede superar 80 caracteres.',
            'fecha_nacimiento_u.required' => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento_u.date' => 'La fecha de nacimiento no es válida.',
            'fecha_nacimiento_u.before_or_equal' => 'Debes tener al menos ' . self::EDAD_MINIMA . ' años para registrarte.',
            'fecha_nacimiento_u.after' => 'La fecha de nacimiento no es válida.',
            "{$campoContrasena}.required" => 'La contraseña es obligatoria.',
            "{$campoContrasena}.min" => 'La contraseña debe tener al menos 8 caracteres.',
            "{$campoContrasena}.max" => 'La contraseña no puede superar 72 caracteres.',
            "{$campoContrasena}.regex" => 'La contraseña debe tener al menos una mayúscula, una minúscula y un número.',
            "{$campoContrasena}.confirmed" => 'Las contraseñas no coinciden.',
        ];
    }

    /** Fecha límite para cumplir la edad mínima. */
    public static function fechaMaximaNacimiento(): string
    {
        return now()->subYears(self::EDAD_MINIMA)->toDateString();
    }
}
