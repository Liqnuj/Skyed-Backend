<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AyudaValoracion extends Model
{
    protected $table = 'ayuda_valoracion';

    protected $primaryKey = 'id_ayv';

    protected $fillable = ['id_aya', 'id_u', 'util_ayv', 'comentario_ayv'];

    protected $casts = ['util_ayv' => 'boolean'];

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(AyudaArticulo::class, 'id_aya', 'id_aya');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_u', 'id_u');
    }
}