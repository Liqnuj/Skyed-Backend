<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use Closure;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Base de todos los reportes de SKYED.
 *
 * Cada reporte corresponde a UNA tabla del panel (nunca se mezclan datos de
 * secciones distintas) y define:
 *  - qué columnas tiene,
 *  - qué filtros acepta (se validan en el backend),
 *  - la consulta que trae TODOS los registros que cumplen esos filtros
 *    (no solo los 10/15 que muestra la página de la tabla).
 */
abstract class Reporte
{
    /** Clave en la URL: /reportes/{modulo}/{clave}/{pdf|xlsx} */
    abstract public function clave(): string;

    abstract public function titulo(): string;

    /** Nombre visible del módulo: "Skyed Social", "Skyed Deportivo"… */
    abstract public function modulo(): string;

    /** Paleta del PDF: social | deportivo | general */
    abstract public function tema(): string;

    /** @return Columna[] */
    abstract public function columnas(): array;

    /** Consulta base con eager loading, orden estable y filtros aplicados. */
    abstract protected function consulta(array $filtros): Builder;

    /**
     * Convierte un registro en [clave_columna => valor]. Devolver null
     * descarta el registro (se usa solo para filtros calculados en PHP).
     */
    abstract protected function fila(Model $registro, array $filtros): ?array;

    /**
     * Filtros propios del reporte (además de la búsqueda `q`):
     * clave => ['etiqueta' => ..., 'regla' => [...], 'valores' => [...]?,
     *           'tipo' => 'fecha'|'bool'?, 'texto' => Closure?]
     */
    protected function filtros(): array
    {
        return [];
    }

    /** ¿La tabla del panel tiene cuadro de búsqueda? */
    protected function permiteBusqueda(): bool
    {
        return true;
    }

    /** Datos agregados para el encabezado (etiqueta => texto). */
    public function resumen(array $filtros): array
    {
        return [];
    }

    // ------------------------------------------------------------------
    //  API pública usada por el controlador y los exportadores
    // ------------------------------------------------------------------

    public function archivoBase(): string
    {
        return Str::slug('skyed ' . $this->modulo() . ' ' . $this->clave());
    }

    /** Reglas de validación de los parámetros que recibe el endpoint. */
    public function reglas(): array
    {
        $reglas = [];

        if ($this->permiteBusqueda()) {
            $reglas['q'] = ['nullable', 'string', 'max:100'];
        }

        foreach ($this->filtros() as $clave => $definicion) {
            $reglas[$clave] = $definicion['regla'];
        }

        return $reglas;
    }

    /** Filtros realmente usados, listos para imprimir (etiqueta => texto). */
    public function filtrosAplicados(array $filtros): array
    {
        $aplicados = [];

        if (!empty($filtros['q'])) {
            $aplicados['Búsqueda'] = '"' . $filtros['q'] . '"';
        }

        foreach ($this->filtros() as $clave => $definicion) {
            $valor = $filtros[$clave] ?? null;

            if ($valor === null || $valor === '') {
                continue;
            }

            if (isset($definicion['texto'])) {
                $texto = ($definicion['texto'])($valor);
            } elseif (($definicion['tipo'] ?? null) === 'fecha') {
                $texto = Formato::fecha($valor);
            } elseif (($definicion['tipo'] ?? null) === 'bool') {
                $texto = 'Sí';
            } else {
                $texto = $definicion['valores'][$valor] ?? (string) $valor;
            }

            $aplicados[$definicion['etiqueta']] = $texto;
        }

        return $aplicados;
    }

    /** Recorre TODOS los registros del reporte en bloques (poca memoria). */
    public function filas(array $filtros): Generator
    {
        foreach ($this->consulta($filtros)->lazy(500) as $registro) {
            $fila = $this->fila($registro, $filtros);

            if ($fila !== null) {
                yield $fila;
            }
        }
    }

    // ------------------------------------------------------------------
    //  Ayudas para las consultas (portables entre PostgreSQL/MySQL/SQLite)
    // ------------------------------------------------------------------

    /** Columna calificada y entrecomillada según el motor: "tabla"."columna". */
    protected function col(Builder $q, string $columna): string
    {
        return $q->getQuery()->getGrammar()->wrap($q->getModel()->qualifyColumn($columna));
    }

