<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventoRealizadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_er' => 'required|string|max:150',
            'descripcion_er' => 'nullable|string|max:255',
            'fecha_er' => 'nullable|date',
            'imagen_er' => 'nullable|string|max:255',
            'id_tipo_eves' => [
                'required',
                Rule::exists('tipo_evento', 'id_tipo_eves')
                    ->where('modulo_tipo_eves', 'social'),
            ],
            'id_a' => 'required|exists:ambiente,id_a',
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_eves.exists' => 'Ese tipo de evento no existe o pertenece a Deportivo, no a Social.',
        ];
    }
}
