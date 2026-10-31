<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonio extends Model
{
    protected $table = 'testimonio';

    protected $primaryKey = 'id_tes';

    public const PUBLICADO = 'publicado';
    public const OCULTO = 'oculto';

    protected $fillable = [
        'id_u',
        'id_rese',
        'calificacion_tes',
        'comentario_tes',
        'estado_tes',
    ];

    protected $casts = [
        'calificacion_tes' => 'integer',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_u', 'id_u');
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'id_rese', 'id_rese');
    }

    public function scopePublicados(Builder $query): Builder
    {
        return $query->where('estado_tes', self::PUBLICADO);
    }

    /**
     * Lo que ve cualquier visitante de la página. Nunca incluye correo,
     * documento ni apellido completo: solo "María R.".
     */
    public function paraPublico(): array
    {
        $usuario = $this->usuario;
        $reserva = $this->reserva;

        $primerNombre = trim(explode(' ', trim((string) ($usuario->nombre_u ?? '')))[0] ?? '');
        if ($primerNombre === '') {
            $primerNombre = 'Cliente';
        }

        $apellido = trim((string) ($usuario->apellido_u ?? ''));
        $inicialApellido = $apellido !== '' ? mb_strtoupper(mb_substr($apellido, 0, 1)) : '';

        $tipo = trim((string) ($reserva?->tipoEvento?->nombre_tipo_eves ?? ''));
        $ciudad = trim((string) ($usuario->ciudad_u ?? ''));

        $nombreEvento = trim((string) ($reserva?->nombre_rese ?? ''));
        if ($nombreEvento === '') {
            $nombreEvento = trim((string) ($reserva?->evento?->nombre_er ?? ''));
        }
        if ($nombreEvento === '') {
            $nombreEvento = $tipo !== '' ? $tipo : 'Evento social';
        }

        return [
            'id_tes' => $this->id_tes,
            'nombre' => $inicialApellido !== '' ? "{$primerNombre} {$inicialApellido}." : $primerNombre,
            'iniciales' => mb_strtoupper(mb_substr($primerNombre, 0, 1)) . $inicialApellido,
            'rol' => implode(' · ', array_filter([$tipo, $ciudad])),
            'evento' => $nombreEvento,
            'calificacion' => $this->calificacion_tes,
            'comentario' => $this->comentario_tes,
            'fecha' => $this->created_at?->toIso8601String(),
        ];
    }

    /** Lo que ve la persona que la escribió (para editarla o borrarla). */
    public function paraDueno(): array
    {
        return [
            'id_tes' => $this->id_tes,
            'id_rese' => $this->id_rese,
            'calificacion' => $this->calificacion_tes,
            'comentario' => $this->comentario_tes,
            'estado' => $this->estado_tes,
            'fecha' => $this->created_at?->toIso8601String(),
        ];
    }
}
