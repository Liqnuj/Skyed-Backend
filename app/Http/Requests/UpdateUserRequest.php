<?php

namespace App\Http\Requests;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        // El id del usuario que se está editando viene en la URL
        // (/users/{id}), lo usamos para que las reglas "unique"
        // no rechacen al propio usuario por sus propios datos.
        $id = $this->route('id');

        return [
            'tipo_documento_u' => 'sometimes|string|max:30',
            'documento_u' => 'sometimes|integer|digits_between:5,12|unique:usuario,documento_u,' . $id . ',id_u',
            'nombre_u' => ['sometimes', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'apellido_u' => ['sometimes', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            // 'N/A' es el valor con el que se guarda al registrarse sin RH.
            'rh_u' => ['sometimes', 'string', Rule::in(array_merge(ReglasUsuario::RH, ['N/A']))],
            'telefono_u' => 'sometimes|digits_between:7,15|unique:usuario,telefono_u,' . $id . ',id_u',
            'correo_u' => ['sometimes', 'email', 'max:80', ReglasUsuario::correoUnico((int) $id)],
            'contrasena_u' => ['sometimes', 'string', 'min:8', 'max:72', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'fecha_nacimiento_u' => 'sometimes|date|after:1900-01-01|before_or_equal:' . ReglasUsuario::fechaMaximaNacimiento(),
            'estado_u' => 'sometimes|in:activo,inactivo',
        ];
    }
}
