<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
            'nombre_u' => 'required|string',
            'apellido_u' => 'required|string',
            'rh_u' => 'required|string',
            'telefono_u' => 'required|string',
            'correo_u' => 'required|email|unique:usuario,correo_u',
            'contrasena_u' => 'required|string|min:8',
            'fecha_nacimiento_u' => 'required|date',

            // Rol y contexto
            'id_rol' => 'required|exists:roles,id_rol',
            'contexto' => 'required|string',
        ];
    }
}
