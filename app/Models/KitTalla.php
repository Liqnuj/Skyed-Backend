<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KitTalla extends Model
{
    protected $table = 'kit_talla';

    protected $primaryKey = 'id_kt';

    protected $fillable = [
        'id_k',
        'talla_kt',
        'cantidad_kt',
    ];

    protected $casts = [
        'cantidad_kt' => 'integer',
    ];

    public function kit(): BelongsTo
    {
        return $this->belongsTo(Kit::class, 'id_k', 'id_k');
    }
}