<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\SeguimientoReserva;

/**
 * Único punto por donde se escribe el historial de una reserva
 * (tabla seguimiento_reserva), para que siempre quede quién, cuándo,
 * qué y, si aplica, el estado anterior y el nuevo.
 */
class ReservaTrazabilidad
{
    /**
     * @param string      $tipo            ver SeguimientoReserva::TIPOS
     * @param int|null    $adminId         null = el cliente o el sistema
     * @param bool        $visibleUsuario  false = nota interna (el cliente nunca la recibe)
     */
    public static function registrar(
        Reserva $reserva,
        string $tipo,
        string $comentario,
        ?int $adminId = null,
        ?string $anterior = null,
        ?string $nuevo = null,
        bool $visibleUsuario = true
    ): SeguimientoReserva {
        return $reserva->seguimientos()->create([
            'fecha_actualizacion' => now(),
            'comentario' => $comentario,
            'id_admin' => $adminId,
            'tipo_evento' => $tipo,
            'estado_anterior' => $anterior,
            'estado_nuevo' => $nuevo,
            'visible_usuario' => $visibleUsuario,
        ]);
    }
}