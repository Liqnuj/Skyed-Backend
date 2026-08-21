<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kit extends Model
{
    protected $table = 'kit';

    protected $primaryKey = 'id_k';

    protected $fillable = [
        'nombre_k',
        'stock_k',
        'fecha_entrega_k',
        'lugar_entrega_k',
        'contenido_k',
        'talla_camiseta_k',
        'numero_dorsal_k',
    ];

    protected $casts = [
        'fecha_entrega_k' => 'date',
        'stock_k' => 'integer',
        'numero_dorsal_k' => 'integer',
    ];

    public function eventos(): HasMany
    {
        return $this->hasMany(
            EventoDeportivo::class,
            'id_k',
            'id_k'
        );
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(
            EntregaKit::class,
            'id_k',
            'id_k'
        );
    }
}