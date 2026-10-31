<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AyudaCategoriaAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_ayc,
            'slug' => $this->slug_ayc,
            'nombre' => $this->nombre_ayc,
            'descripcion' => $this->descripcion_ayc,
            'icono' => $this->icono_ayc,
            'modulo' => $this->modulo_ayc,
            'orden' => $this->orden_ayc,
            'activa' => (bool) $this->activa_ayc,
            // Cuenta TODOS los artículos (borradores y desactivados incluidos).
            'total_articulos' => $this->whenCounted('articulos'),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}