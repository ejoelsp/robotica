<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clasificacion extends Model
{
    use HasFactory;

    protected $table = 'resultados.clasificaciones';

    protected $fillable = [
        'competencia_id',
        'categoria_id',
        'equipo_id',
        'inscripcion_id',
        'ronda_id',
        'puntaje_total',
        'tiempo_total',
        'penal_total',
        'posicion',
        'estado_publicacion',
        'publicado_at',
        'publicado_por',
        'origen_version',
        'detalle_json',
    ];

    protected $casts = [
        'puntaje_total' => 'decimal:2',
        'tiempo_total' => 'decimal:3',
        'penal_total' => 'decimal:3',
        'posicion' => 'integer',
        'origen_version' => 'integer',
        'detalle_json' => 'array',
        'publicado_at' => 'datetime',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function ronda()
    {
        return $this->belongsTo(Ronda::class, 'ronda_id');
    }

    public function publicadoPor()
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }
}
