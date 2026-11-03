<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\EventoDeportivo;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Gestión de eventos deportivos" (Deportivo › Eventos). */
class EventosReporte extends Reporte
{
    private const CATEGORIAS = ['ruta' => 'Ruta', 'mtb' => 'MTB', 'gravel' => 'Gravel', 'pista' => 'Pista', 'bmx' => 'BMX'];

    public function clave(): string { return 'eventos'; }
    public function titulo(): string { return 'Reporte de eventos deportivos'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 5),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 24),
            new Columna('categoria', 'Categoría', Columna::TEXTO, 10),
            new Columna('fecha', 'Fecha', Columna::FECHA, 11),
            new Columna('hora', 'Hora', Columna::TEXTO, 8),
            new Columna('ubicacion', 'Ubicación', Columna::TEXTO, 20),
            new Columna('precio', 'Precio', Columna::MONEDA, 12),
            new Columna('cupos', 'Cupos', Columna::ENTERO, 7),
            new Columna('categorias', 'Categorías de competencia', Columna::ENTERO, 11),
            new Columna('estado', 'Estado', Columna::TEXTO, 11),
            new Columna('kit', 'Kit', Columna::TEXTO, 16, enPdf: false),
            new Columna('distancia', 'Distancia', Columna::TEXTO, 12, enPdf: false),
            new Columna('desnivel', 'Desnivel', Columna::TEXTO, 12, enPdf: false),
            new Columna('descripcion', 'Descripción', Columna::TEXTO, 36, enPdf: false),
            new Columna('requisitos', 'Requisitos', Columna::TEXTO, 36, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'estado' => [
                'etiqueta' => 'Estado',
                'regla' => ['nullable', 'in:activo,inactivo,inhabilitado'],
                'valores' => ['activo' => 'Activo', 'inactivo' => 'Inactivo', 'inhabilitado' => 'Inhabilitado'],
            ],
            'categoria' => [
                'etiqueta' => 'Categoría',
                'regla' => ['nullable', 'in:' . implode(',', array_keys(self::CATEGORIAS))],
                'valores' => self::CATEGORIAS,
            ],
        ] + $this->filtrosFecha('Fecha del evento');
    }

    protected function consulta(array $filtros): Builder
    {
        $q = EventoDeportivo::query()
            ->with('kit')
            ->withCount('categorias')
            ->orderBy('id_e');

        // Buscador del panel: nombre + categoría + ubicación + estado.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_e'),
                $this->col($b, 'categoria_e'),
                $this->col($b, 'ubicacion_e'),
                $this->col($b, 'estado_e'),
            ]),
        ]);

        if (!empty($filtros['estado'])) {
            $q->where('estado_e', $filtros['estado']);
        }

        if (!empty($filtros['categoria'])) {
            $q->where('categoria_e', $filtros['categoria']);
        }

        $this->entreFechas($q, 'fecha_e', $filtros);

        return $q;
    }

    protected function fila(Model $e, array $filtros): ?array
    {
        return [
            'id' => $e->id_e,
            'nombre' => $e->nombre_e,
            'categoria' => self::CATEGORIAS[$e->categoria_e] ?? ($e->categoria_e ?: '—'),
            'fecha' => $e->fecha_e,
            'hora' => $e->hora_e ? substr((string) $e->hora_e, 0, 5) : null,
            'ubicacion' => $e->ubicacion_e,
            'precio' => $e->precio_e,
            'cupos' => $e->cupos_disponibles_e,
            'categorias' => $e->categorias_count,
            'estado' => Formato::humano($e->estado_e ?? ''),
            'kit' => $e->kit?->nombre_k,
            'distancia' => $e->distancia_e,
            'desnivel' => $e->desnivel_e,
            'descripcion' => $e->descripcion_e,
            'requisitos' => $e->requisitos_e,
        ];
    }
}