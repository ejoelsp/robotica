<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Temporada extends Model
{
    use HasFactory;

    protected $table = 'catalogo.temporadas';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'anio',
    ];

    protected $casts = [
        'anio' => 'integer',
    ];

    public function competencias()
    {
        return $this->hasMany(Competencia::class, 'temporada_id');
    }
}
