<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Models\Pqr;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Tabla "Gestión de PQRS" (Social › PQRS). Nunca incluye notas internas. */
class PqrsReporte extends Reporte
{
    private const TIPOS = [
        'peticion' => 'Petición', 'queja' => 'Queja', 'reclamo' => 'Reclamo',
        'sugerencia' => 'Sugerencia', 'felicitacion' => 'Felicitación',
    ];

    public function clave(): string { return 'pqrs'; }
    public function titulo(): string { return 'Reporte de PQRS'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('radicado', 'Radicado', Columna::TEXTO, 12),
            new Columna('solicitante', 'Solicitante', Columna::TEXTO, 17),
            new Columna('correo', 'Correo', Columna::TEXTO, 20, enPdf: false),
            new Columna('tipo', 'Tipo', Columna::TEXTO, 10),
            new Columna('asunto', 'Asunto', Columna::TEXTO, 26),
            new Columna('fase', 'Fase', Columna::TEXTO, 10),
            new Columna('radicada', 'Radicada el', Columna::FECHA_HORA, 14),
            new Columna('limite', 'Fecha límite', Columna::FECHA, 11),
            new Columna('vencida', 'Vencida', Columna::TEXTO, 8),
            new Columna('actuacion', 'Última actuación', Columna::FECHA_HORA, 14, enPdf: false),
            new Columna('mensaje', 'Mensaje', Columna::TEXTO, 40, enPdf: false),
            new Columna('respuesta', 'Respuesta', Columna::TEXTO, 40, enPdf: false),
            new Columna('respondido', 'Respondida el', Columna::FECHA_HORA, 14, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return [
            // Son los mismos filtros del panel: tarjetas de fase, selector de tipo y "Vencidas".
            'estado' => [
                'etiqueta' => 'Fase',
                'regla' => ['nullable', Rule::in(Pqr::ESTADOS)],
                'valores' => Pqr::ETIQUETAS_ESTADO,
            ],
            'tipo' => [
                'etiqueta' => 'Tipo',
                'regla' => ['nullable', Rule::in(Pqr::TIPOS)],
                'valores' => self::TIPOS,
            ],
            'vencidas' => [
                'etiqueta' => 'Solo vencidas',
                'regla' => ['nullable', 'boolean'],
                'tipo' => 'bool',
            ],
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Pqr::query()
            ->with('usuario:id_u,nombre_u,apellido_u,correo_u')
            ->withMax('seguimientos as ultima_actuacion_en', 'created_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id_pqr');

        // Buscador del panel: radicado + asunto + tipo + solicitante + correo.
        $this->buscar(
            $q,
            $filtros['q'] ?? null,
            function (Builder $b) {
                $casos = '';
                foreach (self::TIPOS as $valor => $etiqueta) {
                    $casos .= " WHEN '$valor' THEN '$etiqueta'";
                }

                return [
                    $this->unir($b, ["'SS-PQR-'", $this->textoDe($b, 'id_pqr')], ''),
                    $this->col($b, 'asunto_pqr'),
                    $this->col($b, 'tipo_pqr'),
                    'CASE ' . $this->col($b, 'tipo_pqr') . $casos . ' ELSE ' . $this->col($b, 'tipo_pqr') . ' END',
                ];
            },
            [
                'usuario' => fn (Builder $b) => [
                    $this->unir($b, [
                        $this->col($b, 'nombre_u'),
                        $this->col($b, 'apellido_u'),
                        $this->col($b, 'correo_u'),
                    ]),
                ],
            ],
        );

        if (!empty($filtros['estado'])) {
            $q->where('estado_pqr', $filtros['estado']);
        }

        if (!empty($filtros['tipo'])) {
            $q->where('tipo_pqr', $filtros['tipo']);
        }

        return $q;
    }

    protected function fila(Model $p, array $filtros): ?array
    {
        // "vencida" se calcula en el modelo (días hábiles según el tipo), no es una columna.
        if (!empty($filtros['vencidas']) && !$p->vencida) {
            return null;
        }

        $limite = $p->fecha_limite;

        return [
            'radicado' => $p->radicado,
            'solicitante' => $this->persona($p->usuario),
            'correo' => $p->usuario?->correo_u,
            'tipo' => self::TIPOS[$p->tipo_pqr] ?? Formato::humano($p->tipo_pqr),
            'asunto' => $p->asunto_pqr,
            'fase' => $p->estado_etiqueta,
            'radicada' => $p->creado_en_pqr ?? $p->created_at,
            'limite' => $limite ? substr($limite, 0, 10) : null,
            'vencida' => $p->vencida ? 'Sí' : 'No',
            'actuacion' => $p->ultima_actuacion_en ?? $p->creado_en_pqr ?? $p->created_at,
            'mensaje' => $p->mensaje_pqr,
            'respuesta' => $p->respuesta_pqr,
            'respondido' => $p->respondido_en_pqr,
        ];
    }
}