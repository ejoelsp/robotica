<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Incidencia extends Model
{
    use HasFactory;

    protected $table = 'resultados.incidencias';

    protected $fillable = [
        'categoria_id',
        'equipo_id',
        'reportado_por',
        'tipo',
        'descripcion',
        'evidencia_path',
        'codigo',
        'estado',
        'fecha_envio',
        'pdf_path',
        'jueces_snapshot',
        'equipo_snapshot',
        'integrantes_snapshot',
        'prototipo_nombre',
        'institucion',
    ];

    protected $casts = [
        'fecha_envio' => 'datetime',
        'jueces_snapshot' => 'array',
        'equipo_snapshot' => 'array',
        'integrantes_snapshot' => 'array',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function reportador()
    {
        return $this->belongsTo(User::class, 'reportado_por');
    }
}
