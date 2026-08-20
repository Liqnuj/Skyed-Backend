<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Patrocinador extends Model
{
    protected $table = 'patrocinador';

    protected $primaryKey = 'id_p';

    protected $fillable = [
        'nombre_p',
        'logo_p',
        'telefono_p',
        'correo_p',
        'pagina_web_p',
        'aporte_p',
        'estado_p',
    ];

    public function eventos(): BelongsToMany
    {
        return $this->belongsToMany(
            EventoDeportivo::class,
            'evento_patrocinador',
            'patrocinador_id_p',
            'evento_id_e',
            'id_p',
            'id_e'
        )->withPivot('detalle');
    }
}