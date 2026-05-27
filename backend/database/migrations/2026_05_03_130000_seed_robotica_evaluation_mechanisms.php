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
                    'codigo' => 'cronometro',
                    'nombre' => 'Cronometro',
                    'descripcion' => 'Menor tiempo gana; permite penalizaciones y avance parcial.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'puntaje_jueces',
                    'nombre' => 'Puntaje de jueces',
                    'descripcion' => 'Puntaje tecnico o artistico evaluado por jueces, con penalizaciones.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'combate_llaves',
                    'nombre' => 'Combate por llaves',
                    'descripcion' => 'Registro de victoria, derrota o empate para sumo, batalla y peleas.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'soccer_goles',
                    'nombre' => 'Marcador',
                    'descripcion' => 'Registro de marcador entre Equipo A y Equipo B.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'dron_carrera',
                    'nombre' => 'Dron carrera',
                    'descripcion' => 'Carrera de drones contra reloj con obstaculos y penalizacion en segundos.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'dron_destreza',
                    'nombre' => 'Dron destreza',
                    'descripcion' => 'Evaluacion por puntaje de vuelo autonomo o destreza con obstaculos.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'mixto',
                    'nombre' => 'Mixto',
                    'descripcion' => 'Combina varios campos de evaluacion segun la categoria.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'solo_registro',
                    'nombre' => 'Solo registro',
                    'descripcion' => 'Registro simple sin formula de puntaje automatica.',
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
        if (! Schema::hasTable('catalogo.mecanismos_calificacion')) {
            return;
        }

        DB::table('catalogo.mecanismos_calificacion')
            ->whereIn('codigo', [
                'puntaje_jueces',
                'combate_llaves',
                'soccer_goles',
                'dron_carrera',
                'dron_destreza',
            ])
            ->update([
                'activo' => false,
                'updated_at' => now(),
            ]);
    }
};
