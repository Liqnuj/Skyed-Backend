<?php

namespace App\Http\Requests;

use App\Models\AyudaArticulo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAyudaArticuloRequest extends FormRequest
{
    public function authorize(): bool
    {
        // El permiso por módulo se valida en el controlador (depende del contenido).
        return true;
    }

    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer', 'exists:ayuda_categoria,id_ayc'],
            'titulo' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:ayuda_articulo,slug_aya'],
            'resumen' => ['nullable', 'string', 'max:300'],
            'contenido' => ['required', 'string', 'max:20000'],
            'palabras_clave' => ['nullable', 'string', 'max:255'],
            'modulo' => ['required', Rule::in(AyudaArticulo::MODULOS)],
            'audiencia' => ['required', Rule::in(AyudaArticulo::AUDIENCIAS)],
            'estado' => ['required', Rule::in(AyudaArticulo::ESTADOS)],
            'destacado' => ['sometimes', 'boolean'],
            'faq' => ['sometimes', 'boolean'],
            'orden' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'contexto' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9._-]+$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'categoria_id' => 'categoría',
            'titulo' => 'título',
            'slug' => 'dirección',
            'resumen' => 'resumen',
            'contenido' => 'contenido',
            'palabras_clave' => 'palabras clave',
            'modulo' => 'módulo',
            'audiencia' => 'audiencia',
            'estado' => 'estado',
            'orden' => 'orden',
            'contexto' => 'contexto',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'max' => 'El campo :attribute no puede superar :max caracteres.',
            'in' => 'El valor de :attribute no es válido.',
            'exists' => 'La :attribute elegida no existe.',
            'boolean' => 'El campo :attribute debe ser verdadero o falso.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'slug.regex' => 'La dirección solo puede tener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe un artículo con esa dirección.',
            'contexto.regex' => 'El contexto solo puede tener minúsculas, números, puntos, guiones y guion bajo.',
        ];
    }
}