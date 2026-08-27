<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Role extends Model
{
    use HasFactory;
    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    protected $fillable = [
        'nombre_rol',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'usuario_contexto_rol',
            'id_rol',
            'id_u'
        )->withPivot('contexto');
    }
}