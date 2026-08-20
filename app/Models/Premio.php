<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Premio extends Model
{
    protected $table = 'premio';

    protected $primaryKey = 'id_premio';

    protected $fillable = [
        'nombre_premio',
        'descripcion_premio',
        'posicion_premio',
        'valor_premio',
        'id_r',
    ];

    protected $casts = [
        'posicion_premio' => 'integer',
        'valor_premio' => 'decimal:2',
    ];

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(
            Resultado::class,
            'id_r',
            'id_r'
        );
    }
}