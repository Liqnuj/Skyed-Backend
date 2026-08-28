<?php

namespace App\Policies;

use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InscripcionPolicy
{
    /**
     * Reutilizada por view/update/delete: el dueño de la inscripción
     * o un adminDeportivo pueden operar sobre ella. Es exactamente
     * la misma condición que estaba repetida en 3 métodos distintos
     * de InscripcionController.
     */
    protected function esDuenoOAdmin(User $user, Inscripcion $inscripcion, string $mensaje): Response
    {
        return $user->id_u === $inscripcion->id_u || $user->hasRole('adminDeportivo')
            ? Response::allow()
            : Response::deny($mensaje);
    }

    public function view(User $user, Inscripcion $inscripcion): Response
    {
        return $this->esDuenoOAdmin(
            $user,
            $inscripcion,
            'No tienes permisos para ver esta inscripción'
        );
    }

    public function update(User $user, Inscripcion $inscripcion): Response
    {
        return $this->esDuenoOAdmin(
            $user,
            $inscripcion,
            'No tienes permiso para editar esta inscripción'
        );
    }

    public function delete(User $user, Inscripcion $inscripcion): Response
    {
        return $this->esDuenoOAdmin(
            $user,
            $inscripcion,
            'No tienes permiso para cancelar esta inscripción'
        );
    }
}
