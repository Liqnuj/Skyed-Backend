<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\EventoDeportivo;
use App\Models\Resultado;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Resultados oficiales" (Deportivo › Resultados). */
class ResultadosReporte extends Reporte
{
    private const ESTADOS = ['oficial' => 'Oficial', 'en revision' => 'En revisión', 'descalificado' => 'Descalificado'];

    public function clave(): string { return 'resultados'; }
    public function titulo(): string { return 'Reporte de resultados oficiales'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('evento', 'Evento', Columna::TEXTO, 24),
            new Columna('posicion', 'Pos.', Columna::ENTERO, 6),
            new Columna('participante', 'Participante', Columna::TEXTO, 24),
            new Columna('tiempo', 'Tiempo', Columna::TEXTO, 10),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('categoria', 'Categoría', Columna::TEXTO, 18),
            new Columna('pos_categoria', 'Pos. categoría', Columna::ENTERO, 9),
            new Columna('competidor', 'Estado del competidor', Columna::TEXTO, 14, enPdf: false),
            new Columna('id', 'ID', Columna::ENTERO, 6, enPdf: false),
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
            'estado' => [
                'etiqueta' => 'Estado',
                'regla' => ['nullable', 'in:' . implode(',', array_keys(self::ESTADOS))],
                'valores' => self::ESTADOS,
            ],
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        // Igual que la pantalla: por nombre de evento y luego por posición (sin posición al final).
        $nombreEvento = EventoDeportivo::query()
            ->select('eventoDeportivo.nombre_e')
            ->join('inscripcion', 'inscripcion.id_e', '=', 'eventoDeportivo.id_e')
            ->whereColumn('inscripcion.id_i', 'resultado.id_i')
            ->limit(1);

        $q = Resultado::query()
            ->with([
                'inscripcion.usuario:id_u,nombre_u,apellido_u',
                'inscripcion.evento:id_e,nombre_e',
                'categoriaResultado.categoria:id_cc,nombre_cc',
            ])
            ->orderBy($nombreEvento)
            ->orderByRaw('posicion_general_r IS NULL')
            ->orderBy('posicion_general_r')
            ->orderBy('id_r');

        // Buscador del panel: participante + evento + estado.
        $this->buscar(
            $q,
            $filtros['q'] ?? null,
            fn (Builder $b) => [$this->col($b, 'estado_r')],
            [
                'inscripcion.usuario' => fn (Builder $b) => [
                    $this->unir($b, [$this->col($b, 'nombre_u'), $this->col($b, 'apellido_u')]),
                ],
                'inscripcion.evento' => fn (Builder $b) => [$this->col($b, 'nombre_e')],
            ],
        );

        if (!empty($filtros['evento'])) {
            $q->whereHas('inscripcion', fn (Builder $i) => $i->where('id_e', (int) $filtros['evento']));
        }

        if (!empty($filtros['estado'])) {
            $q->where('estado_r', $filtros['estado']);
        }

        return $q;
    }

    protected function fila(Model $r, array $filtros): ?array
    {
        $cat = $r->categoriaResultado;

        return [
            'evento' => $r->inscripcion?->evento?->nombre_e ?? '—',
            'posicion' => $r->posicion_general_r,
            'participante' => $this->persona($r->inscripcion?->usuario),
            'tiempo' => $r->tiempo_final_r,
            'estado' => self::ESTADOS[$r->estado_r] ?? Formato::humano($r->estado_r ?? ''),
            'categoria' => $cat?->categoria?->nombre_cc ?? '—',
            'pos_categoria' => $cat?->posicion_categoria,
            'competidor' => $cat?->estado_competidor ? Formato::humano($cat->estado_competidor) : null,
            'id' => $r->id_r,
        ];
    }
}