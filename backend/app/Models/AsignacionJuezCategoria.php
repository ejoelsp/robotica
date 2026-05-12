<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionJuezCategoria extends Model
{
    use HasFactory;

    protected $table = 'vinculaciones.asignaciones_juez_categoria';

    protected $fillable = [
        'categoria_id',
        'juez_user_id',
        'rol',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function juez()
    {
        return $this->belongsTo(User::class, 'juez_user_id');
    }
}
