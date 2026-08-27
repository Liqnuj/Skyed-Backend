<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatrocinadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_p' => 'required|string|max:100',
            'logo_p' => 'nullable|string|max:255',
            'telefono_p' => 'nullable|string|max:15',
            'correo_p' => 'nullable|email|max:80',
            'pagina_web_p' => 'nullable|string|max:120',
            'aporte_p' => 'nullable|string|max:100',
            'estado_p' => 'nullable|in:activo,inactivo,inhabilitado',
        ];
    }
}
