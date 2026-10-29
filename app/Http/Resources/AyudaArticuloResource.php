<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Versión de lista: sin el contenido completo. */
class AyudaArticuloResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_aya,
            'slug' => $this->slug_aya,
            'titulo' => $this->titulo_aya,
            'resumen' => $this->resumen_aya,
            'modulo' => $this->modulo_aya,
            'audiencia' => $this->audiencia_aya,
            'destacado' => (bool) $this->destacado_aya,
            'faq' => (bool) $this->faq_aya,
            'contexto' => $this->contexto_aya,
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria->id_ayc,
                'slug' => $this->categoria->slug_ayc,
                'nombre' => $this->categoria->nombre_ayc,
            ]),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}