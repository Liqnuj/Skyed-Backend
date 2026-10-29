<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AyudaCategoriaResource extends JsonResource
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
            'total_articulos' => $this->whenCounted('articulos'),
        ];
    }
}