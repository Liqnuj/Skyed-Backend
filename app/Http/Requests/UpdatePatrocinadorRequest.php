<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatrocinadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_p' => 'sometimes|string|max:100',
            'logo_p' => 'sometimes|nullable|string|max:255',
            'telefono_p' => 'sometimes|nullable|string|max:15',
            'correo_p' => 'sometimes|nullable|email|max:80',
            'pagina_web_p' => 'sometimes|nullable|string|max:120',
            'aporte_p' => 'sometimes|nullable|string|max:100',
            'estado_p' => 'sometimes|in:activo,inactivo,inhabilitado',
        ];
    }
}
