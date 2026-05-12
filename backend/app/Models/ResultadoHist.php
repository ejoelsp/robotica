<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResultadoHist extends Model
{
    use HasFactory;

    protected $table = 'resultados.resultados_hist';

    protected $fillable = [
        'resultado_id',
        'version',
        'version_anterior',
        'version_nueva',
        'puntaje_old',
        'puntaje_new',
        'tiempo_old',
        'tiempo_new',
        'penal_old',
        'penal_new',
        'estado_old',
        'estado_new',
        'payload_old',
        'payload_new',
        'motivo_cambio',
        'editado_por',
        'editado_en',
    ];

    protected $casts = [
        'version' => 'integer',
        'version_anterior' => 'integer',
        'version_nueva' => 'integer',
        'puntaje_old' => 'decimal:2',
        'puntaje_new' => 'decimal:2',
        'tiempo_old' => 'decimal:3',
        'tiempo_new' => 'decimal:3',
        'penal_old' => 'integer',
        'penal_new' => 'integer',
        'payload_old' => 'array',
        'payload_new' => 'array',
        'editado_en' => 'datetime',
    ];

    public function resultado()
    {
        return $this->belongsTo(Resultado::class, 'resultado_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editado_por');
    }
}
