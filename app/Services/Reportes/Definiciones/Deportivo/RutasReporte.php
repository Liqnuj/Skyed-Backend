<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\EventoDeportivo;
use App\Models\RutaEvento;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Rutas de eventos" del panel Deportivo. */
class RutasReporte extends Reporte
{
    public function clave(): string { return 'rutas'; }
    public function titulo(): string { return 'Reporte de rutas de eventos'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('evento', 'Evento', Columna::TEXTO, 22),
            new Columna('nombre', 'Ruta', Columna::TEXTO, 20),
            new Columna('distancia', 'Distancia', Columna::TEXTO, 12),
            new Columna('desnivel', 'Desnivel', Columna::TEXTO, 12),
            new Columna('precio', 'Precio', Columna::MONEDA, 12),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 34, enPdf: false),
            new Columna('gpx', 'Archivo GPX', Columna::TEXTO, 30, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'evento' => [
                'etiqueta' => 'Evento',
                'regla' => ['nullable', 'integer', 'min:1'],
                'texto' => fn ($v) => EventoDeportivo::query()->whereKey($v)->value('nombre_e') ?? ('#' . $v),
            ],
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = RutaEvento::query()->with('evento:id_e,nombre_e')->orderBy('id_re');

        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_re'),
                $this->col($b, 'distancia_re'),
                $this->col($b, 'desnivel_re'),
                $this->col($b, 'estado_re'),
            ]),
        ]);

        if (!empty($filtros['evento'])) {
            $q->where('id_e', (int) $filtros['evento']);
        }

        return $q;
    }

    protected function fila(Model $r, array $filtros): ?array
    {
        return [
            'id' => $r->id_re,
            'evento' => $r->evento?->nombre_e ?? '—',
            'nombre' => $r->nombre_re,
            'distancia' => $r->distancia_re,
            'desnivel' => $r->desnivel_re,
            'precio' => $r->precio_re,
            'estado' => Formato::humano($r->estado_re ?? ''),
            'descripcion' => $r->descripcion_re,
            'gpx' => $r->archivo_gpx_re,
        ];
    }
}
