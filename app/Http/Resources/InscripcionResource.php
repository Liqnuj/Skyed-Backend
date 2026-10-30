<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InscripcionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $esDueno = $user && $user->id_u === $this->id_u;
        $esAdminDeportivo = $user && $user->hasRole('adminDeportivo');

        return [
            'id' => $this->id_i,
            'cupo' => $this->cupo_i,
            'estado' => $this->estado_i,
            'fecha' => $this->fecha_i,
            'precio_pagado' => $this->precio_pagado_i,
            'desglose_precios' => [
                'inscripcion' => $this->precio_inscripcion_i,
                'kit' => $this->precio_kit_i,
                'invitado' => $this->precio_invitado_i,
                'kit_invitado' => $this->precio_kit_invitado_i,
            ],
            'talla_camiseta' => $this->talla_camiseta_i,
            'id_categoria' => $this->id_cc,
            'desea_kit' => (bool) $this->desea_kit_i,
            'numero_dorsal' => $this->numero_dorsal_i,

            // Datos de contacto de emergencia: son sensibles, así que
            // solo se muestran completos al dueño de la inscripción o
            // a un adminDeportivo. El controller ya bloquea el acceso
            // a este endpoint para terceros, pero dejarlo explícito
            // aquí protege también si el Resource se reutiliza en
            // otro lugar (ej. un listado más abierto en el futuro).
            'contacto_emergencia' => $this->when(
                $esDueno || $esAdminDeportivo,
                [
                    'nombre' => $this->contacto_emergencia_nombre,
                    'telefono' => $this->contacto_emergencia_telefono,
                    'parentesco' => $this->contacto_emergencia_parentesco,
                ]
            ),

            // Condiciones médicas y teléfono de contacto: datos sensibles, solo
            // para el dueño de la inscripción o un adminDeportivo.
            'condiciones_medicas' => $this->when($esDueno || $esAdminDeportivo, $this->condiciones_medicas_i),
            'telefono_contacto' => $this->when($esDueno || $esAdminDeportivo, $this->telefono_contacto_i),

            'usuario' => new UserResource($this->whenLoaded('usuario')),
            'evento' => new EventoDeportivoResource($this->whenLoaded('evento')),
            'pago' => new PagoResource($this->whenLoaded('pago')),
            'qr' => new QrEntradaResource($this->whenLoaded('qr')),
            'invitado' => new InvitadoResource($this->whenLoaded('invitado')),
        ];
    }
}