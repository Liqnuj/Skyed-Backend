<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventoDeportivoRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
    return [
        'nombre_e' => 'required|string|max:120',
        'categoria_e' => 'required|in:ruta,mtb,gravel,pista,bmx',
        'precio_e' => 'required|numeric|min:0',
        'distancia_e' => 'nullable|string|max:30',
        'desnivel_e' => 'nullable|string|max:30',
        'fecha_e' => 'required|date',
        'hora_e' => 'required',
        'ubicacion_e' => 'required|string|max:120',
        'descripcion_e' => 'required|string|max:255',
        'requisitos_e' => 'required|string|max:255',
        'imagen_e' => 'required|string|max:120',
        'cupos_disponibles_e' => 'required|integer|min:0',
        'id_k' => 'nullable|exists:kit,id_k',
    ];
}
    /**
     * Mensajes personalizados (opcional, pero se ve más profesional
     * que el usuario reciba mensajes en español claros).
     */
    public function messages(): array
    {
        return [
            'nombre_e.required' => 'El nombre del evento es obligatorio.',
            'categoria_e.in' => 'La categoría debe ser ruta, mtb, gravel, pista o bmx.',
            'fecha_e.date' => 'La fecha no tiene un formato válido.',
            'cupos_disponibles_e.min' => 'Los cupos no pueden ser negativos.',
        ];
    }
}