    /** CAST(columna AS TEXT) para poder buscar números y fechas como texto. */
    protected function textoDe(Builder $q, string $columna): string
    {
        $c = $this->col($q, $columna);

        return match ($q->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "CAST($c AS CHAR)",
            'sqlsrv' => "CAST($c AS NVARCHAR(100))",
            default => "CAST($c AS TEXT)",
        };
    }

    /** Une expresiones SQL con un separador (equivale a `${a} ${b}` del frontend). */
    protected function unir(Builder $q, array $partes, string $separador = ' '): string
    {
        $sep = "'" . $separador . "'";

        return match ($q->getConnection()->getDriverName()) {
            'mysql', 'mariadb', 'pgsql' => 'CONCAT_WS(' . $sep . ', ' . implode(', ', $partes) . ')',
            default => implode(" || $sep || ", array_map(fn ($p) => "COALESCE($p, '')", $partes)),
        };
    }

    /**
     * Búsqueda "contiene" sin distinguir mayúsculas, igual que el cuadro de
     * búsqueda del panel (que busca dentro del texto concatenado de la fila).
     *
     * @param Closure $expresiones fn(Builder $b): string[]  expresiones SQL sobre el modelo
     * @param array   $relaciones  ['relacion.anidada' => fn(Builder $b): string[]]
     */
    protected function buscar(Builder $q, ?string $texto, Closure $expresiones, array $relaciones = []): void
    {
        $texto = trim((string) $texto);

        if ($texto === '') {
            return;
        }

        // Se escapan %, _ y ! para que el texto del usuario se busque literal.
        $patron = '%' . strtr(mb_strtolower($texto), ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';

        $q->where(function (Builder $w) use ($expresiones, $relaciones, $patron) {
            foreach ($expresiones($w) as $expresion) {
                $w->orWhereRaw("LOWER($expresion) LIKE ? ESCAPE '!'", [$patron]);
            }

            foreach ($relaciones as $relacion => $expresionesRelacion) {
                $w->orWhereHas($relacion, function (Builder $sub) use ($expresionesRelacion, $patron) {
                    $sub->where(function (Builder $x) use ($expresionesRelacion, $patron) {
                        foreach ($expresionesRelacion($x) as $expresion) {
                            $x->orWhereRaw("LOWER($expresion) LIKE ? ESCAPE '!'", [$patron]);
                        }
                    });
                });
            }
        });
    }

    /**
     * Rango de fechas inclusivo. Para columnas con hora (timestamps en UTC)
     * el día se interpreta en hora de Colombia, que es lo que ve el usuario.
     */
    protected function entreFechas(Builder $q, string $columna, array $filtros, bool $conHora = false): void
    {
        $desde = $filtros['fecha_desde'] ?? null;
        $hasta = $filtros['fecha_hasta'] ?? null;

        if ($conHora) {
            if ($desde) {
                $q->where($columna, '>=', CarbonImmutable::parse($desde, Formato::ZONA)->startOfDay()->utc());
            }
            if ($hasta) {
                $q->where($columna, '<=', CarbonImmutable::parse($hasta, Formato::ZONA)->endOfDay()->utc());
            }

            return;
        }

        if ($desde) {
            $q->whereDate($columna, '>=', $desde);
        }
        if ($hasta) {
            $q->whereDate($columna, '<=', $hasta);
        }
    }

    /** Filtros de fecha reutilizables (inicial y final). */
    protected function filtrosFecha(string $etiquetaBase = 'Fecha'): array
    {
        return [
            'fecha_desde' => [
                'etiqueta' => $etiquetaBase . ' inicial',
                'regla' => ['nullable', 'date_format:Y-m-d'],
                'tipo' => 'fecha',
            ],
            'fecha_hasta' => [
                'etiqueta' => $etiquetaBase . ' final',
                'regla' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
                'tipo' => 'fecha',
            ],
        ];
    }

    /** "Nombre Apellido" de un usuario (o "—"). */
    protected function persona(?Model $usuario): string
    {
        if (!$usuario) {
            return '—';
        }

        $nombre = trim(($usuario->nombre_u ?? '') . ' ' . ($usuario->apellido_u ?? ''));

        return $nombre !== '' ? $nombre : '—';
    }
}