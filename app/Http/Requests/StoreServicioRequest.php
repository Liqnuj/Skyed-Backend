<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServicioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre_s' => 'required|string|max:100',
            'descripcion_s' => 'nullable|string|max:255',
        ];
    }
}
