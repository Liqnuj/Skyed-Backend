<?php

namespace App\Services\Reportes\Definiciones\Administracion;

use App\Services\Reportes\Columna;
use App\Services\Reportes\Definiciones\UsuariosBase;
use Illuminate\Database\Eloquent\Model;

/** Tabla "Usuarios y roles" (/admin/usuarios): rol + contexto de cada usuario. */
class UsuariosRolesReporte extends UsuariosBase
{
    public function clave(): string { return 'usuarios-roles'; }
    public function titulo(): string { return 'Reporte de usuarios y roles'; }
    public function modulo(): string { return 'Administración'; }
    public function tema(): string { return 'general'; }

    // Esa pantalla no tiene cuadro de búsqueda.
    protected function permiteBusqueda(): bool { return false; }

    public function columnas(): array
    {
        return [
            new Columna('id', 'ID', Columna::ENTERO, 8),
            new Columna('nombre', 'Nombre', Columna::TEXTO, 26),
            new Columna('correo', 'Correo', Columna::TEXTO, 32),
            new Columna('roles', 'Roles actuales (contexto)', Columna::TEXTO, 40),
        ];
    }

    protected function filtros(): array
    {
        return [
            'rol' => $this->filtroRol(),
            'contexto' => [
                'etiqueta' => 'Contexto',
                'regla' => ['nullable', 'in:social,deportivo,general'],
            ],
        ];
    }

    protected function fila(Model $u, array $filtros): ?array
    {
        $roles = $u->roles
            ->map(fn ($r) => $r->nombre_rol . ' (' . $r->pivot->contexto . ')')
            ->implode(', ');

        return [
            'id' => $u->id_u,
            'nombre' => $this->persona($u),
            'correo' => $u->correo_u,
            'roles' => $roles !== '' ? $roles : 'Sin roles',
        ];
    }
}