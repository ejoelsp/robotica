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

        DB::table('catalogo.mecanismos_calificacion')->upsert(
            [
                [
                    'codigo' => 'registro_resultado',
                    'nombre' => 'Registro de resultado',
                    'descripcion' => 'Registro directo de tiempos, marcadores, ganadores, distancias o puntajes finales.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'tabla_evaluacion',
                    'nombre' => 'Tabla de evaluación',
                    'descripcion' => 'Evaluación por criterios configurables con puntaje máximo por criterio.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['codigo'],
            ['nombre', 'descripcion', 'activo', 'updated_at']
        );
    }

    public function down(): void
    {
        // No eliminamos ni desactivamos catalogo para no afectar datos existentes.
    }
};
