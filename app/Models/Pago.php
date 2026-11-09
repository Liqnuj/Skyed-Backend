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
        'hash_comprobante_p',
        'referencia_transaccion_p',
        'verificacion_comprobante_p',
    ];

    protected $casts = [
        'monto_p' => 'decimal:2',
        'fecha_p' => 'datetime',
        'verificacion_comprobante_p' => 'array',
    ];

    /** La huella del archivo no le sirve a nadie en pantalla. */
    protected $hidden = ['hash_comprobante_p'];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(
            Inscripcion::class,
            'id_i',
            'id_i'
        );
    }
}