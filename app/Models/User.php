<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuario';

    protected $primaryKey = 'id_u';

    public $timestamps = true;

    protected $fillable = [
        'tipo_documento_u',
        'documento_u',
        'nombre_u',
        'apellido_u',
        'rh_u',
        'telefono_u',
        'correo_u',
        'fecha_nacimiento_u',
        'codigo',
        'contrasena_u',
        'estado_u',
        'id_inv',
    ];

    protected $hidden = [
        'contrasena_u',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena_u;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'usuario_contexto_rol',
            'id_u',
            'id_rol'
        )->withPivot('contexto');
    }
    public function inscripciones(): HasMany
{
    return $this->hasMany(
        Inscripcion::class,
        'id_u',
        'id_u'
    );
    }
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('nombre_rol', $role)
            ->exists();
    }

    public function hasRoleInContext(string $role, string $contexto): bool
    {
        return $this->roles()
            ->where('nombre_rol', $role)
            ->wherePivot('contexto', $contexto)
            ->exists();
    }
}