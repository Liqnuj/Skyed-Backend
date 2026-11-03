<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\Kit;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Kits del participante" (Deportivo › Kits). */
class KitsReporte extends Reporte
{
    public function clave(): string { return 'kits'; }
    public function titulo(): string { return 'Reporte de kits del participante'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 22),
            new Columna('stock', 'Stock', Columna::ENTERO, 8),
            new Columna('entrega', 'Entrega', Columna::FECHA, 11),
            new Columna('lugar', 'Lugar', Columna::TEXTO, 20),
            new Columna('contenido', 'Contenido', Columna::TEXTO, 34),
            new Columna('eventos', 'Eventos que lo usan', Columna::ENTERO, 10),
            new Columna('talla', 'Talla de camiseta', Columna::TEXTO, 12, enPdf: false),
            new Columna('dorsal', 'N.º de dorsal', Columna::ENTERO, 10, enPdf: false),
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Kit::query()->withCount('eventos')->orderBy('id_k');

        // Buscador del panel: nombre + lugar + contenido.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_k'),
                $this->col($b, 'lugar_entrega_k'),
                $this->col($b, 'contenido_k'),
            ]),
        ]);

        return $q;
    }

    protected function fila(Model $k, array $filtros): ?array
    {
        return [
            'id' => $k->id_k,
            'nombre' => $k->nombre_k,
            'stock' => $k->stock_k,
            'entrega' => $k->fecha_entrega_k,
            'lugar' => $k->lugar_entrega_k,
            'contenido' => $k->contenido_k,
            'eventos' => $k->eventos_count,
            'talla' => $k->talla_camiseta_k,
            'dorsal' => $k->numero_dorsal_k,
        ];
    }
}