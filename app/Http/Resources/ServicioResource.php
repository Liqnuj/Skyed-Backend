<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServicioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_s,
            'nombre' => $this->nombre_s,
            'descripcion' => $this->descripcion_s,
            'ambientes' => AmbienteResource::collection($this->whenLoaded('ambientes')),
        ];
    }
}
