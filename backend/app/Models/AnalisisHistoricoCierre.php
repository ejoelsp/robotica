<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalisisHistoricoCierre extends Model
{
    use HasFactory;

    protected $table = 'resultados.analisis_historico_cierres';

    protected $fillable = [
        'tipo_cierre',
        'temporada_id',
        'competencia_id',
        'anio',
        'estado',
        'fecha_inicio',
        'fecha_fin',
        'total_competencias',
        'total_categorias',
        'total_participantes',
        'total_equipos',
        'total_instituciones',
        'total_inscripciones_aprobadas',
        'tasa_crecimiento_participantes',
        'tasa_crecimiento_equipos',
        'tasa_crecimiento_instituciones',
        'metricas_json',
        'generado_por',
        'generado_at',
        'cerrado_por',
        'cerrado_at',
    ];

    protected $casts = [
        'anio' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'total_competencias' => 'integer',
        'total_categorias' => 'integer',
        'total_participantes' => 'integer',
        'total_equipos' => 'integer',
        'total_instituciones' => 'integer',
        'total_inscripciones_aprobadas' => 'integer',
        'tasa_crecimiento_participantes' => 'decimal:2',
        'tasa_crecimiento_equipos' => 'decimal:2',
        'tasa_crecimiento_instituciones' => 'decimal:2',
        'metricas_json' => 'array',
        'generado_at' => 'datetime',
        'cerrado_at' => 'datetime',
    ];

    public function temporada()
    {
        return $this->belongsTo(Temporada::class, 'temporada_id');
    }

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function cerradoPor()
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
