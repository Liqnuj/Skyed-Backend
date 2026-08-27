<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EventoDeportivoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_e,
            'nombre' => $this->nombre_e,
            'categoria' => $this->categoria_e,
            'fecha' => $this->fecha_e,
            'hora' => $this->hora_e,
            'ubicacion' => $this->ubicacion_e,
            'descripcion' => $this->descripcion_e,
            'requisitos' => $this->requisitos_e,

            // Se expone la ruta cruda (compatibilidad con lo que ya
            // consume el frontend) y también la URL completa lista
            // para usar en un <img src>.
            'imagen' => $this->imagen_e,
            'imagen_url' => $this->imagen_e
                ? Storage::disk('public')->url($this->imagen_e)
                : null,

            'cupos_disponibles' => $this->cupos_disponibles_e,
            'estado' => $this->estado_e,
            'creado_en' => $this->creado_e,

            'kit' => new KitResource($this->whenLoaded('kit')),
            'creador' => new UserResource($this->whenLoaded('creador')),

            'categorias' => CategoriaCompetenciaResource::collection(
                $this->whenLoaded('categorias')
            ),

            'patrocinadores' => PatrocinadorResource::collection(
                $this->whenLoaded('patrocinadores')
            ),
        ];
    }
}
