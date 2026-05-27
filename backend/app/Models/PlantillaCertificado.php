<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlantillaCertificado extends Model
{
    use HasFactory;

    public const TIPOS = [
        'participacion',
        'primer_lugar',
        'segundo_lugar',
        'tercer_lugar',
    ];

    protected $table = 'resultados.plantillas_certificados';

    protected $fillable = [
        'competencia_id',
        'anio',
        'tipo_certificado',
        'archivo_plantilla',
        'configuracion_textos',
        'activo',
        'creado_por',
    ];

    protected $casts = [
        'anio' => 'integer',
        'configuracion_textos' => 'array',
        'activo' => 'boolean',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function creadoPor()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }
}
