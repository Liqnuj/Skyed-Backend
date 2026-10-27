<?php

namespace App\Http\Requests\Auth;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Compatibilidad: el formulario nuevo manda `identificacion` (correo o
        // documento) y el anterior mandaba `correo_u`. Se aceptan los dos, así
        // un cliente desactualizado no recibe un 422 con credenciales correctas.
        if (!$this->filled('identificacion') && $this->filled('correo_u')) {
            $this->merge(['identificacion' => $this->input('correo_u')]);
        }

        // Si es un correo válido, lo normalizamos
        if (is_string($this->identificacion) && filter_var($this->identificacion, FILTER_VALIDATE_EMAIL)) {
            $correo = ReglasUsuario::normalizarCorreo($this->identificacion);
            // correo_u también, por si el controlador todavía lo lee con ese nombre.
            $this->merge(['identificacion' => $correo, 'correo_u' => $correo]);
        }
    }

    public function rules(): array
    {
        return [
            'identificacion' => 'required|string',
            'contrasena_u' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'identificacion.required' => 'Debes ingresar tu correo electrónico o número de documento.',
            'contrasena_u.required' => 'La contraseña es obligatoria.',
        ];
    }
}