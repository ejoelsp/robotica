<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sorteo extends Model
{
    protected $table = 'catalogo.sorteos';

    protected $fillable = [
        'ronda_id',
        'tipo_sorteo',
        'estado',
        'reglas_json',
    ];

    protected $casts = [
        'reglas_json' => 'array',
    ];

    public function ronda()
    {
        return $this->belongsTo(Ronda::class, 'ronda_id');
    }

    public function detalles()
    {
        return $this->hasMany(SorteoDetalle::class, 'sorteo_id');
    }
}
