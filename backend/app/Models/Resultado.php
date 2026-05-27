<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resultado extends Model
{
    use HasFactory;

    protected $table = 'resultados.resultados';

    protected $fillable = [
        'ronda_id',
        'equipo_id',
        'juez_user_id',
        'competencia_id',
        'categoria_id',
        'inscripcion_id',
        'asignacion_juez_id',
        'intento_numero',
        'puntaje',
        'tiempo',
        'penalizaciones',
        'estado',
        'valor_principal',
        'valor_secundario',
        'payload_json',
        'observaciones',
        'version',
        'publicado_at',
        'publicado_por',
    ];

    protected $casts = [
        'puntaje' => 'decimal:2',
        'tiempo' => 'decimal:3',
        'penalizaciones' => 'integer',
        'valor_principal' => 'decimal:3',
        'valor_secundario' => 'decimal:3',
        'payload_json' => 'array',
        'version' => 'integer',
        'intento_numero' => 'integer',
        'publicado_at' => 'datetime',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function juez()
    {
        return $this->belongsTo(User::class, 'juez_user_id');
    }

    public function publicadoPor()
    {
        return $this->belongsTo(User::class, 'publicado_por');
    }

    public function asignacionJuez()
    {
        return $this->belongsTo(AsignacionJuezCategoria::class, 'asignacion_juez_id');
    }

    public function historial()
    {
        return $this->hasMany(ResultadoHist::class, 'resultado_id');
    }
}
