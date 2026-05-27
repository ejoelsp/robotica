<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'comunicacion.notificaciones';

    protected $fillable = [
        'user_id',
        'competencia_id',
        'categoria_id',
        'canal',
        'tipo',
        'asunto',
        'contenido',
        'estado',
        'leido',
        'leido_en',
        'reintentos',
        'enviado_en',
        'email_destino',
        'error_envio',
        'provider_message_id',
        'referencia_tipo',
        'referencia_id',
        'creado_por',
        'datos',
    ];

    protected $casts = [
        'leido' => 'boolean',
        'leido_en' => 'datetime',
        'enviado_en' => 'datetime',
        'datos' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function competencia()
    {
        return $this->belongsTo(Competencia::class, 'competencia_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }
}
