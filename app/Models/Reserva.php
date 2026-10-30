<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reserva extends Model
{
    protected $table = 'reserva';

    protected $primaryKey = 'id_rese';

    protected $fillable = [
        'nombre_rese',
        'fecha_evento_rese',
        'fecha_hasta_rese',
        'hora_aprox_rese',
        'duracion_horas_rese',
        'invitados_rese',
        'presupuesto_rese',
        'ubicacion_rese',
        'Observaciones_rese',
        'total_rese',
        'estado_rese',
        'creado_en_rese',
        'id_u',
        'id_tipo_eves',
        'id_er',
    ];

    protected $casts = [
        'fecha_evento_rese' => 'date',
        'fecha_hasta_rese' => 'date',
        'duracion_horas_rese' => 'integer',
        'presupuesto_rese' => 'decimal:2',
        'total_rese' => 'decimal:2',
        'creado_en_rese' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }

    public function evento(): BelongsTo
    {
        return $this->belongsTo(
            EventoRealizado::class,
            'id_er',
            'id_er'
        );
    }

    public function tipoEvento(): BelongsTo
    {
        return $this->belongsTo(
            TipoEvento::class,
            'id_tipo_eves',
            'id_tipo_eves'
        );
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(
            SeguimientoReserva::class,
            'id_rese',
            'id_rese'
        );
    }

    /** Ficha administrativa (solo la ve el Administrador Social). */
    public function gestion(): HasOne
    {
        return $this->hasOne(
            ReservaGestion::class,
            'id_rese',
            'id_rese'
        );
    }

    /** Servicios de la reserva (los del cliente y los que agrega el admin). */
    public function servicios(): HasMany
    {
        return $this->hasMany(
            ReservaServicio::class,
            'id_rese',
            'id_rese'
        );
    }

    /**
     * Devuelve la ficha de gestión; si la reserva todavía no la tiene
     * (p. ej. se creó por otro camino) la crea vacía.
     */
    public function asegurarGestion(): ReservaGestion
    {
        return $this->gestion
            ?? $this->gestion()->create([
                'etapa_gestion' => 'nueva',
                'contactado' => false,
            ]);
    }
}