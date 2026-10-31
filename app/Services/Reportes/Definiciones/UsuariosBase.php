<?php

namespace App\Services\Reportes\Definiciones;

use App\Models\User;
use App\Services\Reportes\Reporte;
use Illuminate\Database\Eloquent\Builder;

/**
 * Consulta común de las tablas de usuarios. Cada tabla del panel
 * (Social, Deportivo, Usuarios y roles) tiene su propio reporte; esta clase
 * solo evita repetir la consulta. Nunca expone contraseña, código de
 * recuperación, documento, RH ni fecha de nacimiento.
 */
abstract class UsuariosBase extends Reporte
{
    protected function consulta(array $filtros): Builder
    {
        $q = User::query()->with('roles')->orderBy('id_u');

        // Buscador del panel: nombre + apellido + correo.
        $this->buscar($q, $filtros['q'] ?? null, fn (Builder $b) => [
            $this->unir($b, [
                $this->col($b, 'nombre_u'),
                $this->col($b, 'apellido_u'),
                $this->col($b, 'correo_u'),
            ]),
        ]);

        if (!empty($filtros['estado'])) {
            $q->where('estado_u', $filtros['estado']);
        }

        if (!empty($filtros['rol'])) {
            $q->whereHas('roles', fn ($r) => $r->where('rol.nombre_rol', $filtros['rol']));
        }

        if (!empty($filtros['contexto'])) {
            $q->whereHas('roles', fn ($r) => $r->where('usuario_contexto_rol.contexto', $filtros['contexto']));
        }

        return $q;
    }

    protected function filtroEstado(): array
    {
        return [
            'etiqueta' => 'Estado',
            'regla' => ['nullable', 'in:activo,inactivo'],
            'valores' => ['activo' => 'Activo', 'inactivo' => 'Inactivo'],
        ];
    }

    protected function filtroRol(): array
    {
        return [
            'etiqueta' => 'Rol',
            'regla' => ['nullable', 'in:cliente,participante,adminSocial,adminDeportivo'],
        ];
    }
}