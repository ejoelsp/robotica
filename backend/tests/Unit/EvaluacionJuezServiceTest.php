<?php

namespace Tests\Unit;

use App\Models\ConfigCalificacion;
use App\Models\Equipo;
use App\Models\MecanismoCalificacion;
use App\Models\Resultado;
use App\Models\Ronda;
use App\Models\User;
use App\Services\ClasificacionConsolidacionService;
use App\Services\EvaluacionJuezService;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class EvaluacionJuezServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_tabla_individual_criterios_guarda_ceros_por_defecto(): void
    {
        $normalizado = $this->normalizar(
            'tabla_individual_criterios',
            [
                ['key' => 'golpes', 'type' => 'number', 'valor_unitario' => 10, 'es_penalizacion' => false],
                ['key' => 'salidas', 'type' => 'number', 'valor_unitario' => 1, 'es_penalizacion' => true],
            ],
            []
        );

        $this->assertSame(0.0, $normalizado['puntaje']);
        $this->assertSame(0.0, $normalizado['penalizaciones']);
        $this->assertSame(0.0, $normalizado['valor_principal']);
        $this->assertSame(0.0, $normalizado['payload_json']['golpes']);
        $this->assertSame(0.0, $normalizado['payload_json']['salidas']);
    }

    public function test_tabla_enfrentamiento_criterios_guarda_ceros_para_lado_sin_captura(): void
    {
        $campos = [
            ['key' => 'golpes', 'type' => 'number', 'valor_unitario' => 10, 'es_penalizacion' => false],
            ['key' => 'salidas', 'type' => 'number', 'valor_unitario' => 1, 'es_penalizacion' => true],
        ];

        $normalizadoA = $this->normalizar(
            'tabla_enfrentamiento_criterios',
            $campos,
            ['golpes_a' => 1, 'golpes_b' => null, 'salidas_a' => null, 'salidas_b' => null],
            'A'
        );

        $normalizadoB = $this->normalizar(
            'tabla_enfrentamiento_criterios',
            $campos,
            ['golpes_a' => 1, 'golpes_b' => null, 'salidas_a' => null, 'salidas_b' => null],
            'B'
        );

        $this->assertSame(10.0, $normalizadoA['puntaje']);
        $this->assertSame(10.0, $normalizadoA['valor_principal']);
        $this->assertSame(0.0, $normalizadoB['puntaje']);
        $this->assertSame(0.0, $normalizadoB['valor_principal']);
        $this->assertSame(0.0, $normalizadoB['payload_json']['golpes_b']);
        $this->assertSame(0.0, $normalizadoB['payload_json']['salidas_b']);
    }

    public function test_tabla_individual_criterios_usa_tiempo_como_desempate(): void
    {
        $normalizado = $this->normalizar(
            'tabla_individual_criterios',
            [
                ['key' => 'impacto', 'type' => 'number', 'valor_unitario' => 25, 'es_penalizacion' => false],
                ['key' => 'funcionalidad', 'type' => 'number', 'valor_unitario' => 40, 'es_penalizacion' => false],
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo de recorrido', 'required' => false],
            ],
            ['impacto' => 1, 'funcionalidad' => 1, 'tiempo' => '00:00:42']
        );

        $this->assertSame(65.0, $normalizado['puntaje']);
        $this->assertSame(0.0, $normalizado['penalizaciones']);
        $this->assertSame(65.0, $normalizado['valor_principal']);
        $this->assertSame(42.0, $normalizado['tiempo']);
        $this->assertSame(42.0, $normalizado['valor_secundario']);
    }

    public function test_tabla_individual_puntaje_maximo_suma_puntajes_directos(): void
    {
        $normalizado = $this->normalizar(
            'tabla_individual_puntaje_maximo',
            [
                ['key' => 'impacto', 'type' => 'number', 'valor_unitario' => 25, 'es_penalizacion' => false],
                ['key' => 'funcionalidad', 'type' => 'number', 'valor_unitario' => 40, 'es_penalizacion' => false],
                ['key' => 'oral', 'type' => 'number', 'valor_unitario' => 20, 'es_penalizacion' => false],
                ['key' => 'contenido', 'type' => 'number', 'valor_unitario' => 15, 'es_penalizacion' => false],
            ],
            ['impacto' => 23, 'funcionalidad' => 37.5, 'oral' => 18, 'contenido' => 14]
        );

        $this->assertSame(92.0, $normalizado['puntaje']);
        $this->assertSame(0, $normalizado['penalizaciones']);
        $this->assertSame(92.0, $normalizado['valor_principal']);
        $this->assertSame(100.0, $normalizado['valor_secundario']);
    }

    public function test_multi_juez_sin_promedio_no_calcula_resultado_consolidado(): void
    {
        $filas = $this->consolidar(
            $this->configConPromedioJueces(false),
            collect([
                $this->resultado(1, 1, 88),
                $this->resultado(1, 2, 91),
            ])
        );

        $this->assertNull($filas[0]['metric_primary']);
        $this->assertTrue($filas[0]['detalle_json']['resumen']['requiere_promedio_jueces']);
        $this->assertFalse($filas[0]['detalle_json']['resumen']['promediar_jueces']);
        $this->assertCount(2, $filas[0]['detalle_json']['evaluaciones']);
    }

    public function test_multi_juez_con_promedio_calcula_promedio_consolidado(): void
    {
        $filas = $this->consolidar(
            $this->configConPromedioJueces(true),
            collect([
                $this->resultado(1, 1, 88),
                $this->resultado(1, 2, 91),
                $this->resultado(1, 3, 86),
            ])
        );

        $this->assertSame(88.333, $filas[0]['metric_primary']);
        $this->assertTrue($filas[0]['detalle_json']['resumen']['promediar_jueces']);
        $this->assertFalse($filas[0]['detalle_json']['resumen']['requiere_promedio_jueces']);
    }

    public function test_mayor_promedio_elige_el_intento_con_mejor_promedio(): void
    {
        $filas = $this->consolidar(
            $this->configConPromedioJueces(true),
            collect([
                $this->resultado(1, 1, 95, 1),
                $this->resultado(1, 2, 70, 1),
                $this->resultado(1, 1, 84, 2),
                $this->resultado(1, 2, 84, 2),
            ]),
            'mayor_promedio'
        );

        $this->assertSame(84.0, $filas[0]['metric_primary']);
        $this->assertSame(2, $filas[0]['detalle_json']['evaluaciones'][0]['intento_numero']);
    }

    public function test_mayor_puntaje_desempata_intentos_por_menor_tiempo(): void
    {
        $filas = $this->consolidar(
            $this->configConPromedioJueces(true),
            collect([
                $this->resultado(1, 1, 80, 1, 50, 50),
                $this->resultado(1, 1, 80, 2, 40, 40),
            ]),
            'mayor_puntaje'
        );

        $this->assertSame(80.0, $filas[0]['metric_primary']);
        $this->assertSame(40.0, $filas[0]['metric_secondary']);
        $this->assertSame(2, $filas[0]['detalle_json']['evaluaciones'][0]['intento_numero']);
    }

    private function normalizar(string $plantilla, array $campos, array $payload, ?string $lado = null, bool $promediar = false): array
    {
        $service = new EvaluacionJuezService(
            Mockery::mock(\App\Services\RegistroCategoriaLockService::class),
            Mockery::mock(ClasificacionConsolidacionService::class)
        );
        $method = new ReflectionMethod(EvaluacionJuezService::class, 'normalizarValoresEvaluacion');
        $method->setAccessible(true);

        $config = new ConfigCalificacion();
        $config->forceFill([
            'reglas_json' => [
                'registro' => [
                    'tipo_registro' => 'tabla_evaluacion',
                    'modalidad_competencia' => $plantilla === 'tabla_enfrentamiento_criterios'
                        ? 'enfrentamiento_directo'
                        : 'participacion_individual',
                    'plantilla_resultado' => $plantilla,
                    'promediar_resultado_final' => $promediar,
                ],
            ],
        ]);
        $config->setRelation('mecanismo', (new MecanismoCalificacion())->forceFill([
            'codigo' => 'tabla_evaluacion',
        ]));

        return $method->invoke($service, $config, $campos, $payload, null, null, $lado);
    }

    private function consolidar(ConfigCalificacion $config, Collection $resultados, string $criterio = 'mayor_puntaje'): array
    {
        $service = new ClasificacionConsolidacionService();
        $method = new ReflectionMethod(ClasificacionConsolidacionService::class, 'construirFilasConsolidadas');
        $method->setAccessible(true);

        $ronda = new Ronda();
        $ronda->forceFill(['criterio_clasificacion' => $criterio]);

        return $method->invoke($service, $resultados, $config, $ronda);
    }

    private function configConPromedioJueces(bool $promediarJueces): ConfigCalificacion
    {
        $config = new ConfigCalificacion();
        $config->forceFill([
            'unidad_resultado' => 'pts',
            'orden_ranking' => 'desc',
            'campos_json' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'valor_unitario' => 100],
            ],
            'reglas_json' => [
                'registro' => [
                    'tipo_registro' => 'tabla_evaluacion',
                    'modalidad_competencia' => 'participacion_individual',
                    'plantilla_resultado' => 'tabla_individual_puntaje_maximo',
                    'esquema_jueces' => 'evaluacion_multi_juez',
                    'promediar_jueces' => $promediarJueces,
                ],
            ],
        ]);
        $config->setRelation('mecanismo', (new MecanismoCalificacion())->forceFill([
            'codigo' => 'tabla_evaluacion',
        ]));

        return $config;
    }

    private function resultado(
        int $equipoId,
        int $juezId,
        float $valor,
        int $intento = 1,
        float $valorSecundario = 100,
        ?float $tiempo = null
    ): Resultado
    {
        $resultado = new Resultado();
        $resultado->forceFill([
            'id' => ($intento * 100) + $juezId,
            'equipo_id' => $equipoId,
            'juez_user_id' => $juezId,
            'intento_numero' => $intento,
            'puntaje' => $valor,
            'tiempo' => $tiempo,
            'valor_principal' => $valor,
            'valor_secundario' => $valorSecundario,
            'penalizaciones' => 0,
            'version' => 1,
            'payload_json' => ['puntaje' => $valor],
        ]);
        $resultado->setRelation('equipo', (new Equipo())->forceFill([
            'id' => $equipoId,
            'nombre' => 'Equipo ' . $equipoId,
            'institucion' => 'Institucion',
        ]));
        $resultado->setRelation('juez', (new User())->forceFill([
            'id' => $juezId,
            'name' => 'Juez',
            'last_name' => (string) $juezId,
        ]));

        return $resultado;
    }
}
