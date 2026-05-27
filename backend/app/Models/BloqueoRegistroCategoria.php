<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BloqueoRegistroCategoria extends Model
{
    use HasFactory;

    protected $table = 'vinculaciones.bloqueos_registro_categoria';

    protected $fillable = [
        'categoria_id',
        'juez_user_id',
        'asignacion_juez_id',
        'session_id',
        'estado',
        'bloqueado_desde',
        'ultimo_ping_at',
        'liberado_at',
        'motivo_liberacion',
    ];

    protected $casts = [
        'bloqueado_desde' => 'datetime',
        'ultimo_ping_at' => 'datetime',
        'liberado_at' => 'datetime',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function juez()
    {
        return $this->belongsTo(User::class, 'juez_user_id');
    }

    public function asignacionJuez()
    {
        return $this->belongsTo(AsignacionJuezCategoria::class, 'asignacion_juez_id');
    }
}
