<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'correo_u' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'correo_u.required' => 'Necesitamos tu correo para enviarte el código de recuperación.',
            'correo_u.email' => 'El formato del correo no es válido.',
        ];
    }
}