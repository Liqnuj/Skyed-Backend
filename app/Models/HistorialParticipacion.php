<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialParticipacion extends Model
{
    protected $table = 'historial_participacion';

    protected $primaryKey = 'id_hp';

    protected $fillable = [
        'fecha_hp',
        'estado_hp',
        'observaciones_hp',
        'id_u',
        'id_e',
    ];

    protected $casts = [
        'fecha_hp' => 'datetime',
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
            EventoDeportivo::class,
            'id_e',
            'id_e'
        );
    }
}