<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventoRealizadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_er' => 'sometimes|string|max:150',
            'descripcion_er' => 'sometimes|nullable|string|max:255',
            'fecha_er' => 'sometimes|nullable|date',
            'imagen_er' => 'sometimes|nullable|string|max:255',
            'id_tipo_eves' => [
                'sometimes',
                Rule::exists('tipo_evento', 'id_tipo_eves')
                    ->where('modulo_tipo_eves', 'social'),
            ],
            'id_a' => 'sometimes|exists:ambiente,id_a',
            'estado_er' => 'sometimes|in:activo,inactivo',
        ];
    }

    public function messages(): array
    {
        return [
            'id_tipo_eves.exists' => 'Ese tipo de evento no existe o pertenece a Deportivo, no a Social.',
        ];
    }
}
