<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tipo_documento_u' => 'required|string',
            'documento_u' => 'required|numeric',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_documento_u.required' => 'El tipo de documento es obligatorio.',
            'documento_u.required' => 'El número de documento es obligatorio.',
            'documento_u.numeric' => 'El número de documento debe ser numérico.',
        ];
    }
}