<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificadoGenerado extends Model
{
    use HasFactory;

    protected $table = 'resultados.certificados_generados';

    protected $fillable = [
        'competencia_id',
        'categoria_id',
        'equipo_id',
        'inscripcion_id',
        'inscripcion_integrante_id',
        'plantilla_certificado_id',
        'tipo_certificado',
        'archivo_pdf',
        'datos_json',
        'fecha_generacion',
    ];

    protected $casts = [
        'datos_json' => 'array',
        'fecha_generacion' => 'datetime',
    ];

    public function plantilla()
    {
        return $this->belongsTo(PlantillaCertificado::class, 'plantilla_certificado_id');
    }

    public function integrante()
    {
        return $this->belongsTo(InscripcionIntegrante::class, 'inscripcion_integrante_id');
    }

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }
}
