<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    use HasFactory;

    protected $table = 'usuario';

    protected $primaryKey = 'id_u';

    public $timestamps = true;

    protected $fillable = [
        'tipo_documento_u',
        'documento_u',
        'nombre_u',
        'apellido_u',
        'rh_u',
        'ciudad_u',
        'telefono_u',
        'correo_u',
        'foto_u',
        'fecha_nacimiento_u',
        'contrasena_u',
        'estado_u',
        'acepta_terminos_u',
        'terminos_aceptados_en',
        'acepta_privacidad_u',
        'privacidad_aceptada_en',
    ];

    protected $hidden = [
        'contrasena_u',
        'codigo',
    ];

    /** Dirección pública de la foto de perfil (null si no tiene). Se agrega con ->append('foto_url'). */
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_u ? asset('storage/' . $this->foto_u) : null;
    }

    public function getAuthPassword()
    {
        return $this->contrasena_u;
    }

    public function historialContrasenas()
    {
        return $this->hasMany(\App\Models\HistorialContrasena::class, 'id_u', 'id_u');
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

    /**
     * Roles de usuario normal y el módulo de cada uno. El registro es uno solo
     * para toda la plataforma, así que todo usuario normal es participante en
     * Deportivo y cliente en Social.
     */
    public const ROLES_BASE = ['participante' => 'deportivo', 'cliente' => 'social'];

    /** Le da los dos roles base (participante y cliente) que le falten. */
    public function asegurarRolesBase(): void
    {
        foreach (self::ROLES_BASE as $nombre => $contexto) {
            $rol = Role::firstOrCreate(['nombre_rol' => $nombre]);
            $yaLoTiene = $this->roles()
                ->where('rol.id_rol', $rol->id_rol)
                ->wherePivot('contexto', $contexto)
                ->exists();

            if (! $yaLoTiene) {
                $this->roles()->attach($rol->id_rol, ['contexto' => $contexto]);
            }
        }
    }

    public function inscripciones(): HasMany
{
    return $this->hasMany(
        Inscripcion::class,
        'id_u',
        'id_u'
    );
    }

    public function invitado(): BelongsTo
    {
        return $this->belongsTo(
            Invitado::class,
            'id_inv',
            'id_inv'
        );
    }
    public function hasRole(string $role): bool
    {
        return $this->roles()
            ->where('nombre_rol', $role)
            ->exists();
    }

    /**
     * Nombres de rol (en minúsculas) que se consideran "super administrador".
     * Ajusta esta lista si en tu tabla `rol` se llama de otra forma.
     * Además, quien tenga a la vez adminSocial y adminDeportivo también
     * cuenta como super administrador.
     */
    public const ROLES_SUPER = ['superadmin', 'super_admin', 'super admin', 'admin', 'administrador'];

    /** Es super administrador: rol propio, o administra los dos módulos a la vez. */
    public function esSuperAdmin(): bool
    {
        $roles = $this->roles()->pluck('nombre_rol')->map(fn ($r) => mb_strtolower((string) $r));

        return $roles->intersect(self::ROLES_SUPER)->isNotEmpty()
            || ($roles->contains('adminsocial') && $roles->contains('admindeportivo'));
    }

    /**
     * ¿Es administrador de algún tipo? (adminSocial, adminDeportivo o
     * super administrador). Se usa para que un admin no pueda cambiarle
     * los roles a otro admin.
     */
    public function esAdministrador(): bool
    {
        return $this->roles()->pluck('nombre_rol')
            ->map(fn ($r) => mb_strtolower((string) $r))
            ->contains(fn ($r) => str_starts_with($r, 'admin') || in_array($r, self::ROLES_SUPER, true));
    }

    /** ¿Puede este usuario asignar el rol indicado al crear una cuenta? */
    public function puedeGestionarRol(string $nombreRol): bool
    {
        if ($this->esSuperAdmin()) {
            return true;
        }

        $r = mb_strtolower($nombreRol);
        return !str_starts_with($r, 'admin') && !in_array($r, self::ROLES_SUPER, true);
    }

    /**
     * Usuarios que `$viewer` puede ver y administrar:
     * - el super administrador ve a todos;
     * - un admin de módulo NO ve al super administrador ni al admin del
     *   otro módulo (el admin social no ve al deportivo y viceversa);
     * - cada quien siempre se ve a sí mismo.
     */
    public function scopeVisiblesPara(Builder $query, User $viewer): Builder
    {
        if ($viewer->esSuperAdmin()) {
            return $query;
        }

        $otroRol = $viewer->hasRole('adminSocial') ? 'adminDeportivo'
            : ($viewer->hasRole('adminDeportivo') ? 'adminSocial' : null);

        return $query->where(function (Builder $q) use ($viewer, $otroRol) {
            $q->where('id_u', $viewer->id_u)
                ->orWhere(function (Builder $otros) use ($otroRol) {
                    // Sin super administrador...
                    $otros->whereDoesntHave('roles', function ($r) {
                        $r->whereIn(DB::raw('LOWER(nombre_rol)'), self::ROLES_SUPER);
                    })
                    // ...ni quien administra los dos módulos...
                    ->where(function (Builder $no) {
                        $no->whereDoesntHave('roles', fn ($r) => $r->where('nombre_rol', 'adminSocial'))
                           ->orWhereDoesntHave('roles', fn ($r) => $r->where('nombre_rol', 'adminDeportivo'));
                    });

                    // ...ni al administrador del otro módulo.
                    if ($otroRol) {
                        $otros->whereDoesntHave('roles', fn ($r) => $r->where('nombre_rol', $otroRol));
                    }
                });
        });
    }

    /**
     * Usuarios que se listan en el panel de un módulo ('social' o 'deportivo'):
     *  - nunca el administrador del otro módulo (ni quien administra los dos);
     *  - los usuarios normales (participante, cliente o sin rol) en los dos,
     *    porque el registro es uno solo y pueden inscribirse y reservar;
     *  - el personal (admin o asistente) solo en el panel de su módulo.
     * Es la misma regla que usa la tabla de usuarios del frontend.
     */
    public function scopeDelModulo(Builder $query, string $modulo): Builder
    {
        $adminDelOtro = $modulo === 'social' ? 'adminDeportivo' : 'adminSocial';

        return $query
            ->whereDoesntHave('roles', fn ($r) => $r->where('nombre_rol', $adminDelOtro))
            ->where(function (Builder $q) use ($modulo) {
                $q->whereDoesntHave('roles')
                    ->orWhereHas('roles', fn ($r) => $r->whereIn('nombre_rol', ['participante', 'cliente']))
                    ->orWhereHas('roles', fn ($r) => $r->whereIn(
                        DB::raw('LOWER(usuario_contexto_rol.contexto)'),
                        [$modulo, 'general']
                    ));
            });
    }

    public function hasRoleInContext(string $role, string $contexto): bool
    {
        return $this->roles()
            ->where('nombre_rol', $role)
            ->wherePivot('contexto', $contexto)
            ->exists();
    }
    

        /**
     * Módulos que administra este usuario ('social', 'deportivo'). El super
     * administrador administra ambos. Lo usa el Centro de Ayuda para decidir
     * qué guías de administración puede ver.
     *
     * @return string[]
     */
    public function modulosQueAdministra(): array
    {
        $roles = $this->roles()->pluck('nombre_rol')
            ->map(fn ($r) => mb_strtolower((string) $r));

        if ($roles->intersect(self::ROLES_SUPER)->isNotEmpty()) {
            return ['social', 'deportivo'];
        }

        $modulos = [];

        if ($roles->contains('adminsocial')) {
            $modulos[] = 'social';
        }
        if ($roles->contains('admindeportivo')) {
            $modulos[] = 'deportivo';
        }

        return $modulos;
    }

    

        /**
     * Qué contenido del Centro de Ayuda puede gestionar. Cada admin gestiona su
     * módulo; lo "general" solo quien administra ambos (super administrador).
     *
     * @return string[]
     */
    public function modulosGestionablesAyuda(): array
    {
        $modulos = $this->modulosQueAdministra();

        if (count($modulos) === 2) {
            $modulos[] = 'general';
        }

        return $modulos;
    }

    
    // se agrega validacion para notificaciones
public function notificaciones(): HasMany
{
    return $this->hasMany(
        Notificacion::class,
        'user_id'
    );
}

}