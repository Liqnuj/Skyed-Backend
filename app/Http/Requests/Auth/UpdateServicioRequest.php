<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_s' => 'sometimes|string|max:100',
            'descripcion_s' => 'sometimes|nullable|string|max:255',
        ];
    }
}
