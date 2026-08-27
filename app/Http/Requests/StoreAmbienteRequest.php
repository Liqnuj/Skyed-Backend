<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAmbienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_a' => 'required|string|max:100',
            'descripcion_a' => 'nullable|string',
            'capacidad_a' => 'required|integer|min:1',
            'precio_referencia_a' => 'nullable|numeric|min:0',
            'imagen_principal_a' => 'nullable|string|max:255',
        ];
    }
}
