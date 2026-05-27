<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActaReporte extends Model
{
    use HasFactory;

    protected $table = 'resultados.actas_reportes';

    protected $fillable = [
        'competencia_id',
        'categoria_id',
        'ronda_id',
        'tipo_reporte',
        'estado',
        'archivo_generado_path',
        'archivo_firmado_path',
        'generado_por',
        'archivo_firmado_subido_por',
        'generado_at',
        'archivo_firmado_subido_at',
        'snapshot_json',
        'observaciones',
    ];

    protected $casts = [
        'generado_at' => 'datetime',
        'archivo_firmado_subido_at' => 'datetime',
        'snapshot_json' => 'array',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function ronda()
    {
        return $this->belongsTo(Ronda::class, 'ronda_id');
    }

    public function generadoPor()
    {
        return $this->belongsTo(User::class, 'generado_por');
    }

    public function archivoFirmadoSubidoPor()
    {
        return $this->belongsTo(User::class, 'archivo_firmado_subido_por');
    }
}
