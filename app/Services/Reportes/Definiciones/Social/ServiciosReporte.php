<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Models\Servicio;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Gestión de servicios" (Social › Servicios). */
class ServiciosReporte extends Reporte
{
    public function clave(): string { return 'servicios'; }
    public function titulo(): string { return 'Reporte de servicios'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 22),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 36),
            new Columna('total', 'N.º de ambientes', Columna::ENTERO, 11),
            new Columna('ambientes', 'Ambientes', Columna::TEXTO, 28),
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Servicio::query()->with('ambientes')->orderBy('id_s');

        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [$this->col($b, 'nombre_s'), $this->col($b, 'descripcion_s')]),
        ]);

        return $q;
    }

    protected function fila(Model $s, array $filtros): ?array
    {
        return [
            'id' => $s->id_s,
            'nombre' => $s->nombre_s,
            'descripcion' => $s->descripcion_s,
            'total' => $s->ambientes->count(),
            'ambientes' => $s->ambientes->pluck('nombre_a')->implode(', ') ?: '—',
        ];
    }
}