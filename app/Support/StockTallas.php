<?php

namespace App\Support;

use App\Models\Kit;
use App\Models\KitTalla;

/**
 * Inventario del kit por talla.
 *
 * Se descuenta al inscribirse y se devuelve si la inscripción se cancela.
 * Debe llamarse dentro de una DB::transaction para que el bloqueo de filas
 * (lockForUpdate) evite que dos personas se lleven la última unidad.
 *
 * Los kits antiguos que aún no tienen tallas con cantidad no se controlan:
 * siguen funcionando como antes.
 */
class StockTallas
{
    public static function normalizar(?string $talla): ?string
    {
        $talla = strtoupper(trim((string) $talla));

        return $talla === '' ? null : $talla;
    }

    /**
     * Descuenta una unidad por cada talla pedida (titular e invitado pueden pedir la misma).
     *
     * @param  array<int, string|null>  $tallas
     * @return string|null  Mensaje de error, o null si todo salió bien.
     */
    public static function reservar(Kit $kit, array $tallas): ?string
    {
        if (!KitTalla::where('id_k', $kit->id_k)->exists()) {
            return null; // kit sin inventario por talla (versión anterior)
        }

        $pedidas = array_map(fn ($t) => self::normalizar($t), $tallas);

        if (in_array(null, $pedidas, true)) {
            return 'Selecciona la talla del kit';
        }

        $conteo = array_count_values($pedidas);

        // Primero se verifica todo; solo si alcanza para todas se descuenta.
        $filas = [];
        foreach ($conteo as $talla => $cantidad) {
            $fila = KitTalla::where('id_k', $kit->id_k)
                ->where('talla_kt', (string) $talla)
                ->lockForUpdate()
                ->first();

            if (!$fila) {
                return "La talla {$talla} no existe en este kit";
            }

            if ($fila->cantidad_kt < $cantidad) {
                return $fila->cantidad_kt <= 0
                    ? "La talla {$talla} está agotada"
                    : "Solo quedan {$fila->cantidad_kt} unidades de la talla {$talla}";
            }

            $filas[(string) $talla] = $fila;
        }

        foreach ($conteo as $talla => $cantidad) {
            $filas[(string) $talla]->decrement('cantidad_kt', $cantidad);
        }

        $kit->decrement('stock_k', min(array_sum($conteo), (int) $kit->stock_k));

        return null;
    }

    /**
     * Devuelve las unidades al cancelar una inscripción.
     *
     * @param  array<int, string|null>  $tallas
     */
    public static function devolver(Kit $kit, array $tallas): void
    {
        foreach (array_count_values(array_filter(array_map(fn ($t) => self::normalizar($t), $tallas))) as $talla => $cantidad) {
            $fila = KitTalla::where('id_k', $kit->id_k)
                ->where('talla_kt', (string) $talla)
                ->lockForUpdate()
                ->first();

            if ($fila) {
                $fila->increment('cantidad_kt', $cantidad);
                $kit->increment('stock_k', $cantidad);
            }
        }
    }
}