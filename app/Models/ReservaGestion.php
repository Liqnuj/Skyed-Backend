<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Información administrativa de una reserva (1 a 1 con Reserva).
 * Solo el Administrador Social la lee y la escribe.
 */
class ReservaGestion extends Model
{
    protected $table = 'reserva_gestion';

    protected $primaryKey = 'id_gestion';

    /**
     * Etapas de planificación. Confirmada, finalizada y cancelada NO están
     * aquí: siguen saliendo de reserva.estado_rese (pendiente, confirmada,
     * completada, cancelada) para no duplicar ni romper lo existente.
     */
    public const ETAPAS = [
        'nueva',
        'en_revision',
        'contactando_usuario',
        'pendiente_informacion',
        'en_planificacion',
        'pendiente_confirmacion',
        'reprogramada',
        'en_ejecucion',
    ];

    public const ETIQUETAS_ETAPA = [
        'nueva' => 'Nueva',
        'en_revision' => 'En revisión',
        'contactando_usuario' => 'Contactando usuario',
        'pendiente_informacion' => 'Pendiente de información',
        'en_planificacion' => 'En planificación',
        'pendiente_confirmacion' => 'Pendiente de confirmación',
        'reprogramada' => 'Reprogramada',
        'en_ejecucion' => 'En ejecución',
    ];

    public const MEDIOS_CONTACTO = ['telefono', 'whatsapp', 'correo', 'presencial', 'otro'];

    /** Solo estos campos se pueden asignar en masa (nunca datos del cliente). */
    protected $fillable = [
        'id_rese',
        'etapa_gestion',
        'id_responsable',
        'fecha_propuesta',
        'fecha_confirmada',
        'hora_inicio',
        'hora_fin',
        'fecha_limite_preparacion',
        'id_a',
        'asistentes_final',
        'distribucion_espacio',
        'necesidades_especiales',
        'requerimientos_adicionales',
        'decoracion',
        'logistica',
        'datos_tipo',
        'contactado',
        'ultimo_contacto_en',
        'medio_ultimo_contacto',
        'proximo_contacto_en',
        'compromisos',
    ];

    protected $casts = [
        'fecha_propuesta' => 'date',
        'fecha_confirmada' => 'date',
        'fecha_limite_preparacion' => 'date',
        'asistentes_final' => 'integer',
        'decoracion' => 'array',
        'logistica' => 'array',
        'datos_tipo' => 'array',
        'contactado' => 'boolean',
        'ultimo_contacto_en' => 'datetime',
        'proximo_contacto_en' => 'datetime',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'id_rese', 'id_rese');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsable', 'id_u');
    }

    public function ambiente(): BelongsTo
    {
        return $this->belongsTo(Ambiente::class, 'id_a', 'id_a');
    }
}