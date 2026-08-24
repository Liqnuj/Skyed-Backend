<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventoRealizado extends Model
{
    protected $table = 'evento_realizado';

    protected $primaryKey = 'id_er';

    protected $fillable = [
        'nombre_er',
        'descripcion_er',
        'fecha_er',
        'id_tipo_eves',
        'id_a',
        'id_u',
        'estado_er',
    ];

    protected $casts = [
        'fecha_er' => 'date',
    ];

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(
            TipoEvento::class,
            'id_tipo_eves',
            'id_tipo_eves'
        );
    }

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(
            Ambiente::class,
            'id_a',
            'id_a'
        );
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(
            Reserva::class,
            'id_er',
            'id_er'
        );
    }
}