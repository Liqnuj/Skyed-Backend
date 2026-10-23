<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialContrasena extends Model
{
    protected $table = 'historial_contrasena';
    protected $fillable = ['id_u', 'contrasena_hash'];
}