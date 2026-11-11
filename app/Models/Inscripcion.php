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

    /**
     * Desglose de lo pagado: inscripción, kit, invitado y kit del invitado.
     *
     * Las inscripciones nuevas lo guardan en las columnas precio_*_i. Las anteriores
     * a ese cambio quedaron con esas columnas en 0/nulo; para esas se reconstruye con
     * el precio actual del evento y del kit, pero SOLO si la suma coincide exactamente
     * con lo que se pagó (si no coincide, no se inventa nada y se devuelve lo guardado).
     */
    public function getDesglosePreciosAttribute(): array
    {
        $guardado = [
            'inscripcion' => (float) ($this->precio_inscripcion_i ?? 0),
            'kit' => (float) ($this->precio_kit_i ?? 0),
            'invitado' => (float) ($this->precio_invitado_i ?? 0),
            'kit_invitado' => (float) ($this->precio_kit_invitado_i ?? 0),
        ];

        $total = (float) ($this->precio_pagado_i ?? 0);

        if (array_sum($guardado) > 0 || $total <= 0) {
            return $guardado;
        }

        $evento = $this->evento;
        $kit = $evento?->kit;
        $precioEvento = (float) ($evento->precio_e ?? 0);
        $precioKit = (float) ($kit->precio_k ?? 0);

        $reconstruido = [
            'inscripcion' => $precioEvento,
            'kit' => $this->desea_kit_i ? $precioKit : 0.0,
            'invitado' => $this->id_inv ? $precioEvento : 0.0,
            'kit_invitado' => ($this->id_inv && $this->invitado?->desea_kit_inv) ? $precioKit : 0.0,
        ];

        return abs(array_sum($reconstruido) - $total) < 0.01 ? $reconstruido : $guardado;
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

    public function invitado(): BelongsTo
    {
        return $this->belongsTo(
            Invitado::class,
            'id_inv',
            'id_inv'
        );
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(
            CategoriaCompetencia::class,
            'id_cc',
            'id_cc'
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