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
        'id_cc',
        'desea_kit_i',
        'condiciones_medicas_i',
        'telefono_contacto_i',
        'estado_i',
        'fecha_i',
        'precio_pagado_i',
        'precio_inscripcion_i',
        'precio_kit_i',
        'precio_invitado_i',
        'precio_kit_invitado_i',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'contacto_emergencia_parentesco',
        'id_u',
        'id_e',
        'id_inv',
        'talla_camiseta_i',
        'numero_dorsal_i',
    ];

    protected $casts = [
        'fecha_i' => 'datetime',
        'desea_kit_i' => 'boolean',
        'precio_pagado_i' => 'decimal:2',
        'precio_inscripcion_i' => 'decimal:2',
        'precio_kit_i' => 'decimal:2',
        'precio_invitado_i' => 'decimal:2',
        'precio_kit_invitado_i' => 'decimal:2',
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

    public function invitado(): BelongsTo
    {
        return $this->belongsTo(
            Invitado::class,
            'id_inv',
            'id_inv'
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