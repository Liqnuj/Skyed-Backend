<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class EventoDeportivoResource extends JsonResource
{
    /**
     * FIX: Storage::disk('public')->url() arma la URL con APP_URL, que en
     * el .env es "http://localhost" (sin el puerto 8000 de artisan serve),
     * así que las fotos de los eventos no cargaban. asset() usa el host
     * real de la petición, igual que las fotos de perfil y de Social.
     */
    private function urlImagen(): ?string
    {
        if (!$this->imagen_e) {
            return null;
        }

        if (Str::startsWith($this->imagen_e, ['http://', 'https://'])) {
            return $this->imagen_e;
        }

        return asset('storage/' . ltrim($this->imagen_e, '/'));
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_e,
            'nombre' => $this->nombre_e,
            'categoria' => $this->categoria_e,
            'precio' => (float) $this->precio_e,
            'distancia' => $this->distancia_e,
            'desnivel' => $this->desnivel_e,
            'fecha' => $this->fecha_e,
            'hora' => $this->hora_e,
            'ubicacion' => $this->ubicacion_e,
            'descripcion' => $this->descripcion_e,
            'requisitos' => $this->requisitos_e,

            // Se expone la ruta cruda (compatibilidad con lo que ya
            // consume el frontend) y también la URL completa lista
            // para usar en un <img src>.
            'imagen' => $this->imagen_e,
            'imagen_url' => $this->urlImagen(),

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
