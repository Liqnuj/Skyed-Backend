<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'id_tipo_eves' => 'sometimes|exists:tipo_evento,id_tipo_eves',
            'id_a' => 'sometimes|exists:ambiente,id_a',
            'estado_er' => 'sometimes|in:activo,inactivo',
        ];
    }
}
