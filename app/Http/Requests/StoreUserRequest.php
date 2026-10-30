<?php

namespace App\Http\Requests;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Misma limpieza que el registro: correo en minúsculas, teléfono sin separadores, etc. */
    protected function prepareForValidation(): void
    {
        $this->merge(ReglasUsuario::limpiar($this->all()));
    }

    public function rules(): array
    {
        return [
            'tipo_documento_u' => 'required|string|max:30',
            'documento_u' => 'required|integer|digits_between:5,12|unique:usuario,documento_u',
            'nombre_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_u' => ['required', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'rh_u' => ['required', 'string', Rule::in(ReglasUsuario::RH)],
            'telefono_u' => 'required|digits_between:7,15|unique:usuario,telefono_u',
            'correo_u' => ['required', 'email', 'max:80', ReglasUsuario::correoUnico()],
            // Misma regla que el registro (8+ con mayúscula, minúscula y número).
            'contrasena_u' => ['required', 'string', 'min:8', 'max:72', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'fecha_nacimiento_u' => 'required|date|after:1900-01-01|before_or_equal:' . ReglasUsuario::fechaMaximaNacimiento(),

            // Rol y contexto
            'id_rol' => 'required|exists:rol,id_rol',
            'contexto' => 'required|string|in:deportivo,social,general',
        ];
    }
}
