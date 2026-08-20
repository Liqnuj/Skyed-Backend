<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoEvento extends Model
{
    protected $table = 'tipo_evento';

    protected $primaryKey = 'id_tipo_eves';

    protected $fillable = [
        'nombre_tipo_eves',
        'descripcion_eves',
    ];

    public function eventos(): HasMany
    {
        return $this->hasMany(
            EventoRealizado::class,
            'id_tipo_eves',
            'id_tipo_eves'
        );
    }
}