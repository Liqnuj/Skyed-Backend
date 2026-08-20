<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pago';

    protected $primaryKey = 'id_pago';

    protected $fillable = [
        'metodo_pago_p',
        'referencia_p',
        'comprobante_p',
        'monto_p',
        'fecha_p',
        'estado_p',
        'id_i',
    ];

    protected $casts = [
        'monto_p' => 'decimal:2',
        'fecha_p' => 'datetime',
    ];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(
            Inscripcion::class,
            'id_i',
            'id_i'
        );
    }
}