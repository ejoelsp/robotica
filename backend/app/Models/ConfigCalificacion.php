<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigCalificacion extends Model
{
    use HasFactory;

    protected $table = 'catalogo.config_calificacion';

    protected $fillable = [
        'categoria_id',
        'mecanismo_calificacion_id',
        'unidad_resultado',
        'orden_ranking',
        'requiere_aprobacion_admin',
        'visible_publico_en_vivo',
        'permite_edicion_juez',
        'campos_json',
        'reglas_json',
    ];

    protected $casts = [
        'requiere_aprobacion_admin' => 'boolean',
        'visible_publico_en_vivo' => 'boolean',
        'permite_edicion_juez' => 'boolean',
        'campos_json' => 'array',
        'reglas_json' => 'array',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function mecanismo()
    {
        return $this->belongsTo(MecanismoCalificacion::class, 'mecanismo_calificacion_id');
    }
}
