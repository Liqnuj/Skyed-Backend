<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RutaEvento extends Model
{
    protected $table = 'ruta_evento';

    protected $primaryKey = 'id_re';

    protected $fillable = [
        'nombre_re',
        'estado_re',
        'distancia_re',
        'desnivel_re',
        'descripcion_re',
        'archivo_gpx_re',
        'precio_re',
        'id_e',
    ];

    protected $casts = [
        'precio_re' => 'decimal:2',
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