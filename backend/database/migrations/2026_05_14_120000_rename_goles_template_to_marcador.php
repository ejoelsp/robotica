<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.config_calificacion')) {
            return;
        }

        DB::table('catalogo.config_calificacion')
            ->orderBy('id')
            ->get()
            ->each(function (object $config) {
                $reglas = is_string($config->reglas_json)
                    ? json_decode($config->reglas_json, true)
                    : (array) $config->reglas_json;

                $plantilla = $reglas['registro']['plantilla_resultado'] ?? null;

                if ($plantilla === 'goles') {
                    $reglas['registro']['plantilla_resultado'] = 'marcador';
                    $reglas['ranking']['unit'] = 'marcador';

                    DB::table('catalogo.config_calificacion')
                        ->where('id', $config->id)
                        ->update([
                            'unidad_resultado' => 'marcador',
                            'campos_json' => json_encode([
                                ['key' => 'marcador_equipo_a', 'type' => 'number', 'label' => 'Marcador equipo A', 'required' => true],
                                ['key' => 'marcador_equipo_b', 'type' => 'number', 'label' => 'Marcador equipo B', 'required' => true],
                            ]),
                            'reglas_json' => json_encode($reglas),
                            'updated_at' => now(),
                        ]);

                    return;
                }

                if (in_array($plantilla, ['puntaje', 'ganador', 'personalizado'], true)) {
                    $reglas['registro']['plantilla_resultado'] = 'tiempo';
                    $reglas['ranking']['unit'] = 's';

                    DB::table('catalogo.config_calificacion')
                        ->where('id', $config->id)
                        ->update([
                            'unidad_resultado' => 's',
                            'campos_json' => json_encode([
                                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizacion en segundos', 'required' => false],
                                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
                            ]),
                            'reglas_json' => json_encode($reglas),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
