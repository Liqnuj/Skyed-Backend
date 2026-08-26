<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaCompetencia extends Model
{
    protected $table = 'categoria_competencia';

    protected $primaryKey = 'id_cc';

    protected $fillable = [
        'nombre_cc',
        'edad_minima_cc',
        'edad_maxima_cc',
        'genero_cc',
        'distancia_cc',
        'descripcion_cc',
        'id_e',
    ];

    public function evento(): BelongsTo
    {
        return $this->belongsTo(
            EventoDeportivo::class,
            'id_e',
            'id_e'
        );
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(
            CategoriaResultado::class,
            'id_cc',
            'id_cc'
        );
    }
}