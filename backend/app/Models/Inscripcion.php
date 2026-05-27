<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Competencia;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Models\User;
use App\Models\InscripcionIntegrante;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'vinculaciones.inscripciones';

    protected $fillable = [
        'competencia_id',
        'categoria_id',
        'equipo_id',
        'user_id',
        'nombre_prototipo',
        'telefono_contacto',
        'codigo',
        'estado',
        'comprobante_pago',
        'fecha_subida_comprobante',
        'estado_comprobante',
        'motivo_rechazo',
        'observacion_rechazo',
        'fecha_revision_comprobante',
        'revisado_por',
    ];

    protected $casts = [
        'fecha_subida_comprobante' => 'datetime',
        'fecha_revision_comprobante' => 'datetime',
    ];

    public function scopeAprobadas(Builder $query): Builder
    {
        return $query
            ->where('estado', 'confirmado')
            ->where('estado_comprobante', 'aprobado');
    }

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

    public function usuarioRegistro()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function integrantes()
    {
        return $this->hasMany(InscripcionIntegrante::class, 'inscripcion_id');
    }
}
