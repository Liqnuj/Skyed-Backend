<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Formatos de presentación compartidos por el PDF y el Excel.
 * La base guarda las fechas con hora en UTC (app.timezone = UTC); aquí se
 * convierten a la hora de Colombia solo para mostrarlas.
 */
final class Formato
{
    public const ZONA = 'America/Bogota';

    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::ZONA);
    }

    /** Convierte cualquier fecha/cadena a CarbonImmutable (o null). */
    public static function aCarbon(mixed $valor, ?string $zonaOrigen = 'UTC'): ?CarbonImmutable
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if ($valor instanceof DateTimeInterface) {
            return CarbonImmutable::instance($valor);
        }

        return CarbonImmutable::parse((string) $valor, $zonaOrigen);
    }

    /** "dd/mm/aaaa" (día calendario, sin conversión de zona). */
    public static function fecha(mixed $valor): string
    {
        $fecha = self::aCarbon($valor);

        return $fecha ? $fecha->format('d/m/Y') : '—';
    }

    /** "dd/mm/aaaa HH:mm" en hora de Colombia. */
    public static function fechaHora(mixed $valor): string
    {
        $fecha = self::aCarbon($valor);

        return $fecha ? $fecha->setTimezone(self::ZONA)->format('d/m/Y H:i') : '—';
    }

    /** "$ 1.250.000" (sin decimales, igual que el panel). */
    public static function moneda(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return '$ ' . number_format((float) $valor, 0, ',', '.');
    }

    public static function entero(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return number_format((int) $valor, 0, ',', '.');
    }

    /** "en_revision" → "En revisión"-style genérico: "En revision". */
    public static function humano(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        $texto = str_replace('_', ' ', $valor);

        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }

    /** Texto ya listo para mostrar en el PDF según el tipo de columna. */
    public static function paraPdf(Columna $columna, mixed $valor): string
    {
        return match ($columna->tipo) {
            Columna::FECHA => self::fecha($valor),
            Columna::FECHA_HORA => self::fechaHora($valor),
            Columna::MONEDA => self::moneda($valor),
            Columna::ENTERO => self::entero($valor),
            default => ($valor === null || $valor === '') ? '—' : (string) $valor,
        };
    }

    /** Nombre de archivo seguro: "skyed-social-ambientes_20261026-2350". */
    public static function nombreArchivo(string $base): string
    {
        return $base . '_' . self::ahora()->format('Ymd-Hi');
    }
}