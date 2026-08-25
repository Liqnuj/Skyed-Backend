<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventoDeportivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_e' => 'sometimes|string|max:120',
            'categoria_e' => 'sometimes|in:atletismo,senderismo,ciclismo',
            'fecha_e' => 'sometimes|date',
            'hora_e' => 'sometimes',
            'ubicacion_e' => 'sometimes|string|max:120',
            'descripcion_e' => 'sometimes|string|max:255',
            'requisitos_e' => 'sometimes|string|max:255',
            'imagen_e' => 'sometimes|string|max:120',
            'cupos_disponibles_e' => 'sometimes|integer|min:0',
            'estado_e' => 'sometimes|in:activo,inactivo,inhabilitado',
            'id_k' => 'sometimes|nullable|exists:kit,id_k',
        ];
    }
}
