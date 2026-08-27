<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoriaCompetenciaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_cc,
            'nombre' => $this->nombre_cc,
            'edad_minima' => $this->edad_minima_cc,
            'edad_maxima' => $this->edad_maxima_cc,
            'genero' => $this->genero_cc,
            'distancia' => $this->distancia_cc,
            'descripcion' => $this->descripcion_cc,
            'evento' => new EventoDeportivoResource($this->whenLoaded('evento')),
        ];
    }
}
