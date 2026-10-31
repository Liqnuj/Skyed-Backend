<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Models\Reserva;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Gestión de reservas" (Social › Reservas). */
class ReservasReporte extends Reporte
{
    public function clave(): string { return 'reservas'; }
    public function titulo(): string { return 'Reporte de reservas'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('cliente', 'Cliente', Columna::TEXTO, 18),
            new Columna('correo', 'Correo', Columna::TEXTO, 22, enPdf: false),
            new Columna('nombre', 'Nombre de la reserva', Columna::TEXTO, 18, enPdf: false),
            new Columna('evento', 'Evento asociado', Columna::TEXTO, 18),
            new Columna('fecha', 'Fecha del evento', Columna::FECHA, 11),
            new Columna('ubicacion', 'Ubicación', Columna::TEXTO, 16),
            new Columna('invitados', 'Invitados', Columna::ENTERO, 8),
            new Columna('presupuesto', 'Presupuesto', Columna::MONEDA, 13, enPdf: false),
            new Columna('total', 'Total', Columna::MONEDA, 13),
            new Columna('estado', 'Estado', Columna::TEXTO, 11),
            new Columna('creada', 'Creada el', Columna::FECHA_HORA, 15, enPdf: false),
            new Columna('observaciones', 'Observaciones', Columna::TEXTO, 30, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'estado' => [
                'etiqueta' => 'Estado',
                'regla' => ['nullable', 'in:pendiente,confirmada,cancelada,completada'],
                'valores' => [
                    'pendiente' => 'Pendiente', 'confirmada' => 'Confirmada',
                    'cancelada' => 'Cancelada', 'completada' => 'Completada',
                ],
            ],
        ] + $this->filtrosFecha('Fecha del evento');
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Reserva::query()
            ->with(['usuario:id_u,nombre_u,apellido_u,correo_u', 'evento:id_er,nombre_er'])
            ->orderBy('id_rese');

        // Buscador del panel: ID + fecha + estado.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->textoDe($b, 'id_rese'),
                $this->textoDe($b, 'fecha_evento_rese'),
                $this->col($b, 'estado_rese'),
            ]),
        ]);

        if (!empty($filtros['estado'])) {
            $q->where('estado_rese', $filtros['estado']);
        }

        $this->entreFechas($q, 'fecha_evento_rese', $filtros);

        return $q;
    }

    /** Igual que el KPI "Ingresos confirmados" del panel, pero con los filtros del reporte. */
    public function resumen(array $filtros): array
    {
        $total = $this->consulta($filtros)->reorder()
            ->where('estado_rese', 'confirmada')
            ->sum('total_rese');

        return ['Total de reservas confirmadas' => Formato::moneda($total)];
    }

    protected function fila(Model $r, array $filtros): ?array
    {
        return [
            'id' => $r->id_rese,
            'cliente' => $this->persona($r->usuario),
            'correo' => $r->usuario?->correo_u,
            'nombre' => $r->nombre_rese,
            'evento' => $r->evento?->nombre_er,
            'fecha' => $r->fecha_evento_rese,
            'ubicacion' => $r->ubicacion_rese,
            'invitados' => $r->invitados_rese,
            'presupuesto' => $r->presupuesto_rese,
            'total' => $r->total_rese,
            'estado' => Formato::humano($r->estado_rese),
            'creada' => $r->creado_en_rese,
            'observaciones' => $r->Observaciones_rese,
        ];
    }
}