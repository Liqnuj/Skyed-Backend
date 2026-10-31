<?php

namespace App\Services;

use App\Models\Reserva;
use App\Models\ReservaGestion;
use App\Support\CamposEvento;
use App\Support\ReservaFechas;

/**
 * Reglas del ciclo de vida de una reserva.
 *
 *  - estado_rese (pendiente, confirmada, completada, cancelada) es el
 *    estado "oficial" que ya usa el cliente. No se cambia su vocabulario.
 *  - etapa_gestion (reserva_gestion) es la etapa de planificación que solo
 *    ve el Administrador Social, mientras la reserva está abierta.
 */
class ReservaFlujo
{
    public const TRANSICIONES_ESTADO = [
        'pendiente' => ['confirmada', 'cancelada'],
        'confirmada' => ['completada', 'cancelada'],
        'completada' => [],
        'cancelada' => [],
    ];

    public const TRANSICIONES_ETAPA = [
        'nueva' => ['en_revision', 'contactando_usuario'],
        'en_revision' => ['contactando_usuario', 'pendiente_informacion', 'en_planificacion'],
        'contactando_usuario' => ['en_revision', 'pendiente_informacion', 'en_planificacion'],
        'pendiente_informacion' => ['en_revision', 'contactando_usuario', 'en_planificacion'],
        'en_planificacion' => ['contactando_usuario', 'pendiente_informacion', 'pendiente_confirmacion', 'reprogramada', 'en_ejecucion'],
        'pendiente_confirmacion' => ['en_planificacion', 'pendiente_informacion', 'reprogramada', 'en_ejecucion'],
        'reprogramada' => ['contactando_usuario', 'en_planificacion', 'pendiente_confirmacion', 'en_ejecucion'],
        'en_ejecucion' => ['en_planificacion'],
    ];

    /** Etapas anteriores a la confirmación: al confirmar pasan a "en planificación". */
    private const ETAPAS_PREVIAS = ['nueva', 'en_revision', 'contactando_usuario', 'pendiente_informacion', 'pendiente_confirmacion'];

    public static function estaAbierta(Reserva $reserva): bool
    {
        return in_array($reserva->estado_rese, ['pendiente', 'confirmada'], true);
    }

    public static function estadosPermitidos(Reserva $reserva): array
    {
        return self::TRANSICIONES_ESTADO[$reserva->estado_rese] ?? [];
    }

    public static function etapasPermitidas(Reserva $reserva): array
    {
        if (! self::estaAbierta($reserva)) {
            return [];
        }

        $actual = $reserva->asegurarGestion()->etapa_gestion;
        $permitidas = self::TRANSICIONES_ETAPA[$actual] ?? [];

        // "En ejecución" solo tiene sentido con la reserva ya confirmada.
        if ($reserva->estado_rese !== 'confirmada') {
            $permitidas = array_values(array_diff($permitidas, ['en_ejecucion']));
        }

        return $permitidas;
    }

    /** Cómo se dibuja en el calendario y en los listados. */
    public static function estadoVisual(Reserva $reserva): string
    {
        if ($reserva->estado_rese === 'cancelada') {
            return 'cancelada';
        }

        if ($reserva->estado_rese === 'completada') {
            return 'finalizada';
        }

        $etapa = $reserva->gestion?->etapa_gestion ?? 'nueva';

        if ($etapa === 'reprogramada') {
            return 'reprogramada';
        }

        if ($reserva->estado_rese === 'confirmada') {
            return 'confirmada';
        }

        return $etapa === 'nueva' ? 'nueva' : 'en_planificacion';
    }

    /** Lo que falta para poder confirmar. Vacío = se puede confirmar. */
    public static function faltantesParaConfirmar(Reserva $reserva): array
    {
        $g = $reserva->asegurarGestion();
        $faltan = [];

        if (! $g->fecha_confirmada) {
            $faltan[] = 'la fecha confirmada';
        } elseif ($g->fecha_confirmada->toDateString() < ReservaFechas::hoy()) {
            $faltan[] = 'una fecha confirmada que no haya pasado';
        }

        $grupo = CamposEvento::grupoPara($reserva->tipoEvento?->nombre_tipo_eves);

        foreach (CamposEvento::faltantes($grupo, $g->datos_tipo ?? []) as $etiqueta) {
            $faltan[] = mb_strtolower($etiqueta);
        }

        return $faltan;
    }

