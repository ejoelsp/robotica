<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClasificacionPublicacionHist extends Model
{
    use HasFactory;

    protected $table = 'resultados.clasificaciones_publicaciones_hist';

    protected $fillable = [
        'competencia_id',
        'categoria_id',
        'ronda_id',
        'accion',
        'estado_anterior',
        'estado_nuevo',
        'clasificaciones_count',
        'ejecutado_por',
        'ejecutado_at',
        'detalle_json',
    ];

    protected $casts = [
        'clasificaciones_count' => 'integer',
        'ejecutado_at' => 'datetime',
        'detalle_json' => 'array',
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

    public function ejecutadoPor()
    {
        return $this->belongsTo(User::class, 'ejecutado_por');
    }
}
