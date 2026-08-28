<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_k,
            'nombre' => $this->nombre_k,
            'stock' => $this->stock_k,
            'fecha_entrega' => $this->fecha_entrega_k,
            'lugar_entrega' => $this->lugar_entrega_k,
            'contenido' => $this->contenido_k,
            'talla_camiseta' => $this->talla_camiseta_k,
            'numero_dorsal' => $this->numero_dorsal_k,
        ];
    }
}
