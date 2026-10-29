<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Versión de detalle: agrega el contenido y las palabras clave. */
class AyudaArticuloDetalleResource extends AyudaArticuloResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'contenido' => $this->contenido_aya,
            'palabras_clave' => $this->palabrasClave(),
            'creado_en' => $this->created_at?->toIso8601String(),
        ]);
    }
}