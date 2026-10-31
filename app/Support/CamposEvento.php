<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Campos que dependen del tipo de evento. Es la única fuente de verdad:
 * el backend valida con estas definiciones y el frontend dibuja el
 * formulario con lo que la API le devuelve (meta.campos_tipo).
 *
 * El tipo se reconoce por su nombre (tipo_evento.nombre_tipo_eves), así que
 * un tipo nuevo que cree el administrador usa los campos "genérico".
 * Los valores se guardan en reserva_gestion.datos_tipo (JSON).
 */
class CamposEvento
{
    public const GRUPOS = [
        'boda' => 'Boda',
        'quinceanera' => 'Quinceañera',
        'cumpleanos' => 'Cumpleaños',
        'baby_shower' => 'Baby shower',
        'corporativo' => 'Corporativo',
        'generico' => 'Otro evento',
    ];

    public static function grupoPara(?string $nombreTipo): string
    {
        $n = Str::of((string) $nombreTipo)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();

        return match (true) {
            str_contains($n, 'boda'), str_contains($n, 'matrimonio') => 'boda',
            str_contains($n, 'quince') => 'quinceanera',
            str_contains($n, 'cumple') => 'cumpleanos',
            str_contains($n, 'baby'), str_contains($n, 'bebe') => 'baby_shower',
            str_contains($n, 'corporativ'), str_contains($n, 'empresa'), str_contains($n, 'institucion') => 'corporativo',
            default => 'generico',
        };
    }

    /** Lista de campos del grupo (clave, etiqueta, tipo, opciones, requerido_para_confirmar). */
    public static function definicion(string $grupo): array
    {
        $catering = self::campo('catering', 'Catering', 'booleano');
        $musica = self::campo('musica', 'Música', 'texto');

        return match ($grupo) {
            'boda' => [
                self::campo('nombres_novios', 'Nombres de los novios', 'texto', requerido: true),
                self::campo('tipo_ceremonia', 'Tipo de ceremonia', 'seleccion', ['civil', 'religiosa', 'simbolica', 'sin_ceremonia']),
                $catering,
                $musica,
                self::campo('protocolo', 'Protocolo y momentos especiales', 'textarea'),
            ],
            'quinceanera' => [
                self::campo('nombre_homenajeada', 'Nombre de la quinceañera', 'texto', requerido: true),
                self::campo('vals', 'Vals / coreografía', 'booleano'),
                self::campo('damas_chambelanes', 'Damas y chambelanes', 'numero'),
                $catering,
                $musica,
            ],
            'cumpleanos' => [
                self::campo('nombre_cumpleanero', 'Nombre del cumpleañero', 'texto', requerido: true),
                self::campo('edad_cumplida', 'Edad que cumple', 'numero'),
                self::campo('torta', 'Torta incluida', 'booleano'),
                self::campo('animacion', 'Animación o recreación', 'texto'),
                $catering,
                $musica,
            ],
            'baby_shower' => [
                self::campo('nombre_madre', 'Nombre de la futura mamá', 'texto', requerido: true),
                self::campo('tipo_celebracion', 'Tipo de celebración', 'seleccion', ['revelacion', 'nina', 'nino', 'sorpresa']),
                self::campo('juegos', 'Juegos y actividades', 'booleano'),
                $catering,
            ],
            'corporativo' => [
                self::campo('entidad', 'Entidad u organización', 'texto', requerido: true),
                self::campo('contacto_empresa', 'Persona de contacto en la entidad', 'texto'),
                self::campo('tipo_actividad', 'Tipo de actividad', 'seleccion', ['capacitacion', 'reunion', 'fiesta_fin_de_ano', 'lanzamiento', 'otro']),
                self::campo('equipos_audiovisuales', 'Equipos audiovisuales', 'booleano'),
                self::campo('protocolo', 'Protocolo', 'textarea'),
                $catering,
            ],
            default => [
                self::campo('tipo_celebracion', 'Tipo de celebración', 'texto'),
                $catering,
                $musica,
            ],
        };
    }

    /** Reglas de validación de Laravel para datos_tipo.* de este grupo. */
    public static function reglas(string $grupo): array
    {
        $reglas = [];

        foreach (self::definicion($grupo) as $c) {
            $reglas['datos_tipo.' . $c['clave']] = match ($c['tipo']) {
                'textarea' => ['nullable', 'string', 'max:1000'],
                'numero' => ['nullable', 'integer', 'min:0', 'max:100000'],
                'booleano' => ['nullable', 'boolean'],
                'seleccion' => ['nullable', 'in:' . implode(',', $c['opciones'])],
                default => ['nullable', 'string', 'max:150'],
            };
        }

        return $reglas;
    }

    /** Etiquetas de los campos obligatorios para confirmar que están vacíos. */
    public static function faltantes(string $grupo, array $datos): array
    {
        $faltan = [];

        foreach (self::definicion($grupo) as $c) {
            if (! $c['requerido_para_confirmar']) {
                continue;
            }

            $valor = $datos[$c['clave']] ?? null;

            if ($valor === null || (is_string($valor) && trim($valor) === '')) {
                $faltan[] = $c['etiqueta'];
            }
        }

        return $faltan;
    }

    private static function campo(
        string $clave,
        string $etiqueta,
        string $tipo = 'texto',
        array $opciones = [],
        bool $requerido = false
    ): array {
        return [
            'clave' => $clave,
            'etiqueta' => $etiqueta,
            'tipo' => $tipo,
            'opciones' => $opciones,
            'requerido_para_confirmar' => $requerido,
        ];
    }
}