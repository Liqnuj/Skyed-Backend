<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventoDeportivo extends Model
{
    protected $table = 'eventoDeportivo';

    protected $primaryKey = 'id_e';

    public $timestamps = true;

    protected $fillable = [
        'nombre_e',
        'categoria_e',
        'fecha_e',
        'hora_e',
        'ubicacion_e',
        'descripcion_e',
        'requisitos_e',
        'imagen_e',
        'cupos_disponibles_e',
        'estado_e',
        'creado_e',
        'id_k',
        'id_u',
    ];

    protected $casts = [
        'fecha_e' => 'date',
        'creado_e' => 'datetime',
        'cupos_disponibles_e' => 'integer',
        'id_k' => 'integer',
        'id_u' => 'integer',
    ];

    /**
     * Kit asociado al evento.
     */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(
            Kit::class,
            'id_k',
            'id_k'
        );
    }

    /**
     * Usuario que creó el evento.
     */
    public function creador(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }

    /**
     * Categorías de competencia del evento.
     */
    public function categorias(): HasMany
    {
        return $this->hasMany(
            CategoriaCompetencia::class,
            'id_e',
            'id_e'
        );
    }

    /**
     * Patrocinadores del evento.
     */
    public function patrocinadores(): BelongsToMany
    {
        return $this->belongsToMany(
            Patrocinador::class,
            'evento_patrocinador',
            'evento_id_e',
            'patrocinador_id_p',
            'id_e',
            'id_p'
        )->withPivot('detalle');
    }

    /**
     * Inscripciones del evento.
     */
    public function inscripciones(): HasMany
    {
        return $this->hasMany(
            Inscripcion::class,
            'id_e',
            'id_e'
        );
    }
}