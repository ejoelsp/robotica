<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Clasificacion;
use App\Models\User;
use App\Models\Inscripcion;

class Equipo extends Model
{
    use HasFactory;

    protected $table = 'catalogo.equipos';

    protected $fillable = [
        'nombre',
        'institucion',
        'capitan_user_id',
    ];

    public function capitan()
    {
        return $this->belongsTo(User::class, 'capitan_user_id');
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'equipo_id');
    }

    public function clasificaciones()
    {
        return $this->hasMany(Clasificacion::class, 'equipo_id');
    }
}
