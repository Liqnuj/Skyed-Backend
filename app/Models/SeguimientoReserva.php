<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoReserva extends Model
{
    protected $table = 'seguimiento_reserva';

    protected $primaryKey = 'id_seguimiento';

    protected $fillable = [
        'id_rese',
        'fecha_actualizacion',
        'comentario',
    ];

    protected $casts = [
        'fecha_actualizacion' => 'datetime',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class,
            'id_rese',
            'id_rese'
        );
    }
}