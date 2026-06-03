<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.mecanismos_calificacion')) {
            return;
        }

        DB::table('catalogo.mecanismos_calificacion')
            ->where('codigo', 'tabla_evaluacion')
            ->update([
                'nombre' => 'Tabla de evaluación',
                'descripcion' => 'Evaluación por criterios configurables con puntaje máximo por criterio.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.mecanismos_calificacion')) {
            return;
        }

        DB::table('catalogo.mecanismos_calificacion')
            ->where('codigo', 'tabla_evaluacion')
            ->update([
                'nombre' => 'Tabla de evaluacion',
                'descripcion' => 'Evaluacion por criterios configurables con puntaje maximo por criterio.',
                'updated_at' => now(),
            ]);
    }
};
