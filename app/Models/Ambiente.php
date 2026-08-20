<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ambiente extends Model
{
    protected $table = 'ambiente';

    protected $primaryKey = 'id_a';

    protected $fillable = [
        'nombre_a',
        'descripcion_a',
        'capacidad_a',
        'precio_referencia_a',
        'imagen_principal_a',
    ];

    protected $casts = [
        'capacidad_a' => 'integer',
        'precio_referencia_a' => 'decimal:2',
    ];

    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(
            Servicio::class,
            'ambiente_servicio',
            'id_a',
            'id_s',
            'id_a',
            'id_s'
        );
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(
            EventoRealizado::class,
            'id_a',
            'id_a'
        );
    }
}