<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Inscripcion;
use App\Models\User;

class InscripcionIntegrante extends Model
{
    use HasFactory;

    protected $table = 'vinculaciones.inscripcion_integrantes';

    protected $fillable = [
        'inscripcion_id',
        'nombre_completo',
        'user_id',
        'es_capitan',
    ];

    protected $casts = [
        'es_capitan' => 'boolean',
    ];

    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}