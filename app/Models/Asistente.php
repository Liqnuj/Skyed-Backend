<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistente extends Model
{
    protected $table = 'asistente';

    protected $primaryKey = 'id_asistente';

    protected $fillable = [
        'id_admin',
        'id_usuario',
        'modulo',
        'permisos',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'permisos' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function administrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_admin', 'id_u');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_u');
    }
}
