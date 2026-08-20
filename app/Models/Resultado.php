<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;


class Resultado extends Model
{
    protected $table = 'resultado';

    protected $primaryKey = 'id_r';

    protected $fillable = [
        'tiempo_final_r',
        'posicion_general_r',
        'estado_r',
        'id_i',
    ];

    protected $casts = [
        'tiempo_final_r' => 'string',
        'posicion_general_r' => 'integer',
    ];

    public function inscripcion(): BelongsTo
    {
        return $this->belongsTo(
            Inscripcion::class,
            'id_i',
            'id_i'
        );
    }

    public function categoriaResultado(): HasOne
    {
        return $this->hasOne(
            CategoriaResultado::class,
            'id_r',
            'id_r'
        );
    }
    public function premio(): HasOne
{
    return $this->hasOne(
        Premio::class,
        'id_r',
        'id_r'
    );
}
}