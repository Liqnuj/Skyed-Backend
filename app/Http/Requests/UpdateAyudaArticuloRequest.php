<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/** Igual que crear, pero parcial: solo se valida lo que llegue (PATCH). */
class UpdateAyudaArticuloRequest extends StoreAyudaArticuloRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();

        foreach (['categoria_id', 'titulo', 'contenido', 'modulo', 'audiencia', 'estado'] as $campo) {
            array_unshift($reglas[$campo], 'sometimes');
        }

        $reglas['slug'] = [
            'sometimes', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('ayuda_articulo', 'slug_aya')->ignore($this->route('id'), 'id_aya'),
        ];

        return $reglas;
    }
}