<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PqrSeguimiento extends Model
{
    protected $table = 'pqr_seguimientos';

    protected $primaryKey = 'id_seguimiento';

    protected $fillable = [
        'id_pqr',
        'id_admin',
        'estado_anterior',
        'estado_nuevo',
        'tipo_evento',
        'comentario_publico',
        'nota_interna',
    ];

    public function pqr(): BelongsTo
    {
        return $this->belongsTo(
            Pqr::class,
            'id_pqr',
            'id_pqr'
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
}
