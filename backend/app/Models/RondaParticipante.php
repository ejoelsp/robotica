<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RondaParticipante extends Model
{
    use HasFactory;

    protected $table = 'resultados.ronda_participantes';

    protected $fillable = [
        'ronda_id',
        'inscripcion_id',
        'equipo_id',
        'estado',
        'origen_clasificacion_id',
    ];

    public function ronda()
    {
        return $this->belongsTo(Ronda::class, 'ronda_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function origenClasificacion()
    {
        return $this->belongsTo(Clasificacion::class, 'origen_clasificacion_id');
    }
}
