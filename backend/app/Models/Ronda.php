<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ronda extends Model
{
    use HasFactory;

    protected $table = 'catalogo.rondas';

    protected $fillable = [
        'categoria_id',
        'nombre',
        'tipo',
        'orden',
        'cantidad_intentos',
        'intentos_consecutivos',
        'clasifican_cantidad',
        'criterio_clasificacion',
        'ronda_origen_id',
        'es_final',
        'estado',
    ];

    protected $casts = [
        'orden' => 'integer',
        'cantidad_intentos' => 'integer',
        'intentos_consecutivos' => 'boolean',
        'clasifican_cantidad' => 'integer',
        'es_final' => 'boolean',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function resultados()
    {
        return $this->hasMany(Resultado::class, 'ronda_id');
    }

    public function participantes()
    {
        return $this->hasMany(RondaParticipante::class, 'ronda_id');
    }

    public function origen()
    {
        return $this->belongsTo(Ronda::class, 'ronda_origen_id');
    }
}
