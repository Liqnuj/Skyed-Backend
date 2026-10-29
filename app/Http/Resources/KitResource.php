<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KitResource extends JsonResource
{
    private function tallasOrdenadas(): array
    {
        $orden = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        $posicion = fn (string $t) => ($i = array_search($t, $orden, true)) === false ? 100 : $i;

        return $this->tallas
            ->sort(fn ($a, $b) => [$posicion($a->talla_kt), $a->talla_kt] <=> [$posicion($b->talla_kt), $b->talla_kt])
            ->map(fn ($t) => ['talla' => $t->talla_kt, 'cantidad' => (int) $t->cantidad_kt])
            ->values()
            ->all();
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_k,
            'nombre' => $this->nombre_k,
            'precio' => (float) $this->precio_k,
            'imagen' => $this->imagen_k,
            'imagen_url' => $this->imagen_url,
            'stock' => $this->stock_k,
            // Tallas con su cantidad disponible (en orden XS, S, M, L, XL...).
            'tallas' => $this->tallasOrdenadas(),
            'fecha_entrega' => $this->fecha_entrega_k,
            'lugar_entrega' => $this->lugar_entrega_k,
            'contenido' => $this->contenido_k,
            'talla_camiseta' => $this->talla_camiseta_k,
            'numero_dorsal' => $this->numero_dorsal_k,
        ];
    }
}