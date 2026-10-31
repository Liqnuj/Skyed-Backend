<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateAyudaCategoriaRequest extends StoreAyudaCategoriaRequest
{
    public function rules(): array
    {
        $reglas = parent::rules();

        foreach (['nombre', 'modulo'] as $campo) {
            array_unshift($reglas[$campo], 'sometimes');
        }

        $reglas['slug'] = [
            'sometimes', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('ayuda_categoria', 'slug_ayc')->ignore($this->route('id'), 'id_ayc'),
        ];

        return $reglas;
    }
}