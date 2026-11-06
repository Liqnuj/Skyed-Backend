<?php

namespace App\Services\Reportes\Definiciones\Deportivo;

use App\Models\Patrocinador;
use App\Services\Reportes\Columna;
use App\Services\Reportes\Formato;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Patrocinadores" del panel Deportivo. */
class PatrocinadoresReporte extends Reporte
{
    public function clave(): string { return 'patrocinadores'; }
    public function titulo(): string { return 'Reporte de patrocinadores'; }
    public function modulo(): string { return 'Skyed Deportivo'; }
    public function tema(): string { return 'deportivo'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 6),
            new Columna('nombre', 'Patrocinador', Columna::TEXTO, 22),
            new Columna('correo', 'Correo', Columna::TEXTO, 24),
            new Columna('telefono', 'Teléfono', Columna::TEXTO, 14),
            new Columna('aporte', 'Aporte', Columna::TEXTO, 20),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('eventos', 'Eventos', Columna::TEXTO, 30),
            new Columna('pagina', 'Página web', Columna::TEXTO, 30, enPdf: false),
        ];
    }

    protected function consulta(array $filtros): Builder
    {
        $q = Patrocinador::query()->with('eventos:id_e,nombre_e')->orderBy('id_p');

        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_p'),
                $this->col($b, 'correo_p'),
                $this->col($b, 'telefono_p'),
                $this->col($b, 'aporte_p'),
            ]),
        ], [
            'eventos' => fn (Builder $b) => [$this->col($b, 'nombre_e')],
        ]);

        return $q;
    }

    protected function fila(Model $p, array $filtros): ?array
    {
        return [
            'id' => $p->id_p,
            'nombre' => $p->nombre_p,
            'correo' => $p->correo_p,
            'telefono' => $p->telefono_p,
            'aporte' => $p->aporte_p,
            'estado' => Formato::humano($p->estado_p ?? 'activo'),
            'eventos' => $p->eventos->pluck('nombre_e')->implode(', ') ?: 'Sin asignar',
            'pagina' => $p->pagina_web_p,
        ];
    }
}
