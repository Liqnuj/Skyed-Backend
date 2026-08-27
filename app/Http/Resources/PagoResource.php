<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PagoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_pago,
            'metodo_pago' => $this->metodo_pago_p,
            'referencia' => $this->referencia_p,
            'comprobante' => $this->comprobante_p,
            'monto' => $this->monto_p,
            'fecha' => $this->fecha_p,
            'estado' => $this->estado_p,
        ];
    }
}
