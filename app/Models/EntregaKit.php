<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntregaKit extends Model
{
    protected $table = 'entrega_kit';

    protected $primaryKey = 'id_ek';

    protected $fillable = [
        'fecha_entrega_real_ek',
        'persona_entrega_ek',
        'estado_ek',
        'observaciones_ek',
        'id_k',
        'id_u',
        'id_e',
    ];

    protected $casts = [
        'fecha_entrega_real_ek' => 'datetime',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(
            Kit::class,
            'id_k',
            'id_k'
        );
    }

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