    /**
     * Cambia el estado oficial de la reserva y deja todo registrado.
     * Debe llamarse dentro de una transacción y con la reserva bloqueada.
     *
     * @param bool $estricto true = valida transiciones y requisitos (endpoint nuevo);
     *                       false = compatibilidad con PUT /reservas/{id} (no valida el flujo).
     * @return string|null mensaje de error, o null si se aplicó
     */
    public static function cambiarEstado(
        Reserva $reserva,
        string $nuevo,
        ?int $adminId,
        ?string $comentarioPublico = null,
        ?string $notaInterna = null,
        bool $estricto = true
    ): ?string {
        $actual = $reserva->estado_rese;

        if ($actual === $nuevo) {
            return $estricto ? 'La reserva ya está en ese estado.' : null;
        }

        if ($estricto) {
            if (! in_array($nuevo, self::TRANSICIONES_ESTADO[$actual] ?? [], true)) {
                return "No se puede pasar una reserva «{$actual}» a «{$nuevo}».";
            }

            if ($nuevo === 'confirmada') {
                $faltan = self::faltantesParaConfirmar($reserva);

                if ($faltan) {
                    return 'Para confirmar la reserva falta: ' . implode(', ', $faltan) . '.';
                }
            }

            if ($nuevo === 'completada') {
                $fecha = $reserva->asegurarGestion()->fecha_confirmada?->toDateString();

                if (! $fecha || $fecha > ReservaFechas::hoy()) {
                    return 'Solo se puede finalizar un evento cuya fecha confirmada ya llegó.';
                }
            }
        }

        $reserva->update(['estado_rese' => $nuevo]);

        // Mismo texto que ya interpreta el perfil del cliente.
        ReservaTrazabilidad::registrar(
            $reserva,
            'cambio_estado',
            'Estado cambiado a: ' . $nuevo,
            $adminId,
            $actual,
            $nuevo
        );

        $publico = trim((string) $comentarioPublico);
        if ($publico !== '') {
            ReservaTrazabilidad::registrar($reserva, 'actualizacion', $publico, $adminId);
        }

        $nota = trim((string) $notaInterna);
        if ($nota !== '') {
            ReservaTrazabilidad::registrar($reserva, 'nota_interna', $nota, $adminId, null, null, false);
        }

        $gestion = $reserva->asegurarGestion();

        if ($nuevo === 'confirmada' && in_array($gestion->etapa_gestion, self::ETAPAS_PREVIAS, true)) {
            ReservaTrazabilidad::registrar(
                $reserva,
                'cambio_etapa',
                'Etapa: ' . ReservaGestion::ETIQUETAS_ETAPA[$gestion->etapa_gestion] . ' → ' . ReservaGestion::ETIQUETAS_ETAPA['en_planificacion'],
                $adminId,
                $gestion->etapa_gestion,
                'en_planificacion',
                false
            );

            $gestion->update(['etapa_gestion' => 'en_planificacion']);
        }

        NotificacionService::crear(
            (int) $reserva->id_u,
            'Actualización de tu reserva',
            'El estado de tu reserva cambió a: ' . $nuevo,
            'reserva'
        );

        return null;
    }

    /**
     * Cambia la etapa de planificación. Debe llamarse dentro de una
     * transacción y con la reserva bloqueada.
     *
     * @return string|null mensaje de error, o null si se aplicó
     */
    public static function cambiarEtapa(Reserva $reserva, string $nueva, ?int $adminId, ?string $nota = null): ?string
    {
        if (! self::estaAbierta($reserva)) {
            return 'La reserva ya está cerrada; no se puede cambiar su etapa.';
        }

        $gestion = $reserva->asegurarGestion();
        $anterior = $gestion->etapa_gestion;

        if ($anterior === $nueva) {
            return 'La reserva ya está en esa etapa.';
        }

        if (! in_array($nueva, self::etapasPermitidas($reserva), true)) {
            $de = ReservaGestion::ETIQUETAS_ETAPA[$anterior] ?? $anterior;
            $a = ReservaGestion::ETIQUETAS_ETAPA[$nueva] ?? $nueva;

            return "No se puede pasar de «{$de}» a «{$a}»"
                . ($nueva === 'en_ejecucion' && $reserva->estado_rese !== 'confirmada' ? ' hasta confirmar la reserva.' : '.');
        }

        $gestion->update(['etapa_gestion' => $nueva]);

        $de = ReservaGestion::ETIQUETAS_ETAPA[$anterior] ?? $anterior;
        $a = ReservaGestion::ETIQUETAS_ETAPA[$nueva];

        // Solo "reprogramada" le importa al cliente; el resto es interno.
        $visible = $nueva === 'reprogramada';

        ReservaTrazabilidad::registrar(
            $reserva,
            'cambio_etapa',
            $visible
                ? 'Tu reserva fue reprogramada. El equipo te confirmará la nueva fecha.'
                : "Etapa: {$de} → {$a}",
            $adminId,
            $anterior,
            $nueva,
            $visible
        );

        $nota = trim((string) $nota);
        if ($nota !== '') {
            ReservaTrazabilidad::registrar($reserva, 'nota_interna', $nota, $adminId, null, null, false);
        }

        return null;
    }
}
