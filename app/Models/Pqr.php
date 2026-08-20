<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pqr extends Model
{
    protected $table = 'pqr';

    protected $primaryKey = 'id_pqr';

    protected $fillable = [
        'tipo_pqr',
        'asunto_pqr',
        'mensaje_pqr',
        'estado_pqr',
        'respuesta_pqr',
        'respondido_en_pqr',
        'creado_en_pqr',
        'id_u',
    ];

    protected $casts = [
        'respondido_en_pqr' => 'datetime',
        'creado_en_pqr' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }
}   