<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comunicacion.configuracion_pagos', function (Blueprint $table) {
            $table->id();
            $table->text('informacion_pago');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX configuracion_pagos_activo_unico ON comunicacion.configuracion_pagos (activo) WHERE activo = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS comunicacion.configuracion_pagos_activo_unico');

        Schema::dropIfExists('comunicacion.configuracion_pagos');
    }
};
