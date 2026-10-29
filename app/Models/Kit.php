<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kit extends Model
{
    protected $table = 'kit';

    protected $primaryKey = 'id_k';

    protected $fillable = [
        'nombre_k',
        'stock_k',
        'fecha_entrega_k',
        'lugar_entrega_k',
        'contenido_k',
        'talla_camiseta_k',
        'numero_dorsal_k',
        'precio_k',
        'imagen_k',
    ];

    // Expone la URL pública de la imagen en el JSON del kit (GET /kits).
    protected $appends = ['imagen_url'];

    protected $casts = [
        'fecha_entrega_k' => 'date',
        'stock_k' => 'integer',
        'numero_dorsal_k' => 'integer',
        'precio_k' => 'decimal:2',
    ];

    /**
     * URL pública de la imagen del kit (null si no tiene).
     * imagen_k guarda la ruta relativa del disco público, igual que imagen_e en los eventos.
     */
    public function getImagenUrlAttribute(): ?string
    {
        $ruta = $this->imagen_k;

        if (!$ruta) {
            return null;
        }

        if (preg_match('#^https?://#i', $ruta)) {
            return $ruta;
        }

        return asset('storage/' . ltrim($ruta, '/'));
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(
            EventoDeportivo::class,
            'id_k',
            'id_k'
        );
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(
            EntregaKit::class,
            'id_k',
            'id_k'
        );
    }
}