<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionPago extends Model
{
    protected $table = 'comunicacion.configuracion_pagos';

    protected $fillable = [
        'informacion_pago',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
