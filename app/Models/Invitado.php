<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitado extends Model
{
    protected $table = 'invitados';

    protected $primaryKey = 'id_inv';

    protected $fillable = [
        'tipo_documento',
        'documento_inv',
        'nombre_inv',
        'apellido_inv',
        'rh_inv',
        'telefono_inv',
        'fecha_nacimiento_inv',
        'correo_inv',
    ];

    protected $casts = [
        'fecha_nacimiento_inv' => 'date',
    ];

    /**
     * Usuarios vinculados a este invitado (usuario.id_inv -> invitados.id_inv).
     */
    public function usuarios(): HasMany
    {
        return $this->hasMany(
            User::class,
            'id_inv',
            'id_inv'
        );
    }
}
