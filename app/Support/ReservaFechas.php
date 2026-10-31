<?php

namespace App\Support;

use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué fecha y hora se muestra de una reserva.
 *
 * La fecha que pidió el cliente (reserva.fecha_evento_rese) NUNCA se
 * sobrescribe. La fecha efectiva es, en este orden:
 *   1. fecha confirmada  (reserva_gestion.fecha_confirmada)
 *   2. fecha propuesta   (reserva_gestion.fecha_propuesta)
 *   3. fecha solicitada  (rango fecha_evento_rese → fecha_hasta_rese, aproximada)
 */
class ReservaFechas
{
    public const ZONA = 'America/Bogota';

    /** Hoy (Y-m-d) en hora de Colombia: la app corre en UTC y de noche ya sería "mañana". */
    public static function hoy(): string
    {
        return Carbon::now(self::ZONA)->toDateString();
    }

    /** @return array{inicio:string,fin:string,tipo:string,aproximada:bool} fechas en Y-m-d */
    public static function efectiva(Reserva $r): array
    {
        $g = $r->gestion;

        if ($g?->fecha_confirmada) {
            $d = $g->fecha_confirmada->toDateString();

            return ['inicio' => $d, 'fin' => $d, 'tipo' => 'confirmada', 'aproximada' => false];
        }

        if ($g?->fecha_propuesta) {
            $d = $g->fecha_propuesta->toDateString();

            return ['inicio' => $d, 'fin' => $d, 'tipo' => 'propuesta', 'aproximada' => false];
        }

        $inicio = $r->fecha_evento_rese?->toDateString() ?? '';
        $fin = $r->fecha_hasta_rese?->toDateString() ?? $inicio;

        if ($fin < $inicio) {
            $fin = $inicio;
        }

        return [
            'inicio' => $inicio,
            'fin' => $fin,
            'tipo' => 'solicitada',
            'aproximada' => $fin !== $inicio,
        ];
    }

    /** @return array{hora_inicio:?string,hora_fin:?string} en HH:MM */
    public static function horas(Reserva $r): array
    {
        $g = $r->gestion;

        $inicio = self::hhmm($g?->hora_inicio ?? $r->hora_aprox_rese);
        $fin = self::hhmm($g?->hora_fin);

        if ($inicio && ! $fin && $r->duracion_horas_rese) {
            [$h, $m] = array_map('intval', explode(':', $inicio));
            $minutos = min($h * 60 + $m + $r->duracion_horas_rese * 60, 23 * 60 + 59);
            $fin = sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
        }

        return ['hora_inicio' => $inicio, 'hora_fin' => $fin];
    }

    /**
     * Filtra por la fecha efectiva (ver arriba) con SQL que sirve en
     * PostgreSQL y en SQLite. Requiere haber unido reserva_gestion
     * (ver unirGestion()).
     */
    public static function aplicarRango(Builder $query, string $desde, string $hasta): Builder
    {
        $inicio = 'COALESCE(reserva_gestion.fecha_confirmada, reserva_gestion.fecha_propuesta, reserva.fecha_evento_rese)';
        $fin = 'COALESCE(reserva_gestion.fecha_confirmada, reserva_gestion.fecha_propuesta, reserva.fecha_hasta_rese, reserva.fecha_evento_rese)';

        return $query
            ->whereRaw("DATE($inicio) <= ?", [$hasta])
            ->whereRaw("DATE($fin) >= ?", [$desde]);
    }

    /** Une la ficha de gestión sin perder las columnas de reserva. */
    public static function unirGestion(Builder $query): Builder
    {
        return $query
            ->select('reserva.*')
            ->leftJoin('reserva_gestion', 'reserva_gestion.id_rese', '=', 'reserva.id_rese');
    }

    private static function hhmm(mixed $valor): ?string
    {
        return $valor ? substr((string) $valor, 0, 5) : null;
    }
}