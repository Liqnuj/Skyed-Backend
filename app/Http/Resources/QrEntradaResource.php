<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrEntradaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_qr,
            'codigo' => $this->codigo_qr,
            'imagen' => $this->qr_imagen_qr,
            'fecha_generacion' => $this->fecha_generacion_qr,
            'fecha_uso' => $this->fecha_uso_qr,
            'estado' => $this->estado_qr,
        ];
    }
}
