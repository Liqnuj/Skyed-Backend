<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoriaResultado extends Model
{
    protected $table = 'categoria_resultado';

    protected $primaryKey = 'id_categoria_resultado';

    protected $fillable = [
        'posicion_categoria',
        'estado_competidor',
        'id_cc',
        'id_r',
    ];

    protected $casts = [
        'posicion_categoria' => 'integer',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(
            CategoriaCompetencia::class,
            'id_cc',
            'id_cc'
        );
    }

    public function resultado(): BelongsTo
    {
        return $this->belongsTo(
            Resultado::class,
            'id_r',
            'id_r'
        );
    }
}