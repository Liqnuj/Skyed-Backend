<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estacion extends Model
{
    protected $table = 'estacion';

    protected $primaryKey = 'id_est';

    protected $fillable = [
        'nombre_est',
        'tipo_est',
        'kilometro_est',
        'latitud_est',
        'longitud_est',
        'descripcion_pest',
        'estado_est',
        'id_e',
    ];

    protected $casts = [
        'latitud_est' => 'decimal:7',
        'longitud_est' => 'decimal:7',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(
            EventoDeportivo::class,
            'id_e',
            'id_e'
        );
    }
}