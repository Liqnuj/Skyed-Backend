<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatrocinadorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_p,
            'nombre' => $this->nombre_p,
            'logo' => $this->logo_p,
            'telefono' => $this->telefono_p,
            'correo' => $this->correo_p,
            'pagina_web' => $this->pagina_web_p,
            'aporte' => $this->aporte_p,
            'estado' => $this->estado_p,
            // El pivot 'detalle' solo existe cuando el patrocinador
            // viene cargado a través de la relación belongsToMany de
            // un evento (->withPivot('detalle')).
            'detalle' => $this->whenPivotLoaded('evento_patrocinador', function () {
                return $this->pivot->detalle;
            }),
        ];
    }
}
