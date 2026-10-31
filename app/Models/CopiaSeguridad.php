<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de una copia de seguridad. Los datos están en un archivo privado
 * (archivo_cs); ver App\Support\GestorCopias.
 */
class CopiaSeguridad extends Model
{
    protected $table = 'copia_seguridad';

    protected $primaryKey = 'id_cs';

    protected $fillable = [
        'fecha_cs',
        'alcance_cs',
        'conjuntos_cs',
        'archivo_cs',
        'tamano_cs',
        'registros_cs',
        'checksum_cs',
        'origen_cs',
        'id_u',
    ];

    protected $casts = [
        'fecha_cs' => 'datetime',
        'conjuntos_cs' => 'array',
        'tamano_cs' => 'integer',
        'registros_cs' => 'integer',
    ];

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_u', 'id_u');
    }
}
