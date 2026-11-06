<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\EntregaKit;
use App\Models\EventoDeportivo;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Entrega de kits" del panel Deportivo. */
class EntregasKitsReporte extends Reporte
{
    private const ESTADOS = ['pendiente' => 'Pendiente', 'entregado' => 'Entregado', 'devolucion' => 'Devolución'];

    public function clave(): string { return 'entregas-kits'; }
    public function titulo(): string { return 'Reporte de entregas de kits'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('persona', 'Persona', Columna::TEXTO, 22),
            new Columna('documento', 'Documento', Columna::TEXTO, 14),
            new Columna('rol', 'Tipo', Columna::TEXTO, 10),
            new Columna('titular', 'Titular', Columna::TEXTO, 20),
            new Columna('evento', 'Evento', Columna::TEXTO, 22),
            new Columna('kit', 'Kit', Columna::TEXTO, 18),
            new Columna('talla', 'Talla', Columna::TEXTO, 9),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('fecha', 'Fecha de entrega', Columna::FECHA_HORA, 16),
            new Columna('entregado_por', 'Entregado por', Columna::TEXTO, 18, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            'estado' => [
                'etiqueta' => 'Estado',
                'regla' => ['nullable', 'in:' . implode(',', array_keys(self::ESTADOS))],
                'valores' => self::ESTADOS,
            ],
            'evento' => [
                'etiqueta' => 'Evento',
                'regla' => ['nullable', 'integer', 'min:1'],
                'texto' => fn ($v) => EventoDeportivo::query()->whereKey($v)->value('nombre_e') ?? ('#' . $v),
            ],
            'rol' => [
                'etiqueta' => 'Persona',
                'regla' => ['nullable', 'in:titular,invitado'],
                'valores' => ['titular' => 'Titulares', 'invitado' => 'Invitados'],
            ],
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = EntregaKit::query()
            ->with([
                'kit',
                'usuario',
                'evento',
                'inscripcion.usuario',
                'inscripcion.invitado',
                'inscripcion',
                'invitado',
            ])
            ->whereHas('inscripcion', fn (Builder $i) => $i->where('estado_i', 'confirmada'))
            ->orderByDesc('id_i')
            ->orderBy('id_inv')
            ->orderBy('id_ek');

        $this->buscar(
            $q,
            $filtros['q'] ?? null,
            fn (Builder $b) => [],
            [
                'invitado' => fn (Builder $b) => [
                    $this->unir($b, [
                        $this->col($b, 'nombre_inv'),
                        $this->col($b, 'apellido_inv'),
                    ]),
                    $this->textoDe($b, 'documento_inv'),
                ],
                'inscripcion.usuario' => fn (Builder $b) => [
                    $this->unir($b, [
                        $this->col($b, 'nombre_u'),
                        $this->col($b, 'apellido_u'),
                    ]),
                    $this->textoDe($b, 'documento_u'),
                ],
            ],
        );

        if (!empty($filtros['evento'])) {
            $q->where('id_e', (int) $filtros['evento']);
        }
        if (!empty($filtros['estado'])) {
            $q->where('estado_ek', $filtros['estado']);
        }
        if (($filtros['rol'] ?? null) === 'titular') {
            $q->whereNull('id_inv');
        } elseif (($filtros['rol'] ?? null) === 'invitado') {
            $q->whereNotNull('id_inv');
        }

        return $q;
    }

    protected function fila(Model $e, array $filtros): ?array
    {
        $invitado = $e->invitado ?? $e->inscripcion?->invitado;
        $titular = $e->inscripcion?->usuario ?? $e->usuario;
        $esInvitado = $e->id_inv !== null;

        return [
            'persona' => $esInvitado
                ? trim(($invitado?->nombre_inv ?? '') . ' ' . ($invitado?->apellido_inv ?? ''))
                : trim(($titular?->nombre_u ?? '') . ' ' . ($titular?->apellido_u ?? '')),
            'documento' => $esInvitado ? $invitado?->documento_inv : $titular?->documento_u,
            'rol' => $esInvitado ? 'Invitado' : 'Titular',
            'titular' => trim(($titular?->nombre_u ?? '') . ' ' . ($titular?->apellido_u ?? '')),
            'evento' => $e->evento?->nombre_e ?? '—',
            'kit' => $e->kit?->nombre_k ?? '—',
            'talla' => $esInvitado
                ? $invitado?->talla_kit_inv
                : $e->inscripcion?->talla_camiseta_i,
            'estado' => self::ESTADOS[$e->estado_ek] ?? Formato::humano($e->estado_ek ?? ''),
            'fecha' => $e->fecha_entrega_real_ek,
            'entregado_por' => $e->persona_entrega_ek,
        ];
    }
}
