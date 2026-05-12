<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.mecanismos_calificacion')) {
            Schema::create('catalogo.mecanismos_calificacion', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 50);
                $table->string('nombre', 120);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique('codigo', 'mecanismos_calificacion_codigo_unique');
            });
        }

        DB::table('catalogo.mecanismos_calificacion')->upsert(
            [
                [
                    'codigo' => 'cronometro',
                    'nombre' => 'Cronometro',
                    'descripcion' => 'Registro de tiempos para categorias contrarreloj.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'puntaje',
                    'nombre' => 'Puntaje',
                    'descripcion' => 'Registro directo de puntajes numericos.',
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'codigo' => 'combate',
                    'nombre' => 'Combate',
                    'descripcion' => 'Registro de victorias, derrotas y metricas de enfrentamiento.',
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
        Schema::dropIfExists('catalogo.mecanismos_calificacion');
    }
};
