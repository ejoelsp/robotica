<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComiteOrganizador extends Model
{
    use HasFactory;

    protected $table = 'catalogo.comite_organizadores';

    protected $fillable = [
        'competencia_id',
        'nombres',
        'apellidos',
        'correo',
        'rol_comite',
        'foto',
        'orden',
        'estado',
    ];

    protected $casts = [
        'orden' => 'integer',
        'estado' => 'boolean',
    ];

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }
}
