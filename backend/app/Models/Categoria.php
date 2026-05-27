<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Clasificacion;
use App\Models\Competencia;
use App\Models\ConfigCalificacion;
use App\Models\Inscripcion;
use App\Models\AsignacionJuezCategoria;
use App\Models\Resultado;
use App\Models\Ronda;

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'catalogo.categorias';

    protected $fillable = [
        'competencia_id',
        'nombre',
        'nombre_key',
        'costo_inscripcion',
        'max_integrantes',
        'estado',
        'estado_resultados',
        'resultados_finalizados_at',
        'reglamento',
        'imagen',
    ];

    protected $casts = [
        'estado' => 'boolean',
        'costo_inscripcion' => 'decimal:2',
        'max_integrantes' => 'integer',
        'resultados_finalizados_at' => 'datetime',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'categoria_id');
    }

    public function configCalificacion()
    {
        return $this->hasOne(ConfigCalificacion::class, 'categoria_id');
    }

    public function clasificaciones()
    {
        return $this->hasMany(Clasificacion::class, 'categoria_id');
    }

    public function rondas()
    {
        return $this->hasMany(Ronda::class, 'categoria_id');
    }

    public function asignacionesJuez()
    {
        return $this->hasMany(AsignacionJuezCategoria::class, 'categoria_id');
    }

    public function resultados()
    {
        return $this->hasMany(Resultado::class, 'categoria_id');
    }
}
