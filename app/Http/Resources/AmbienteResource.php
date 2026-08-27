<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmbienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_a,
            'nombre' => $this->nombre_a,
            'descripcion' => $this->descripcion_a,
            'capacidad' => $this->capacidad_a,
            'precio_referencia' => $this->precio_referencia_a,
            'imagen_principal' => $this->imagen_principal_a,
            'servicios' => ServicioResource::collection($this->whenLoaded('servicios')),
        ];
    }
}
