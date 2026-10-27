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
        // Compatibilidad: antes el login enviaba 'correo_u'. Si llega así,
        // se toma como la identificación (correo o documento).
        if (! $this->filled('identificacion') && $this->filled('correo_u')) {
            $this->merge(['identificacion' => $this->input('correo_u')]);
        }

        // Si es un correo válido, lo normalizamos
        if (is_string($this->identificacion) && filter_var($this->identificacion, FILTER_VALIDATE_EMAIL)) {
            $this->merge(['identificacion' => ReglasUsuario::normalizarCorreo($this->identificacion)]);
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