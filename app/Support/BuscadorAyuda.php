<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Búsqueda sencilla del Centro de Ayuda. Son pocas decenas de artículos,
 * así que se puntúa en PHP: igual en PostgreSQL y SQLite, sin acentos.
 */
class BuscadorAyuda
{
    private const PALABRAS_VACIAS = [
        'como', 'que', 'cual', 'cuales', 'donde', 'cuando', 'para', 'por', 'con',
        'sin', 'una', 'uno', 'unos', 'unas', 'los', 'las', 'del', 'mis', 'mas',
        'hago', 'puedo', 'quiero', 'necesito', 'esta', 'este', 'esto', 'ese',
        'esa', 'son', 'hay', 'ser', 'estoy', 'tengo', 'significa', 'sobre',
        'algo', 'todo', 'muy', 'pero', 'porque',
    ];

    /** Minúsculas, sin tildes ni signos, con espacios simples. */
    public static function normalizar(string $texto): string
    {
        $t = Str::ascii(mb_strtolower($texto));
        $t = preg_replace('/[^a-z0-9\s]+/', ' ', $t) ?? $t;

        return trim(preg_replace('/\s+/', ' ', $t) ?? $t);
    }

    /** @return string[] raíces (máx. 6 letras) de las palabras útiles de la consulta */
    public static function raices(string $consulta): array
    {
        $raices = [];

        foreach (explode(' ', self::normalizar($consulta)) as $palabra) {
            if (strlen($palabra) < 3 || in_array($palabra, self::PALABRAS_VACIAS, true)) {
                continue;
            }
            $raices[] = substr($palabra, 0, 6);
        }

        return array_values(array_unique($raices));
    }

    /**
     * @param  Collection  $articulos  ya filtrados por visibilidad y ordenados
     * @return Collection  los que coinciden, el más relevante primero
     */
    public static function buscar(Collection $articulos, string $consulta, int $limite = 20): Collection
    {
        $raices = self::raices($consulta);

        if ($raices === []) {
            return collect();
        }

        return $articulos
            ->map(function ($a) use ($raices) {
                $titulo = self::normalizar((string) $a->titulo_aya);
                $claves = self::normalizar((string) $a->palabras_clave_aya);
                $resumen = self::normalizar((string) $a->resumen_aya);
                $contenido = self::normalizar((string) $a->contenido_aya);

                $puntos = 0;
                $coinciden = 0;

                foreach ($raices as $raiz) {
                    $p = 0;
                    if (str_contains($titulo, $raiz)) { $p += 10; }
                    if (str_contains($claves, $raiz)) { $p += 6; }
                    if (str_contains($resumen, $raiz)) { $p += 3; }
                    if (str_contains($contenido, $raiz)) { $p += 1; }

                    if ($p > 0) {
                        $coinciden++;
                    }
                    $puntos += $p;
                }

                if ($coinciden === 0) {
                    return null;
                }

                // Bonus si coinciden TODAS las palabras de la búsqueda.
                if ($coinciden === count($raices)) {
                    $puntos += 5;
                }

                return ['articulo' => $a, 'puntos' => $puntos];
            })
            ->filter()
            ->sortByDesc('puntos')
            ->take($limite)
            ->pluck('articulo')
            ->values();
    }
}