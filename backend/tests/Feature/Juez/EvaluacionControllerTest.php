<?php

namespace Tests\Feature\Juez;

use App\Exceptions\EvaluacionConcurrencyException;
use App\Models\Resultado;
use App\Models\User;
use App\Services\EvaluacionJuezService;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class EvaluacionControllerTest extends TestCase
{
    use WithoutMiddleware;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_contexto_returns_service_payload(): void
    {
        $user = $this->makeUser(44);

        $service = Mockery::mock(EvaluacionJuezService::class);
        $service
            ->shouldReceive('getContextoJuez')
            ->once()
            ->with($user, 7, 9)
            ->andReturn([
                'categorias' => [
                    ['categoria' => ['id' => 7, 'nombre' => 'Seguidor de linea']],
                ],
                'seleccion' => [
                    'categoria_id' => 7,
                    'ronda_id' => 9,
                ],
                'equipos' => [],
            ]);

        $this->app->instance(EvaluacionJuezService::class, $service);

        $response = $this
            ->actingAs($user)
            ->getJson('/juez/evaluaciones/contexto?categoria_id=7&ronda_id=9');

        $response
            ->assertOk()
            ->assertJsonPath('seleccion.categoria_id', 7)
            ->assertJsonPath('seleccion.ronda_id', 9);
    }

    public function test_formulario_returns_service_payload(): void
    {
        $user = $this->makeUser(45);

        $service = Mockery::mock(EvaluacionJuezService::class);
        $service
            ->shouldReceive('construirFormulario')
            ->once()
            ->with($user, 3, 11)
            ->andReturn([
                'ronda' => ['id' => 3, 'nombre' => 'Ronda 1'],
                'equipo' => ['id' => 11, 'nombre' => 'Equipo A'],
                'config_calificacion' => ['mecanismo_codigo' => 'puntaje'],
                'resultado_actual' => null,
            ]);

        $this->app->instance(EvaluacionJuezService::class, $service);

        $response = $this
            ->actingAs($user)
            ->getJson('/juez/evaluaciones/formulario?ronda_id=3&equipo_id=11');

        $response
            ->assertOk()
            ->assertJsonPath('ronda.id', 3)
            ->assertJsonPath('equipo.id', 11);
    }

    public function test_guardar_returns_service_payload(): void
    {
        $user = $this->makeUser(46);

        $service = Mockery::mock(EvaluacionJuezService::class);
        $service
            ->shouldReceive('guardarEvaluacion')
            ->once()
            ->with($user, Mockery::on(function (array $payload) {
                return $payload['ronda_id'] === 3
                    && $payload['equipo_id'] === 11
                    && $payload['version'] === 1
                    && $payload['payload']['puntaje'] === 9.5;
            }))
            ->andReturn([
                'guardado' => true,
                'resultado' => [
                    'id' => 99,
                    'version' => 2,
                    'estado' => 'registrado',
                ],
            ]);

        $this->app->instance(EvaluacionJuezService::class, $service);

        $response = $this
            ->actingAs($user)
            ->postJson('/juez/evaluaciones', [
                'ronda_id' => 3,
                'equipo_id' => 11,
                'version' => 1,
                'payload' => [
                    'puntaje' => 9.5,
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('guardado', true)
            ->assertJsonPath('resultado.id', 99)
            ->assertJsonPath('resultado.version', 2);
    }

    public function test_guardar_returns_conflict_payload_on_concurrency_exception(): void
    {
        $user = $this->makeUser(47);
        $resultado = new Resultado();
        $resultado->forceFill([
            'id' => 14,
            'version' => 5,
            'estado' => 'registrado',
            'updated_at' => Carbon::parse('2026-04-26 12:00:00'),
        ]);

        $service = Mockery::mock(EvaluacionJuezService::class);
        $service
            ->shouldReceive('guardarEvaluacion')
            ->once()
            ->andThrow(new EvaluacionConcurrencyException($resultado));

        $this->app->instance(EvaluacionJuezService::class, $service);

        $response = $this
            ->actingAs($user)
            ->postJson('/juez/evaluaciones', [
                'ronda_id' => 3,
                'equipo_id' => 11,
                'version' => 4,
                'payload' => [
                    'puntaje' => 8.1,
                ],
            ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('conflict', true)
            ->assertJsonPath('resultado_actual.id', 14)
            ->assertJsonPath('resultado_actual.version', 5);
    }

    public function test_guardar_validates_payload_shape_before_hitting_service(): void
    {
        $user = $this->makeUser(48);

        $service = Mockery::mock(EvaluacionJuezService::class);
        $service->shouldNotReceive('guardarEvaluacion');

        $this->app->instance(EvaluacionJuezService::class, $service);

        $response = $this
            ->actingAs($user)
            ->postJson('/juez/evaluaciones', [
                'ronda_id' => 3,
                'equipo_id' => 11,
                'payload' => 'invalido',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payload']);
    }

    private function makeUser(int $id): User
    {
        $user = new User();
        $user->id = $id;
        $user->name = 'Juez';
        $user->last_name = 'Prueba';
        $user->email = "juez{$id}@example.com";
        $user->role_id = 2;

        return $user;
    }
}
