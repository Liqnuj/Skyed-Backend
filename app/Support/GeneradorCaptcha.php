<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Verificación "No soy un robot" propia de SKYED: una imagen con caracteres
 * deformados que la persona debe escribir.
 *
 * Los caracteres se dibujan como trazos (no como texto), así no aparecen
 * escritos en el código de la página. El servidor guarda solo un resumen
 * cifrado de la respuesta durante 5 minutos y cada imagen sirve una sola vez.
 */
class GeneradorCaptcha
{
    public const LARGO = 5;

    private const MINUTOS = 5;

    /**
     * Cada carácter, como trazos en una cuadrícula de 10 x 14. Se dejaron
     * fuera los que se confunden entre sí (0/O, 1/I/L, 2/Z, 5/S, 8/B).
     */
    private const TRAZOS = [
        'A' => [[[0, 14], [5, 0], [10, 14]], [[2, 9], [8, 9]]],
        'C' => [[[10, 2], [7, 0], [3, 0], [0, 4], [0, 10], [3, 14], [7, 14], [10, 12]]],
        'D' => [[[0, 0], [0, 14], [5, 14], [9, 11], [10, 7], [9, 3], [5, 0], [0, 0]]],
        'E' => [[[10, 0], [0, 0], [0, 14], [10, 14]], [[0, 7], [7, 7]]],
        'F' => [[[10, 0], [0, 0], [0, 14]], [[0, 7], [7, 7]]],
        'H' => [[[0, 0], [0, 14]], [[10, 0], [10, 14]], [[0, 7], [10, 7]]],
        'J' => [[[10, 0], [10, 11], [7, 14], [3, 14], [0, 11]]],
        'K' => [[[0, 0], [0, 14]], [[10, 0], [0, 8]], [[3, 6], [10, 14]]],
        'M' => [[[0, 14], [0, 0], [5, 8], [10, 0], [10, 14]]],
        'N' => [[[0, 14], [0, 0], [10, 14], [10, 0]]],
        'P' => [[[0, 14], [0, 0], [7, 0], [10, 2], [10, 5], [7, 7], [0, 7]]],
        'R' => [[[0, 14], [0, 0], [7, 0], [10, 2], [10, 5], [7, 7], [0, 7]], [[5, 7], [10, 14]]],
        'T' => [[[0, 0], [10, 0]], [[5, 0], [5, 14]]],
        'U' => [[[0, 0], [0, 11], [3, 14], [7, 14], [10, 11], [10, 0]]],
        'V' => [[[0, 0], [5, 14], [10, 0]]],
        'W' => [[[0, 0], [2, 14], [5, 6], [8, 14], [10, 0]]],
        'X' => [[[0, 0], [10, 14]], [[10, 0], [0, 14]]],
        'Y' => [[[0, 0], [5, 7], [10, 0]], [[5, 7], [5, 14]]],
        '3' => [[[0, 1], [3, 0], [7, 0], [10, 3], [7, 7], [3, 7]], [[7, 7], [10, 10], [7, 14], [3, 14], [0, 13]]],
        '4' => [[[8, 14], [8, 0], [0, 10], [10, 10]]],
        '6' => [[[9, 1], [6, 0], [3, 0], [0, 4], [0, 11], [3, 14], [7, 14], [10, 11], [10, 9], [7, 7], [3, 7], [0, 9]]],
        '7' => [[[0, 0], [10, 0], [4, 14]]],
        '9' => [[[10, 5], [7, 7], [3, 7], [0, 5], [0, 3], [3, 0], [7, 0], [10, 3], [10, 10], [7, 14], [3, 14], [1, 13]]],
    ];

    private const COLORES = ['#1e3a8a', '#6b21a8', '#9d174d', '#065f46', '#7c2d12', '#334155'];

    /** @return array{id: string, imagen: string} */
    public static function crear(): array
    {
        $letras = array_keys(self::TRAZOS);
        $codigo = '';
        for ($i = 0; $i < self::LARGO; $i++) {
            $codigo .= $letras[random_int(0, count($letras) - 1)];
        }

        $id = (string) Str::uuid();
        Cache::put(self::clave($id), hash('sha256', $codigo), now()->addMinutes(self::MINUTOS));

        return [
            'id' => $id,
            'imagen' => 'data:image/svg+xml;base64,' . base64_encode(self::dibujar($codigo)),
        ];
    }

    /** ¿Es correcta? La imagen se descarta en el primer intento, acierte o no. */
    public static function verificar(?string $id, ?string $respuesta): bool
    {
        if (!$id || !$respuesta) {
            return false;
        }

        $guardado = Cache::pull(self::clave($id));
        $escrito = strtoupper(preg_replace('/\s+/', '', $respuesta) ?? '');

        return is_string($guardado) && hash_equals($guardado, hash('sha256', $escrito));
    }

    private static function clave(string $id): string
    {
        return 'captcha:' . $id;
    }

    private static function dibujar(string $codigo): string
    {
        $ancho = 220;
        $alto = 70;
        $partes = [];

        // Ruido de fondo: puntos y curvas que cruzan los caracteres.
        for ($i = 0; $i < 40; $i++) {
            $partes[] = sprintf('<circle cx="%d" cy="%d" r="%.1f" fill="#94a3b8" opacity=".45"/>', random_int(0, $ancho), random_int(0, $alto), random_int(5, 18) / 10);
        }
        for ($i = 0; $i < 3; $i++) {
            $partes[] = sprintf(
                '<path d="M%d %d C%d %d %d %d %d %d" fill="none" stroke="%s" stroke-width="%.1f" opacity=".55"/>',
                random_int(-10, 20), random_int(10, 60), random_int(40, 90), random_int(-10, 80),
                random_int(120, 170), random_int(-10, 80), random_int(200, 230), random_int(10, 60),
                self::COLORES[random_int(0, count(self::COLORES) - 1)], random_int(12, 22) / 10
            );
        }

        foreach (str_split($codigo) as $n => $caracter) {
            $escala = random_int(23, 28) / 10;
            $giro = deg2rad(random_int(-20, 20));
            $inclinacion = random_int(-25, 25) / 100;
            $x0 = 22 + $n * 38 + random_int(-4, 4);
            $y0 = 16 + random_int(-6, 6);
            $color = self::COLORES[random_int(0, count(self::COLORES) - 1)];

            foreach (self::TRAZOS[$caracter] as $trazo) {
                $puntos = [];
                foreach ($trazo as [$x, $y]) {
                    // Se centra en el carácter, se inclina, se gira y se mueve un poco cada punto.
                    $px = ($x - 5 + ($y - 7) * $inclinacion) * $escala;
                    $py = ($y - 7) * $escala;
                    $rx = $px * cos($giro) - $py * sin($giro);
                    $ry = $px * sin($giro) + $py * cos($giro);
                    $puntos[] = sprintf('%.1f %.1f', $x0 + 5 * $escala / 2 + $rx + random_int(-12, 12) / 10, $y0 + 7 * $escala / 2 + $ry + random_int(-12, 12) / 10);
                }
                $partes[] = sprintf('<path d="M%s" fill="none" stroke="%s" stroke-width="%.1f" stroke-linecap="round" stroke-linejoin="round"/>', implode(' L', $puntos), $color, random_int(28, 36) / 10);
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%2$d" viewBox="0 0 %1$d %2$d"><rect width="100%%" height="100%%" fill="#f8fafc"/>%3$s</svg>',
            $ancho, $alto, implode('', $partes)
        );
    }
}
