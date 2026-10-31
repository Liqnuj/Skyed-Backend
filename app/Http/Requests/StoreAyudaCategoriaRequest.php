<?php

namespace App\Http\Requests;

use App\Models\AyudaArticulo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAyudaCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permiso por módulo en el controlador
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:ayuda_categoria,slug_ayc'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'icono' => ['nullable', 'string', 'max:50', 'regex:/^ti-[a-z0-9-]+$/'],
            'modulo' => ['required', Rule::in(AyudaArticulo::MODULOS)],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'activa' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'slug' => 'dirección',
            'descripcion' => 'descripción',
            'icono' => 'ícono',
            'modulo' => 'módulo',
            'orden' => 'orden',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'in' => 'El valor de :attribute no es válido.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'slug.regex' => 'La dirección solo puede tener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una categoría con esa dirección.',
            'icono.regex' => 'El ícono debe ser un nombre de Tabler Icons, por ejemplo ti-rocket.',
        ];
    }
}