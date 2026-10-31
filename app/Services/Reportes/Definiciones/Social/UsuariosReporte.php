<?php

namespace App\Services\Reportes\Definiciones\Social;

use App\Services\Reportes\Columna;
use App\Services\Reportes\Definiciones\UsuariosBase;
use App\Services\Reportes\Formato;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Usuarios registrados" del panel Social. */
class UsuariosReporte extends UsuariosBase
{
    public function clave(): string { return 'usuarios'; }
    public function titulo(): string { return 'Reporte de usuarios registrados'; }
    public function modulo(): string { return 'Skyed Social'; }
    public function tema(): string { return 'social'; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 8),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 28),
            new Columna('correo', 'Correo', Columna::TEXTO, 34),
            new Columna('estado', 'Estado', Columna::TEXTO, 12),
            new Columna('telefono', 'Teléfono', Columna::TEXTO, 16, enPdf: false),
            new Columna('ciudad', 'Ciudad', Columna::TEXTO, 16, enPdf: false),
            new Columna('registro', 'Registrado el', Columna::FECHA_HORA, 16, enPdf: false),
        ];
    }

    protected function filtros(): array
    {
        return ['estado' => $this->filtroEstado()];
    }

    protected function fila(Model $u, array $filtros): ?array
    {
        return [
            'id' => $u->id_u,
            'nombre' => $this->persona($u),
            'correo' => $u->correo_u,
            'estado' => Formato::humano($u->estado_u ?? 'activo'),
            'telefono' => $u->telefono_u,
            'ciudad' => $u->ciudad_u,
            'registro' => $u->created_at,
        ];
    }
}