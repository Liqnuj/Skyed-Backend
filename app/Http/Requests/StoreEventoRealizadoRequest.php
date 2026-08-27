<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'id_tipo_eves' => 'required|exists:tipo_evento,id_tipo_eves',
            'id_a' => 'required|exists:ambiente,id_a',
        ];
    }
}
