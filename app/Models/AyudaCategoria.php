<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AyudaCategoria extends Model
{
    protected $table = 'ayuda_categoria';

    protected $primaryKey = 'id_ayc';

    protected $fillable = [
        'nombre_ayc',
        'slug_ayc',
        'descripcion_ayc',
        'icono_ayc',
        'modulo_ayc',
        'orden_ayc',
        'activa_ayc',
    ];

    protected $casts = [
        'activa_ayc' => 'boolean',
        'orden_ayc' => 'integer',
    ];

    public function articulos(): HasMany
    {
        return $this->hasMany(AyudaArticulo::class, 'id_ayc', 'id_ayc');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa_ayc', true);
    }

    /** Con módulo ('social' o 'deportivo') incluye también las generales. */
    public function scopeDeModulo(Builder $query, ?string $modulo): Builder
    {
        if (in_array($modulo, ['deportivo', 'social'], true)) {
            $query->whereIn('modulo_ayc', ['general', $modulo]);
        }

        return $query;
    }
}