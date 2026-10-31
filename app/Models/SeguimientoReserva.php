<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeguimientoReserva extends Model
{
    protected $table = 'seguimiento_reserva';

    protected $primaryKey = 'id_seguimiento';

    /** Clases de registro del historial. */
    public const TIPOS = [
        'creacion',
        'cambio_estado',
        'cambio_etapa',
        'cambio_fecha',
        'contacto',
        'edicion',
        'servicio',
        'nota_interna',
        'actualizacion',
    ];

    protected $fillable = [
        'id_rese',
        'fecha_actualizacion',
        'comentario',
        'id_admin',
        'tipo_evento',
        'estado_anterior',
        'estado_nuevo',
        'visible_usuario',
    ];

    protected $casts = [
        'fecha_actualizacion' => 'datetime',
        'visible_usuario' => 'boolean',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(
            Reserva::class,
            'id_rese',
            'id_rese'
        );
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_admin',
            'id_u'
        );
    }

    /** Solo lo que el cliente puede ver (nunca las notas internas). */
    public function scopeVisiblesParaUsuario(Builder $query): Builder
    {
        return $query->where('visible_usuario', true);
    }
}