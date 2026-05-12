<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\ClasificacionConsolidacionService;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Mockery;
use Tests\TestCase;

class ResultadoControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_consolidado_returns_service_payload(): void
    {
        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service
            ->shouldReceive('obtenerVista')
            ->once()
            ->with(6, 7, 9)
            ->andReturn([
                'scope' => [
                    'competencia_id' => 6,
                    'categoria_id' => 7,
                    'ronda_id' => 9,
                ],
                'summary' => [
                    'clasificaciones_count' => 3,
                ],
                'rows' => [],
            ]);

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this->getJson('/admin/resultados/consolidado?competencia_id=6&categoria_id=7&ronda_id=9');

        $response
            ->assertOk()
            ->assertJsonPath('scope.competencia_id', 6)
            ->assertJsonPath('summary.clasificaciones_count', 3);
    }

    public function test_consolidar_returns_service_payload(): void
    {
        $user = $this->makeUser(71);

        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service
            ->shouldReceive('consolidar')
            ->once()
            ->with(6, 7, 9, $user)
            ->andReturn([
                'scope' => [
                    'competencia_id' => 6,
                    'categoria_id' => 7,
                    'ronda_id' => 9,
                ],
                'rows' => [
                    ['id' => 1, 'posicion' => 1],
                ],
            ]);

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this
            ->actingAs($user)
            ->postJson('/admin/resultados/consolidar', [
                'competencia_id' => 6,
                'categoria_id' => 7,
                'ronda_id' => 9,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('scope.ronda_id', 9)
            ->assertJsonPath('rows.0.id', 1);
    }

    public function test_publicar_returns_service_payload(): void
    {
        $user = $this->makeUser(72);

        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service
            ->shouldReceive('actualizarEstadoPublicacion')
            ->once()
            ->with(6, 7, 9, 'visible', $user)
            ->andReturn([
                'scope' => [
                    'competencia_id' => 6,
                    'categoria_id' => 7,
                    'ronda_id' => 9,
                ],
                'summary' => [
                    'estado_publicacion' => 'visible',
                ],
                'publication_history' => [
                    ['accion' => 'publicar'],
                ],
            ]);

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this
            ->actingAs($user)
            ->postJson('/admin/resultados/publicar', [
                'competencia_id' => 6,
                'categoria_id' => 7,
                'ronda_id' => 9,
                'estado_publicacion' => 'visible',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('summary.estado_publicacion', 'visible')
            ->assertJsonPath('publication_history.0.accion', 'publicar');
    }

    public function test_publicar_validates_estado_publicacion(): void
    {
        $service = Mockery::mock(ClasificacionConsolidacionService::class);
        $service->shouldNotReceive('actualizarEstadoPublicacion');

        $this->app->instance(ClasificacionConsolidacionService::class, $service);

        $response = $this->postJson('/admin/resultados/publicar', [
            'competencia_id' => 6,
            'categoria_id' => 7,
            'ronda_id' => 9,
            'estado_publicacion' => 'invalido',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado_publicacion']);
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->name = 'Admin';
        $user->last_name = 'Prueba';
        $user->email = "admin{$id}@example.com";
        $user->role_id = 1;

        return $user;
    }
}
