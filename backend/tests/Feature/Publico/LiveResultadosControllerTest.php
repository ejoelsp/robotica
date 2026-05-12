<?php

namespace Tests\Feature\Publico;

use App\Services\ClasificacionConsolidacionService;
use Mockery;
use Tests\TestCase;

class LiveResultadosControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_public_live_snapshot_uses_only_published_states(): void
    {
        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service
            ->shouldReceive('obtenerPanelEnVivo')
            ->once()
            ->with(
                55,
                7,
                9,
                Mockery::on(function (array $options) {
                    return ($options['audience'] ?? null) === 'public'
                        && ($options['estados_publicacion'] ?? null) === ['visible', 'cerrado'];
                })
            )
            ->andReturn([
                'competition_id' => 55,
                'generated_at' => now()->toIso8601String(),
                'meta' => [
                    'audience' => 'public',
                    'estados_publicacion' => ['visible', 'cerrado'],
                    'filters' => [
                        'categoria_id' => 7,
                        'ronda_id' => 9,
                    ],
                    'scopes_count' => 1,
                    'selected_key' => '7:9',
                ],
                'scopes' => [
                    [
                        'key' => '7:9',
                        'categoria_id' => 7,
                        'ronda_id' => 9,
                        'estado_publicacion' => 'visible',
                        'rows' => [],
                    ],
                ],
                'selected' => [
                    'key' => '7:9',
                    'categoria_id' => 7,
                    'ronda_id' => 9,
                    'estado_publicacion' => 'visible',
                    'rows' => [],
                ],
            ]);

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this->getJson('/resultados/en-vivo?competencia_id=55&categoria_id=7&ronda_id=9');

        $response
            ->assertOk()
            ->assertJsonPath('meta.audience', 'public')
            ->assertJsonPath('meta.estados_publicacion.0', 'visible')
            ->assertJsonPath('meta.estados_publicacion.1', 'cerrado')
            ->assertJsonPath('stream.mode', 'short_sse');
    }

    public function test_public_live_stream_emits_retry_payload_and_heartbeat(): void
    {
        config()->set('resultados_live.sse.iterations', 1);
        config()->set('resultados_live.sse.heartbeat_ms', 0);
        config()->set('resultados_live.sse.retry_ms', 1500);

        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service
            ->shouldReceive('obtenerPanelEnVivo')
            ->once()
            ->with(
                55,
                null,
                null,
                Mockery::on(function (array $options) {
                    return ($options['audience'] ?? null) === 'public'
                        && ($options['estados_publicacion'] ?? null) === ['visible', 'cerrado'];
                })
            )
            ->andReturn([
                'competition_id' => 55,
                'generated_at' => now()->toIso8601String(),
                'meta' => [
                    'audience' => 'public',
                    'estados_publicacion' => ['visible', 'cerrado'],
                    'filters' => [
                        'categoria_id' => null,
                        'ronda_id' => null,
                    ],
                    'scopes_count' => 0,
                    'selected_key' => null,
                ],
                'scopes' => [],
                'selected' => null,
            ]);

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this->get('/resultados/en-vivo/stream?competencia_id=55');

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('retry: 1500', $content);
        $this->assertStringContainsString('event: live-results', $content);
        $this->assertStringContainsString('event: heartbeat', $content);
        $this->assertStringContainsString('"mode":"short_sse"', $content);
    }
}
