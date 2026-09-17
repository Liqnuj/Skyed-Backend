<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HistorialParticipacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_hp' => $this->id_hp,
            'fecha_hp' => $this->fecha_hp,
            'estado_hp' => $this->estado_hp,
            'observaciones_hp' => $this->observaciones_hp,
            'evento' => new EventoDeportivoResource($this->whenLoaded('evento')),
        ];
    }
}