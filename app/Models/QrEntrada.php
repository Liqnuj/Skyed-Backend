<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrEntrada extends Model
{
    protected $table = 'qr_entrada';

    protected $primaryKey = 'id_qr';

    protected $fillable = [
        'codigo_qr',
        'qr_imagen_qr',
        'fecha_generacion_qr',
        'fecha_uso_qr',
        'estado_qr',
        'id_i',
    ];

    protected $casts = [
        'fecha_generacion_qr' => 'datetime',
        'fecha_uso_qr' => 'datetime',
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