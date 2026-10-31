<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Models\Ambiente;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Gestión de ambientes" (Social › Lugares). */
class AmbientesReporte extends Reporte
{
    public function clave(): string { return 'ambientes'; }
    public function titulo(): string { return 'Reporte de ambientes'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 20),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 32),
            new Columna('capacidad', 'Capacidad', Columna::ENTERO, 10),
            new Columna('precio', 'Precio de referencia', Columna::MONEDA, 14),
            new Columna('servicios', 'Servicios', Columna::TEXTO, 24),
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Ambiente::query()->with('servicios')->orderBy('id_a');

        // Mismo criterio del buscador del panel: nombre + descripción.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [$this->col($b, 'nombre_a'), $this->col($b, 'descripcion_a')]),
        ]);

        return $q;
    }

    protected function fila(Model $a, array $filtros): ?array
    {
        return [
            'id' => $a->id_a,
            'nombre' => $a->nombre_a,
            'descripcion' => $a->descripcion_a,
            'capacidad' => $a->capacidad_a,
            'precio' => $a->precio_referencia_a,
            'servicios' => $a->servicios->pluck('nombre_s')->implode(', ') ?: '—',
        ];
    }
}