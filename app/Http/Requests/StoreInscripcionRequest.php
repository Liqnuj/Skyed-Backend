<?php

namespace App\Http\Requests;

use App\Support\ReglasUsuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** "300 123-4567" o "(300) 1234567" -> "3001234567", igual que en el registro. */
    protected function prepareForValidation(): void
    {
        $limpiar = fn ($v) => is_string($v) ? preg_replace('/[\s\-()]/', '', $v) : $v;

        $datos = ['contacto_emergencia_telefono' => $limpiar($this->input('contacto_emergencia_telefono'))];

        $invitado = $this->input('invitado');
        if (is_array($invitado) && isset($invitado['telefono_inv'])) {
            $invitado['telefono_inv'] = $limpiar($invitado['telefono_inv']);
            $datos['invitado'] = $invitado;
        }

        $this->merge($datos);
    }

    public function rules(): array
    {
        return [
            // cupo_i y precio_pagado_i se aceptan por compatibilidad con el
            // formulario, pero el controlador los ignora: el cupo es siempre 1
            // y el precio sale del evento (no de lo que mande el navegador).
            'cupo_i' => 'nullable|integer|min:1|max:1',
            'precio_pagado_i' => 'nullable|numeric|min:0',

            'contacto_emergencia_nombre' => 'required|string|max:100',
            'contacto_emergencia_telefono' => 'required|digits_between:7,15',
            'contacto_emergencia_parentesco' => ['required', 'string', 'max:50', "regex:/^[\\pL\\s'_\\-\\/]+$/u"],

            'metodo_pago_p' => 'nullable|in:transferencia,nequi,efectivo',
            'referencia_p' => 'nullable|string|max:100',
            // La imagen se sube aparte (POST /pagos/{id}/comprobante); este
            // campo ya no se usa para guardar nada.
            'comprobante_p' => 'nullable|string|max:255',

            // Invitado opcional (acompañante que asiste con el titular).
            // Envía "invitado" solo si aplica; si no, la inscripción
            // queda solo a nombre del usuario autenticado.
            'invitado' => 'nullable|array',
            'invitado.tipo_documento' => 'required_with:invitado|in:CC,TI,CE,PA',
            'invitado.documento_inv' => 'required_with:invitado|integer|digits_between:5,12|unique:invitados,documento_inv',
            'invitado.nombre_inv' => ['required_with:invitado', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'invitado.apellido_inv' => ['required_with:invitado', 'string', 'max:50', 'regex:' . ReglasUsuario::NOMBRE],
            'invitado.rh_inv' => ['required_with:invitado', Rule::in(ReglasUsuario::RH)],
            'invitado.telefono_inv' => 'required_with:invitado|digits_between:7,15|unique:invitados,telefono_inv',
            'invitado.fecha_nacimiento_inv' => 'required_with:invitado|date|after:1900-01-01|before_or_equal:today',
            'invitado.correo_inv' => 'nullable|email|max:80|unique:invitados,correo_inv',
        ];
    }
}
