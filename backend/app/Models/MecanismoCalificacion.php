<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MecanismoCalificacion extends Model
{
    use HasFactory;

    protected $table = 'catalogo.mecanismos_calificacion';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function configuraciones()
    {
        return $this->hasMany(ConfigCalificacion::class, 'mecanismo_calificacion_id');
    }
}
