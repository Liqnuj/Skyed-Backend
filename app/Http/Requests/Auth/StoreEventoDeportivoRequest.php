<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEventoDeportivoRequest extends FormRequest
{
    /**
     * La autorización de rol ya la hace el middleware
     * 'role.context:adminDeportivo' en la ruta, así que aquí
     * simplemente dejamos pasar a cualquiera que haya llegado
     * hasta este punto.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_e' => 'required|string|max:120',
            'categoria_e' => 'required|in:atletismo,senderismo,ciclismo',
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
            'categoria_e.in' => 'La categoría debe ser atletismo, senderismo o ciclismo.',
            'fecha_e.date' => 'La fecha no tiene un formato válido.',
            'cupos_disponibles_e.min' => 'Los cupos no pueden ser negativos.',
        ];
    }
}
