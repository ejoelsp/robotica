<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('catalogo.categorias')
            || ! Schema::hasTable('catalogo.config_calificacion')
            || ! Schema::hasTable('catalogo.mecanismos_calificacion')
            || ! Schema::hasTable('resultados.resultados')
        ) {
            return;
        }

        $mecanismos = DB::table('catalogo.mecanismos_calificacion')
            ->where('activo', true)
            ->pluck('id', 'codigo')
            ->all();

        DB::table('catalogo.categorias')
            ->select(['id', 'nombre'])
            ->orderBy('id')
            ->get()
            ->each(function (object $categoria) use ($mecanismos) {
                $codigo = $this->resolverMecanismo((string) $categoria->nombre);

                if (! $codigo || ! isset($mecanismos[$codigo])) {
                    return;
                }

                if ($this->categoriaTieneResultados((int) $categoria->id)) {
                    return;
                }

                $configActual = DB::table('catalogo.config_calificacion')
                    ->where('categoria_id', $categoria->id)
                    ->first();

                $defaults = $this->defaultsPorMecanismo($codigo);

                DB::table('catalogo.config_calificacion')->updateOrInsert(
                    ['categoria_id' => $categoria->id],
                    [
                        'mecanismo_calificacion_id' => $mecanismos[$codigo],
                        'unidad_resultado' => $defaults['unidad_resultado'],
                        'orden_ranking' => $defaults['orden_ranking'],
                        'requiere_aprobacion_admin' => (bool) ($configActual->requiere_aprobacion_admin ?? true),
                        'visible_publico_en_vivo' => (bool) ($configActual->visible_publico_en_vivo ?? false),
                        'permite_edicion_juez' => (bool) ($configActual->permite_edicion_juez ?? true),
                        'campos_json' => json_encode($this->camposPorMecanismo($codigo)),
                        'reglas_json' => json_encode([
                            'ranking' => [
                                'order' => $defaults['orden_ranking'],
                                'unit' => $defaults['unidad_resultado'],
                            ],
                            'workflow' => [
                                'requiere_aprobacion_admin' => (bool) ($configActual->requiere_aprobacion_admin ?? true),
                                'visible_publico_en_vivo' => (bool) ($configActual->visible_publico_en_vivo ?? false),
                                'permite_edicion_juez' => (bool) ($configActual->permite_edicion_juez ?? true),
                            ],
                            'remapeo_seguro' => [
                                'origen' => '2026_05_03_140000_remap_robotica_category_evaluation_configs',
                                'config_anterior' => $configActual ? [
                                    'mecanismo_calificacion_id' => $configActual->mecanismo_calificacion_id,
                                    'unidad_resultado' => $configActual->unidad_resultado,
                                    'orden_ranking' => $configActual->orden_ranking,
                                    'campos_json' => $configActual->campos_json,
                                    'reglas_json' => $configActual->reglas_json,
                                ] : null,
                            ],
                        ]),
                        'updated_at' => now(),
                        'created_at' => $configActual->created_at ?? now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.config_calificacion')) {
            return;
        }

        DB::table('catalogo.config_calificacion')
            ->where('reglas_json->remapeo_seguro->origen', '2026_05_03_140000_remap_robotica_category_evaluation_configs')
            ->orderBy('id')
            ->get()
            ->each(function (object $config) {
                $reglas = is_string($config->reglas_json)
                    ? json_decode($config->reglas_json, true)
                    : (array) $config->reglas_json;
                $anterior = $reglas['remapeo_seguro']['config_anterior'] ?? null;

                if (! is_array($anterior)) {
                    unset($reglas['remapeo_seguro']);

                    DB::table('catalogo.config_calificacion')
                        ->where('id', $config->id)
                        ->update([
                            'reglas_json' => json_encode($reglas),
                            'updated_at' => now(),
                        ]);

                    return;
                }

                DB::table('catalogo.config_calificacion')
                    ->where('id', $config->id)
                    ->update([
                        'mecanismo_calificacion_id' => $anterior['mecanismo_calificacion_id'],
                        'unidad_resultado' => $anterior['unidad_resultado'],
                        'orden_ranking' => $anterior['orden_ranking'],
                        'campos_json' => $anterior['campos_json'],
                        'reglas_json' => $anterior['reglas_json'],
                        'updated_at' => now(),
                    ]);
            });
    }

    private function categoriaTieneResultados(int $categoriaId): bool
    {
        return DB::table('resultados.resultados')
            ->where('categoria_id', $categoriaId)
            ->orWhereIn('ronda_id', function ($query) use ($categoriaId) {
                $query->select('id')
                    ->from('catalogo.rondas')
                    ->where('categoria_id', $categoriaId);
            })
            ->exists();
    }

    private function resolverMecanismo(string $nombre): ?string
    {
        $normalizado = Str::of($nombre)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();

        if (Str::contains($normalizado, ['batalla', 'sumo', 'pelea', 'simulacion de batalla', 'persecucion'])) {
            return 'combate_llaves';
        }

        if (Str::contains($normalizado, 'soccer')) {
            return 'soccer_goles';
        }

        if (Str::contains($normalizado, 'dron') && Str::contains($normalizado, 'carrera')) {
            return 'dron_carrera';
        }

        if (Str::contains($normalizado, 'dron') && Str::contains($normalizado, ['autonomo', 'destreza'])) {
            return 'dron_destreza';
        }

        if (Str::contains($normalizado, 'bailarin')) {
            return 'puntaje_jueces';
        }

        if (Str::contains($normalizado, ['carrera', 'laberinto', 'trepador', 'seguidor'])) {
            return 'cronometro';
        }

        return null;
    }

    private function defaultsPorMecanismo(string $codigo): array
    {
        return Arr::get([
            'cronometro' => ['unidad_resultado' => 's', 'orden_ranking' => 'asc'],
            'puntaje_jueces' => ['unidad_resultado' => 'pts', 'orden_ranking' => 'desc'],
            'combate_llaves' => ['unidad_resultado' => 'pts', 'orden_ranking' => 'desc'],
            'soccer_goles' => ['unidad_resultado' => 'marcador', 'orden_ranking' => 'desc'],
            'dron_carrera' => ['unidad_resultado' => 's', 'orden_ranking' => 'asc'],
            'dron_destreza' => ['unidad_resultado' => 'pts', 'orden_ranking' => 'desc'],
        ], $codigo, ['unidad_resultado' => null, 'orden_ranking' => 'desc']);
    }

    private function camposPorMecanismo(string $codigo): array
    {
        return match ($codigo) {
            'cronometro' => [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'distancia_avanzada', 'type' => 'number', 'label' => 'Distancia avanzada', 'required' => false],
                ['key' => 'completo_si_no', 'type' => 'checkbox', 'label' => 'Recorrido completo', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'puntaje_jueces' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'combate_llaves' => [
                [
                    'key' => 'resultado',
                    'type' => 'select',
                    'label' => 'Resultado',
                    'required' => true,
                    'options' => [
                        ['value' => 'victoria', 'label' => 'Victoria'],
                        ['value' => 'derrota', 'label' => 'Derrota'],
                        ['value' => 'empate', 'label' => 'Empate'],
                    ],
                ],
                ['key' => 'puntos', 'type' => 'number', 'label' => 'Puntos', 'required' => false],
                ['key' => 'amonestaciones', 'type' => 'number', 'label' => 'Amonestaciones', 'required' => false],
                ['key' => 'descalificado', 'type' => 'checkbox', 'label' => 'Descalificado', 'required' => false],
                [
                    'key' => 'metodo_victoria',
                    'type' => 'select',
                    'label' => 'Metodo de victoria',
                    'required' => false,
                    'options' => [
                        ['value' => 'expulsion', 'label' => 'Expulsion'],
                        ['value' => 'ko', 'label' => 'KO'],
                        ['value' => 'inmovilidad', 'label' => 'Inmovilidad'],
                        ['value' => 'abandono', 'label' => 'Abandono'],
                        ['value' => 'decision_juez', 'label' => 'Decision juez'],
                    ],
                ],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'soccer_goles' => [
                ['key' => 'marcador_equipo_a', 'type' => 'number', 'label' => 'Marcador equipo A', 'required' => true],
                ['key' => 'marcador_equipo_b', 'type' => 'number', 'label' => 'Marcador equipo B', 'required' => true],
            ],
            'dron_carrera' => [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                ['key' => 'obstaculos_no_superados', 'type' => 'number', 'label' => 'Obstaculos no superados', 'required' => false],
                ['key' => 'penalizaciones_segundos', 'type' => 'number', 'label' => 'Penalizacion en segundos', 'required' => false],
                ['key' => 'completo_si_no', 'type' => 'checkbox', 'label' => 'Recorrido completo', 'required' => false],
                ['key' => 'porcentaje_recorrido', 'type' => 'number', 'label' => 'Porcentaje recorrido', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'dron_destreza' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'required' => true],
                ['key' => 'obstaculos_superados', 'type' => 'number', 'label' => 'Obstaculos superados', 'required' => false],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            default => [],
        };
    }
};
