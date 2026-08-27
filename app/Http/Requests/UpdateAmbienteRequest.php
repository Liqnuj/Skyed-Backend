<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_a' => 'sometimes|string|max:100',
            'descripcion_a' => 'sometimes|nullable|string',
            'capacidad_a' => 'sometimes|integer|min:1',
            'precio_referencia_a' => 'sometimes|nullable|numeric|min:0',
            'imagen_principal_a' => 'sometimes|nullable|string|max:255',
        ];
    }
}
