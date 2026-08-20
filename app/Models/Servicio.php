<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Servicio extends Model
{
    protected $table = 'servicio';

    protected $primaryKey = 'id_s';

    protected $fillable = [
        'nombre_s',
        'descripcion_s',
    ];

    public function ambientes(): BelongsToMany
    {
        return $this->belongsToMany(
            Ambiente::class,
            'ambiente_servicio',
            'id_s',
            'id_a',
            'id_s',
            'id_a'
        );
    }
}