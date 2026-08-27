<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Proyección segura del usuario. NO se incluyen aquí datos
     * sensibles como documento_u, fecha_nacimiento_u o rh_u salvo
     * que el propio contexto (perfil propio / admin) lo requiera:
     * este Resource es el que se embebe dentro de otros recursos
     * (evento.creador, inscripcion.usuario, etc.), donde solo hace
     * falta identificar a la persona, no exponer todo su perfil.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id_u,
            'nombre' => $this->nombre_u,
            'apellido' => $this->apellido_u,
            'correo' => $this->correo_u,
            'telefono' => $this->telefono_u,
            'estado' => $this->estado_u,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('nombre_rol');
            }),
        ];
    }
}
