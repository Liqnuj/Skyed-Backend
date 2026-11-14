<?php

namespace App\Http\Requests\Auth;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->correo_u)) {
            $this->merge(['correo_u' => ReglasUsuario::normalizarCorreo($this->correo_u)]);
        }
    }

    public function rules(): array
    {
        return [
            // Por correo, o por tipo y número de documento (sin exponer el correo).
            'correo_u' => 'required_without:documento_u|nullable|email',
            'tipo_documento_u' => 'required_with:documento_u|nullable|string|max:30',
            'documento_u' => 'required_without:correo_u|nullable|digits_between:5,12',
            'token' => 'required|string',
            // Misma regla que el registro y que la pantalla de recuperación.
            'contrasena_u' => ReglasUsuario::contrasena(),
        ];
    }

    public function messages(): array
    {
        return array_merge(ReglasUsuario::mensajes(), [
            'correo_u.required_without' => 'El correo es obligatorio.',
            'token.required' => 'El código de seguridad es obligatorio.',
            'contrasena_u.required' => 'Debes ingresar una nueva contraseña.',
            'contrasena_u.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        ]);
    }
}
