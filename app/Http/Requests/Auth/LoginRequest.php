<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo_u' => 'required|email',
            'contrasena_u' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'correo_u.required' => 'El correo electrónico es obligatorio.',
            'correo_u.email' => 'Debes ingresar un correo electrónico válido.',
            'contrasena_u.required' => 'La contraseña es obligatoria.',
        ];
    }
}