<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Clasificacion;
use App\Models\Categoria;
use App\Models\ComiteOrganizador;
use App\Models\Inscripcion;
use App\Models\Temporada;

class Competencia extends Model
{
    use HasFactory;

    protected $table = 'catalogo.competencias';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'nombre',
        'temporada_id',
        'descripcion',
        'fecha_inicio',
        'fecha_fin',
        'enlace_evento',
        'tipo_competencia',
        'imagen_url',
        'logo_url',
        'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'estado' => 'boolean',
    ];

    public function categorias()
    {
        return $this->hasMany(Categoria::class, 'competencia_id');
    }

    public function temporada()
    {
        return $this->belongsTo(Temporada::class, 'temporada_id');
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class, 'competencia_id');
    }

    public function clasificaciones()
    {
        return $this->hasMany(Clasificacion::class, 'competencia_id');
    }

    public function comiteOrganizadores()
    {
        return $this->hasMany(ComiteOrganizador::class, 'competencia_id');
    }
}
