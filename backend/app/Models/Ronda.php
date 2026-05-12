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
        'estado',
        'fecha_hora',
    ];

    protected $casts = [
        'fecha_hora' => 'datetime',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function resultados()
    {
        return $this->hasMany(Resultado::class, 'ronda_id');
    }
}
