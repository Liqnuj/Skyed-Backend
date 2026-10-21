<?php

namespace App\Http\Requests\Auth;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Correo en minúsculas, nombres sin espacios de más y números sin
     * puntos/espacios, antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(ReglasUsuario::limpiar($this->only([
            'nombre_u', 'apellido_u', 'telefono_u', 'documento_u', 'correo_u',
        ])));
    }

    public function rules(): array
    {
        return [
            'tipo_documento_u' => 'required|string|max:30',
            // La columna es bigint (antes integer: una cédula como 3456789012
            // no cabía y el registro respondía 500 en PostgreSQL).
            'documento_u' => 'required|integer|digits_between:5,12|unique:usuario,documento_u',
            'nombre_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'telefono_u' => 'required|digits_between:7,15|unique:usuario,telefono_u',
            'correo_u' => ['required', 'email', 'max:80', ReglasUsuario::correoUnico()],
            'contrasena_u' => ReglasUsuario::contrasena(),
            'fecha_nacimiento_u' => 'required|date|after:1900-01-01|before_or_equal:' . ReglasUsuario::fechaMaximaNacimiento(),
            'contexto' => 'sometimes|string|in:deportivo,social',
        ];
    }

    public function messages(): array
    {
        return ReglasUsuario::mensajes();
    }
}
