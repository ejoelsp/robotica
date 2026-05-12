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
                    'nombre' => 'Tabla de evaluacion',
                    'descripcion' => 'Evaluacion por criterios configurables con puntaje maximo por criterio.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ],
            ['codigo'],
            ['nombre', 'descripcion', 'activo', 'updated_at']
        );

        DB::table('catalogo.mecanismos_calificacion')
            ->whereIn('codigo', [
                'cronometro',
                'puntaje',
                'puntaje_jueces',
                'combate',
                'combate_llaves',
                'mixto',
                'solo_registro',
                'soccer_goles',
                'dron_carrera',
                'dron_destreza',
            ])
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.mecanismos_calificacion')) {
            return;
        }

        DB::table('catalogo.mecanismos_calificacion')
            ->whereIn('codigo', ['registro_resultado', 'tabla_evaluacion'])
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }
};
