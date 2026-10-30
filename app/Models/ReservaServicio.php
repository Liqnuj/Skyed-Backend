<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaServicio extends Model
{
    protected $table = 'reserva_servicio';

    protected $primaryKey = 'id_rs';

    public const ORIGENES = ['cliente', 'admin'];

    public const ESTADOS = ['pendiente', 'confirmado', 'no_disponible'];

    protected $fillable = [
        'id_rese',
        'id_s',
        'nombre_servicio',
        'cantidad',
        'origen',
        'estado',
        'id_responsable',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'id_rese', 'id_rese');
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'id_s', 'id_s');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsable', 'id_u');
    }
}