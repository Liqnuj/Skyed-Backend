<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\Estacion;
use App\Models\EventoDeportivo;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Estaciones de eventos" del panel Deportivo. */
class EstacionesReporte extends Reporte
{
    public function clave(): string { return 'estaciones'; }
    public function titulo(): string { return 'Reporte de estaciones de eventos'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('evento', 'Evento', Columna::TEXTO, 22),
            new Columna('nombre', 'Estación', Columna::TEXTO, 20),
            new Columna('tipo', 'Tipo', Columna::TEXTO, 16),
            new Columna('kilometro', 'Kilómetro', Columna::TEXTO, 10),
            new Columna('latitud', 'Latitud', Columna::TEXTO, 12),
            new Columna('longitud', 'Longitud', Columna::TEXTO, 12),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 34, enPdf: false),
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
        $q = Estacion::query()->with('evento:id_e,nombre_e')->orderBy('id_est');

        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_est'),
                $this->col($b, 'tipo_est'),
                $this->col($b, 'kilometro_est'),
                $this->col($b, 'estado_est'),
                $this->col($b, 'descripcion_pest'),
            ]),
        ]);

        if (!empty($filtros['evento'])) {
            $q->where('id_e', (int) $filtros['evento']);
        }

        return $q;
    }

    protected function fila(Model $e, array $filtros): ?array
    {
        return [
            'id' => $e->id_est,
            'evento' => $e->evento?->nombre_e ?? '—',
            'nombre' => $e->nombre_est,
            'tipo' => Formato::humano($e->tipo_est),
            'kilometro' => $e->kilometro_est,
            'latitud' => $e->latitud_est,
            'longitud' => $e->longitud_est,
            'estado' => Formato::humano($e->estado_est ?? ''),
            'descripcion' => $e->descripcion_pest,
        ];
    }
}
