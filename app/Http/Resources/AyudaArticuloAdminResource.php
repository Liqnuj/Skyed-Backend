<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Artículo para el panel de administración: incluye estado, visitas y valoraciones. */
class AyudaArticuloAdminResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_aya,
            'slug' => $this->slug_aya,
            'titulo' => $this->titulo_aya,
            'resumen' => $this->resumen_aya,
            'contenido' => $this->contenido_aya,
            'palabras_clave' => $this->palabras_clave_aya,
            'modulo' => $this->modulo_aya,
            'audiencia' => $this->audiencia_aya,
            'estado' => $this->estado_aya,
            'destacado' => (bool) $this->destacado_aya,
            'faq' => (bool) $this->faq_aya,
            'orden' => $this->orden_aya,
            'contexto' => $this->contexto_aya,
            'visitas' => $this->visitas_aya,
            'autor_id' => $this->id_autor,
            'categoria' => $this->whenLoaded('categoria', fn () => [
                'id' => $this->categoria->id_ayc,
                'slug' => $this->categoria->slug_ayc,
                'nombre' => $this->categoria->nombre_ayc,
            ]),
            'utiles' => $this->whenCounted('utiles'),
            'no_utiles' => $this->whenCounted('no_utiles'),
            'creado_en' => $this->created_at?->toIso8601String(),
            'actualizado_en' => $this->updated_at?->toIso8601String(),
        ];
    }
}