<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo_u' => 'required|email',
            'token' => 'required|string',
            'contrasena_u' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'correo_u.required' => 'El correo es obligatorio.',
            'token.required' => 'El código de seguridad es obligatorio.',
            'contrasena_u.required' => 'Debes ingresar una nueva contraseña.',
            'contrasena_u.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'contrasena_u.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}