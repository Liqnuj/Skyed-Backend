<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'tipo_documento_u' => 'required|string',
            'documento_u' => 'required|integer|unique:usuario,documento_u',
            'nombre_u' => 'required|string|max:50',
            'apellido_u' => 'required|string|max:50',
            'telefono_u' => 'required|string|unique:usuario,telefono_u',
            'correo_u' => 'required|email|unique:usuario,correo_u',
            'contrasena_u' => 'required|string|min:8|confirmed',
            'fecha_nacimiento_u' => 'required|date',
            'contexto' => 'sometimes|string|in:deportivo,social',
        ];
    }

    public function messages(): array
    {
        return [
            'documento_u.unique' => 'Este documento ya se encuentra registrado en el sistema.',
            'correo_u.unique' => 'Este correo electrónico ya está en uso. Intenta iniciar sesión.',
            'telefono_u.unique' => 'El número de teléfono ya está vinculado a otra cuenta.',
            'contrasena_u.min' => 'La contraseña debe tener al menos 8 caracteres para ser segura.',
            'contrasena_u.confirmed' => 'Las contraseñas no coinciden.',
            'fecha_nacimiento_u.required' => 'La fecha de nacimiento es obligatoria.',
        ];
    }
}