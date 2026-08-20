<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopiaSeguridad extends Model
{
    protected $table = 'copia_seguridad';

    protected $primaryKey = 'id_cs';

    protected $fillable = [
        'nombre_tabla_cs',
        'fecha_cs',
        'datos_json_cs',
    ];

    protected $casts = [
        'fecha_cs' => 'datetime',
        'datos_json_cs' => 'array',
    ];
}