<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reserva extends Model
{
    protected $table = 'reserva';

    protected $primaryKey = 'id_rese';

    protected $fillable = [
        'fecha_evento_rese',
        'invitados_rese',
        'presupuesto_rese',
        'ubicacion_rese',
        'Observaciones_rese',
        'total_rese',
        'estado_rese',
        'creado_en_rese',
        'id_u',
        'id_er',
    ];

    protected $casts = [
        'fecha_evento_rese' => 'date',
        'presupuesto_rese' => 'decimal:2',
        'total_rese' => 'decimal:2',
        'creado_en_rese' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(
            EventoRealizado::class,
            'id_er',
            'id_er'
        );
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(
            SeguimientoReserva::class,
            'id_rese',
            'id_rese'
        );
    }
}