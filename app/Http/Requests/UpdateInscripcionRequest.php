<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInscripcionRequest extends FormRequest
{
    /**
     * Aquí solo dejamos pasar la validación de campos.
     * El chequeo de "es el dueño o es adminDeportivo" se hace
     * dentro del controlador, porque necesita comparar con la
     * inscripción ya cargada de la base de datos.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $tel = $this->input('contacto_emergencia_telefono');
        if (is_string($tel)) {
            $this->merge(['contacto_emergencia_telefono' => preg_replace('/[\s\-()]/', '', $tel)]);
        }
    }

    public function rules(): array
    {
        return [
            'contacto_emergencia_nombre' => 'sometimes|string|max:100',
            'contacto_emergencia_telefono' => 'sometimes|digits_between:7,15',
            'contacto_emergencia_parentesco' => ['sometimes', 'string', 'max:50', "regex:/^[\\pL\\s'_\\-\\/]+$/u"],
        ];
    }
}
