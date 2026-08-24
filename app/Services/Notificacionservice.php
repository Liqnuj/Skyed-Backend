<?php

namespace App\Services;

use App\Models\Notificacion;

class NotificacionService
{
    /**
     * Crea una notificación para un usuario.
     *
     * @param int $userId id_u del usuario que la va a recibir
     * @param string $titulo
     * @param string $mensaje
     * @param string $tipo Categoría libre: 'pqr', 'pago', 'inscripcion',
     *                     'reserva', 'evento', 'general'...
     */
    public static function crear(
        int $userId,
        string $titulo,
        string $mensaje,
        string $tipo = 'general'
    ): Notificacion {
        return Notificacion::create([
            'user_id' => $userId,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'tipo' => $tipo,
            'leida' => false,
        ]);
    }
}