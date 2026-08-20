<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Inscripcion extends Model
{
    protected $table = 'inscripcion';

    protected $primaryKey = 'id_i';

    protected $fillable = [
        'cupo_i',
        'estado_i',
        'fecha_i',
        'precio_pagado_i',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'contacto_emergencia_parentesco',
        'id_u',
        'id_e',
    ];

    protected $casts = [
        'fecha_i' => 'datetime',
        'precio_pagado_i' => 'decimal:2',
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

    public function pago(): HasOne
    {
        return $this->hasOne(
            Pago::class,
            'id_i',
            'id_i'
        );
    }

    public function qr(): HasOne
    {
        return $this->hasOne(
            QrEntrada::class,
            'id_i',
            'id_i'
        );
    }
}