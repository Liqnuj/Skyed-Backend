<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // El id del usuario que se está editando viene en la URL
        // (/users/{id}), lo usamos para que las reglas "unique"
        // no rechacen al propio usuario por sus propios datos.
        $id = $this->route('id');

        return [
            'tipo_documento_u' => 'sometimes|string',
            'documento_u' => 'sometimes|integer|unique:usuario,documento_u,' . $id . ',id_u',
            'nombre_u' => 'sometimes|string',
            'apellido_u' => 'sometimes|string',
            'rh_u' => 'sometimes|string',
            'telefono_u' => 'sometimes|string|unique:usuario,telefono_u,' . $id . ',id_u',
            'correo_u' => 'sometimes|email|unique:usuario,correo_u,' . $id . ',id_u',
            'contrasena_u' => 'sometimes|string|min:8',
            'fecha_nacimiento_u' => 'sometimes|date',
            'estado_u' => 'sometimes|in:activo,inactivo',
        ];
    }
}
