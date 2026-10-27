<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pqr extends Model
{
    protected $table = 'pqr';

    protected $primaryKey = 'id_pqr';

    /**
     * Fases por las que pasa una PQR, en orden.
     * "abierto" se muestra al usuario como "Enviado".
     */
    public const ESTADOS = [
        'abierto',
        'en_revision',
        'en_proceso',
        'resuelto',
        'cerrado',
    ];

    public const ETIQUETAS_ESTADO = [
        'abierto' => 'Enviado',
        'en_revision' => 'En revisión',
        'en_proceso' => 'En proceso',
        'resuelto' => 'Resuelto',
        'cerrado' => 'Cerrada',
    ];

    public const TIPOS = [
        'peticion',
        'queja',
        'reclamo',
        'sugerencia',
        'felicitacion',
    ];

    /** Estados en los que la PQR ya no está "en curso". */
    public const ESTADOS_FINALES = ['resuelto', 'cerrado'];

   
    
    /**
     * Transiciones válidas de una PQR. El backend nunca acepta
     * saltarse una fase ni volver atrás.
     */
    public const TRANSICIONES = [
        'abierto' => ['en_revision'],
        'en_revision' => ['en_proceso'],
        'en_proceso' => ['resuelto'],
        'resuelto' => ['cerrado'],
        'cerrado' => [],
    ];

    

    /**
     * Plazo de respuesta en días hábiles por tipo. Son los mismos que
     * se le muestran al usuario en la página de PQR. La felicitación
     * no tiene plazo.
     */
    public const PLAZOS_DIAS = [
        'peticion' => 3,
        'queja' => 5,
        'reclamo' => 5,
        'sugerencia' => 2,
    ];

    protected $fillable = [
        'tipo_pqr',
        'asunto_pqr',
        'mensaje_pqr',
        'estado_pqr',
        'respuesta_pqr',
        'respondido_en_pqr',
        'creado_en_pqr',
        'id_u',
    ];

    protected $casts = [
        'respondido_en_pqr' => 'datetime',
        'creado_en_pqr' => 'datetime',
    ];

    protected $appends = [
        'radicado',
        'estado_etiqueta',
        'fecha_limite',
        'vencida',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'id_u',
            'id_u'
        );
    }

    public function seguimientos(): HasMany
    {
        return $this->hasMany(
            PqrSeguimiento::class,
            'id_pqr',
            'id_pqr'
        )->orderBy('created_at')->orderBy('id_seguimiento');
    }

    /** Número de radicado que ve el usuario, p. ej. SS-PQR-12. */
    protected function radicado(): Attribute
    {
        return Attribute::get(fn () => 'SS-PQR-' . $this->id_pqr);
    }

    protected function estadoEtiqueta(): Attribute
    {
        return Attribute::get(
            fn () => self::ETIQUETAS_ESTADO[$this->estado_pqr]
                ?? $this->estado_pqr
        );
    }

    /**
     * Fecha límite de respuesta (días hábiles de lunes a viernes;
     * no descuenta festivos). Null para los tipos sin plazo.
     */
    protected function fechaLimite(): Attribute
    {
        return Attribute::get(
            fn () => $this->calcularFechaLimite()?->toIso8601String()
        );
    }

    /** Pasó el plazo y la PQR sigue sin resolverse. */
    protected function vencida(): Attribute
    {
        return Attribute::get(function () {
            $limite = $this->calcularFechaLimite();

            return $limite !== null
                && !in_array($this->estado_pqr, self::ESTADOS_FINALES, true)
                && now()->greaterThan($limite);
        });
    }

    private function calcularFechaLimite(): ?Carbon
    {
        $dias = self::PLAZOS_DIAS[$this->tipo_pqr] ?? null;
        $base = $this->creado_en_pqr ?? $this->created_at;

        if ($dias === null || $base === null) {
            return null;
        }

        return $base->copy()->addWeekdays($dias)->endOfDay();
    }
}