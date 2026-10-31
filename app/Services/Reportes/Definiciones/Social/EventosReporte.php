<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Models\EventoRealizado;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Gestión de eventos sociales" (Social › Eventos). */
class EventosReporte extends Reporte
{
    public function clave(): string { return 'eventos'; }
    public function titulo(): string { return 'Reporte de eventos sociales'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 24),
            new Columna('tipo', 'Tipo de evento', Columna::TEXTO, 16),
            new Columna('ambiente', 'Ambiente', Columna::TEXTO, 18),
            new Columna('fecha', 'Fecha', Columna::FECHA, 11),
            new Columna('estado', 'Estado', Columna::TEXTO, 10),
            new Columna('reservas', 'Reservas', Columna::ENTERO, 9),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 34, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'estado' => [
                'etiqueta' => 'Estado',
                'regla' => ['nullable', 'in:activo,inactivo'],
                'valores' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
            ],
        ] + $this->filtrosFecha('Fecha del evento');
    }

    protected function consulta(array $filtros): Builder
    {
        $q = EventoRealizado::query()
            ->with(['tipoEvento', 'ambiente'])
            ->withCount('reservas')
            ->orderBy('id_er');

        // Buscador del panel: nombre + descripción + estado.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_er'),
                $this->col($b, 'descripcion_er'),
                $this->col($b, 'estado_er'),
            ]),
        ]);

        if (!empty($filtros['estado'])) {
            $q->where('estado_er', $filtros['estado']);
        }

        $this->entreFechas($q, 'fecha_er', $filtros);

        return $q;
    }

    protected function fila(Model $e, array $filtros): ?array
    {
        return [
            'id' => $e->id_er,
            'nombre' => $e->nombre_er,
            'tipo' => $e->tipoEvento?->nombre_tipo_eves,
            'ambiente' => $e->ambiente?->nombre_a,
            'fecha' => $e->fecha_er,
            'estado' => Formato::humano($e->estado_er),
            'reservas' => $e->reservas_count,
            'descripcion' => $e->descripcion_er,
        ];
    }
}