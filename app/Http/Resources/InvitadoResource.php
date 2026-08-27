<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_inv,
            'tipo_documento' => $this->tipo_documento,
            'documento' => $this->documento_inv,
            'nombre' => $this->nombre_inv,
            'apellido' => $this->apellido_inv,
            'rh' => $this->rh_inv,
            'telefono' => $this->telefono_inv,
            'fecha_nacimiento' => $this->fecha_nacimiento_inv,
            'correo' => $this->correo_inv,
        ];
    }
}
