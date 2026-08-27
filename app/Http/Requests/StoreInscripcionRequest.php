<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cupo_i' => 'required|integer|min:1',
            'precio_pagado_i' => 'nullable|numeric|min:0',

            'contacto_emergencia_nombre' => 'required|string|max:100',
            'contacto_emergencia_telefono' => 'required|string|max:15',
            'contacto_emergencia_parentesco' => 'required|string|max:50',

            'metodo_pago_p' => 'nullable|string|max:50',
            'referencia_p' => 'nullable|string|max:100',
            'comprobante_p' => 'nullable|string|max:255',

            // Invitado opcional (acompañante que asiste con el titular).
            // Envía "invitado" solo si aplica; si no, la inscripción
            // queda solo a nombre del usuario autenticado.
            'invitado' => 'nullable|array',
            'invitado.tipo_documento' => 'required_with:invitado|string|max:30',
            'invitado.documento_inv' => 'required_with:invitado|integer|unique:invitados,documento_inv',
            'invitado.nombre_inv' => 'required_with:invitado|string|max:50',
            'invitado.apellido_inv' => 'required_with:invitado|string|max:50',
            'invitado.rh_inv' => 'required_with:invitado|string|max:5',
            'invitado.telefono_inv' => 'required_with:invitado|string|max:50|unique:invitados,telefono_inv',
            'invitado.fecha_nacimiento_inv' => 'required_with:invitado|date',
            'invitado.correo_inv' => 'nullable|email|max:80|unique:invitados,correo_inv',
        ];
    }
}
