<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SorteoDetalle extends Model
{
    protected $table = 'catalogo.sorteo_detalles';

    protected $fillable = [
        'sorteo_id',
        'inscripcion_id',
        'orden',
        'grupo',
        'lado',
        'estado',
    ];

    public function sorteo()
    {
        return $this->belongsTo(Sorteo::class, 'sorteo_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }
}
