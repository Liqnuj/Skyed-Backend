<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AyudaArticulo extends Model
{
    protected $table = 'ayuda_articulo';

    protected $primaryKey = 'id_aya';

    public const BORRADOR = 'borrador';
    public const PUBLICADO = 'publicado';
    public const DESACTIVADO = 'desactivado';

    public const ESTADOS = [self::BORRADOR, self::PUBLICADO, self::DESACTIVADO];
    public const MODULOS = ['general', 'deportivo', 'social'];
    public const AUDIENCIAS = ['publico', 'usuario', 'admin'];

    protected $fillable = [
        'id_ayc',
        'titulo_aya',
        'slug_aya',
        'resumen_aya',
        'contenido_aya',
        'palabras_clave_aya',
        'modulo_aya',
        'audiencia_aya',
        'estado_aya',
        'destacado_aya',
        'faq_aya',
        'orden_aya',
        'contexto_aya',
        'visitas_aya',
        'id_autor',
    ];

    protected $casts = [
        'destacado_aya' => 'boolean',
        'faq_aya' => 'boolean',
        'orden_aya' => 'integer',
        'visitas_aya' => 'integer',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(AyudaCategoria::class, 'id_ayc', 'id_ayc');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_autor', 'id_u');
    }

    public function valoraciones(): HasMany
    {
        return $this->hasMany(AyudaValoracion::class, 'id_aya', 'id_aya');
    }

    /**
     * Lo que puede ver ESTA persona: solo publicados, de categorías activas,
     * y según su audiencia:
     *  - visitante: 'publico'
     *  - con sesión: 'publico' + 'usuario'
     *  - administrador: además las guías 'admin' de SU módulo (y las generales)
     */
    public function scopeVisiblesPara(Builder $query, ?User $usuario): Builder
    {
        $audiencias = $usuario ? ['publico', 'usuario'] : ['publico'];
        $modulosAdmin = $usuario ? $usuario->modulosQueAdministra() : [];

        return $query
            ->where('estado_aya', self::PUBLICADO)
            ->whereHas('categoria', fn (Builder $c) => $c->where('activa_ayc', true))
            ->where(function (Builder $q) use ($audiencias, $modulosAdmin) {
                $q->whereIn('audiencia_aya', $audiencias);

                if ($modulosAdmin !== []) {
                    $q->orWhere(function (Builder $a) use ($modulosAdmin) {
                        $a->where('audiencia_aya', 'admin')
                          ->whereIn('modulo_aya', array_merge(['general'], $modulosAdmin));
                    });
                }
            });
    }

    /** Con módulo ('social' o 'deportivo') incluye también los generales. */
    public function scopeDeModulo(Builder $query, ?string $modulo): Builder
    {
        if (in_array($modulo, ['deportivo', 'social'], true)) {
            $query->whereIn('modulo_aya', ['general', $modulo]);
        }

        return $query;
    }

    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden_aya')->orderBy('titulo_aya');
    }

    /** @return string[] */
    public function palabrasClave(): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $this->palabras_clave_aya))
        ));
    }
}