<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\EventoDeportivo;
use App\Models\Pago;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Inscripciones y pagos" (Deportivo › Inscripciones). Una fila por pago. */
class InscripcionesPagosReporte extends Reporte
{
    private const ESTADOS = [
        'pendiente' => 'Pendiente',
        'confirmado' => 'Confirmado',
        'rechazado' => 'Rechazado',
        'cancelado' => 'Cancelado',
    ];

    public function clave(): string { return 'inscripciones-pagos'; }
    public function titulo(): string { return 'Reporte de inscripciones y pagos'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID pago', Columna::ENTERO, 6),
            new Columna('participante', 'Participante', Columna::TEXTO, 20),
            new Columna('correo', 'Correo', Columna::TEXTO, 24),
            new Columna('evento', 'Evento', Columna::TEXTO, 22),
            new Columna('fecha', 'Inscripción', Columna::FECHA_HORA, 14),
            new Columna('monto', 'Monto', Columna::MONEDA, 12),
            new Columna('metodo', 'Método', Columna::TEXTO, 12),
            new Columna('pago', 'Pago', Columna::TEXTO, 11),
            new Columna('inscripcion', 'Estado de inscripción', Columna::TEXTO, 12, enPdf: false),
            new Columna('referencia', 'Referencia', Columna::TEXTO, 20, enPdf: false),
            new Columna('fecha_pago', 'Fecha del pago', Columna::FECHA_HORA, 16, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'estado' => [
                'etiqueta' => 'Estado del pago',
                'regla' => ['nullable', 'in:' . implode(',', array_keys(self::ESTADOS))],
                'valores' => self::ESTADOS,
            ],
            'evento' => [
                'etiqueta' => 'Evento',
                'regla' => ['nullable', 'integer', 'min:1'],
                'texto' => fn ($v) => EventoDeportivo::query()->whereKey($v)->value('nombre_e') ?? ('#' . $v),
            ],
        ] + $this->filtrosFecha('Fecha de inscripción');
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Pago::query()
            ->with(['inscripcion.usuario:id_u,nombre_u,apellido_u,correo_u', 'inscripcion.evento:id_e,nombre_e'])
            ->orderBy('id_pago');

        // Buscador del panel: participante + correo + evento + referencia del pago.
        $this->buscar(
            $q,
            $filtros['q'] ?? null,
            fn (Builder $b) => [$this->col($b, 'referencia_p')],
            [
                'inscripcion.usuario' => fn (Builder $b) => [
                    $this->unir($b, [
                        $this->col($b, 'nombre_u'),
                        $this->col($b, 'apellido_u'),
                        $this->col($b, 'correo_u'),
                    ]),
                ],
                'inscripcion.evento' => fn (Builder $b) => [$this->col($b, 'nombre_e')],
            ],
        );

        if (!empty($filtros['estado'])) {
            $q->where('estado_p', $filtros['estado']);
        }

        if (!empty($filtros['evento'])) {
            $q->whereHas('inscripcion', fn (Builder $i) => $i->where('id_e', (int) $filtros['evento']));
        }

        if (!empty($filtros['fecha_desde']) || !empty($filtros['fecha_hasta'])) {
            $q->whereHas('inscripcion', fn (Builder $i) => $this->entreFechas($i, 'fecha_i', $filtros, true));
        }

        return $q;
    }

    public function resumen(array $filtros): array
    {
        $suma = $this->consulta($filtros)->reorder()->where('estado_p', 'confirmado')->sum('monto_p');

        return ['Total de pagos confirmados' => Formato::moneda($suma)];
    }

    protected function fila(Model $p, array $filtros): ?array
    {
        $i = $p->inscripcion;

        return [
            'id' => $p->id_pago,
            'participante' => $this->persona($i?->usuario),
            'correo' => $i?->usuario?->correo_u,
            'evento' => $i?->evento?->nombre_e ?? '—',
            'fecha' => $i?->fecha_i,
            'monto' => $p->monto_p,
            'metodo' => $p->metodo_pago_p ? Formato::humano($p->metodo_pago_p) : '—',
            'pago' => self::ESTADOS[$p->estado_p] ?? Formato::humano($p->estado_p ?? ''),
            'inscripcion' => $i ? Formato::humano($i->estado_i ?? '') : '—',
            'referencia' => $p->referencia_p,
            'fecha_pago' => $p->fecha_p,
        ];
    }
}