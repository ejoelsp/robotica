<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\DashboardAdminService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Nunca se ejecutan migraciones ni escrituras contra la base del proyecto.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        foreach (['catalogo', 'vinculaciones', 'seguridad', 'resultados', 'comunicacion'] as $schema) {
            DB::statement("ATTACH DATABASE ':memory:' AS {$schema}");
        }
        // SQLite admite schema.tabla.columna, pero no schema.tabla.*.
        // Esta adaptación solo afecta la sintaxis de las pruebas de Eloquent.
        DB::connection()->setQueryGrammar(new class(DB::connection()) extends \Illuminate\Database\Query\Grammars\SQLiteGrammar
        {
            public function wrap($value, $prefixAlias = false)
            {
                if (is_string($value) && preg_match('/^[^.]+\.([^.]+)\.\*$/', $value, $matches)) {
                    return parent::wrap($matches[1].'.*', $prefixAlias);
                }

                return parent::wrap($value, $prefixAlias);
            }
        });
        $this->createTables();
        $this->seedScope();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_admin_receives_real_counts_and_competition_specific_links(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('component', 'Admin/Dashboard')
            ->assertJsonPath('props.competencia.id', 1)
            ->assertJsonPath('props.dashboard.indicadores.categorias_activas', 3)
            ->assertJsonPath('props.dashboard.indicadores.jueces_activos', 2)
            ->assertJsonPath('props.dashboard.inscripciones.total', 7)
            ->assertJsonPath('props.dashboard.inscripciones.aprobadas', 4)
            ->assertJsonPath('props.dashboard.inscripciones.pendientes', 1)
            ->assertJsonPath('props.dashboard.inscripciones.rechazadas', 2)
            ->assertJsonPath('props.dashboard.pendientes.asignaciones', 1)
            ->assertJsonPath('props.dashboard.pendientes.notificaciones', 2)
            ->assertJsonPath('props.dashboard.pendientes.actas', 2)
            ->assertJsonPath('props.dashboard.enlaces.inscripciones', '/admin/inscripciones?competencia_id=1')
            ->assertJsonPath('props.dashboard.enlaces.asignaciones', '/admin/asignacion-jueces?competencia_id=1')
            ->assertJsonPath('props.dashboard.enlaces.comite', '/admin/competencias?comite=1')
            ->assertJsonPath('props.dashboard.enlaces.certificados_manuales', '/admin/certificados?competencia_id=1&tab=manual');
    }

    public function test_selecting_another_competition_isolates_all_event_metrics(): void
    {
        $this->actingAs($this->admin())->get('/admin/dashboard?competencia_id=2', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('props.competencia.id', 2)
            ->assertJsonPath('props.dashboard.inscripciones.total', 1)
            ->assertJsonPath('props.dashboard.inscripciones.pendientes', 1)
            ->assertJsonPath('props.dashboard.indicadores.jueces_activos', 0)
            ->assertJsonPath('props.dashboard.pendientes.actas', 1)
            ->assertJsonPath('props.dashboard.pendientes.notificaciones', 2)
            ->assertJsonCount(1, 'props.dashboard.rondas.data');
    }

    public function test_closure_uses_the_existing_rules_and_ignores_stale_official_records(): void
    {
        $panel = $this->panel();
        // La categoría inactiva con inscripción aprobada también es evaluable.
        $this->assertSame(3, $panel['analisis']['categorias_evaluables']);
        $this->assertSame(1, $panel['analisis']['categorias_cerradas']);
        $this->assertSame(2, $panel['analisis']['categorias_pendientes']);
        $this->assertSame(33.3, $panel['analisis']['porcentaje']);
        $this->assertSame('preliminar', $panel['analisis']['estado']);

        DB::table('catalogo.rondas')->where('categoria_id', 12)->update(['estado' => 'cerrada']);
        DB::table('catalogo.rondas')->insert(['id' => 131, 'categoria_id' => 13, 'nombre' => 'Final', 'estado' => 'cerrada', 'orden' => 1, 'es_final' => true]);
        $this->assertSame('oficial', $this->panel()['analisis']['estado']);
        DB::table('resultados.analisis_historico_cierres')->where('competencia_id', 1)->delete();
        $this->assertSame('listo', $this->panel()['analisis']['estado']);
        $this->assertSame(100.0, $this->panel()['analisis']['porcentaje']);
        $this->assertSame(0, DB::table('resultados.analisis_historico_cierres')->count());
    }

    public function test_round_and_publication_states_remain_independent(): void
    {
        $rows = collect($this->panel()['rondas']['data']);
        $this->assertSame('cerrada', $rows->firstWhere('ronda_id', 121)['estado']);
        $this->assertSame('visible', $rows->firstWhere('ronda_id', 121)['publicacion']);
        $this->assertSame('sin_consolidar', $rows->firstWhere('ronda_id', 122)['publicacion']);
        $this->assertSame('sin_rondas', $rows->firstWhere('categoria_id', 13)['estado']);
        DB::table('resultados.clasificaciones')->insert(['id' => 3, 'competencia_id' => 1, 'categoria_id' => 12, 'ronda_id' => 121, 'estado_publicacion' => 'borrador']);
        $this->assertSame('mixto', collect($this->panel()['rondas']['data'])->firstWhere('ronda_id', 121)['publicacion']);
    }

    public function test_dashboard_queries_do_not_write_or_generate_rounds_and_closures(): void
    {
        $writes = [];
        DB::listen(function (QueryExecuted $query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|create|alter|drop)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });
        $this->panel();
        $this->assertSame([], $writes);
    }

    public function test_round_pagination_preserves_competition_context(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DB::table('catalogo.rondas')->insert(['id' => 300 + $i, 'categoria_id' => 11, 'nombre' => 'Ronda '.$i, 'estado' => 'borrador', 'orden' => $i + 1, 'es_final' => false]);
        }
        $first = $this->panel()['rondas'];
        $this->assertCount(10, $first['data']);
        $this->assertStringContainsString('competencia_id=1', $first['next']);
        $this->assertStringContainsString('rondas_page=2', $first['next']);
        $second = app(DashboardAdminService::class)->obtenerPanel($this->competition(), 1, 2)['rondas'];
        $this->assertCount(7, $second['data']);
        $this->assertSame(2, $second['current_page']);
        $this->assertNull($second['next']);
    }

    public function test_empty_competition_and_empty_system_have_safe_zero_states(): void
    {
        DB::table('catalogo.competencias')->insert(['id' => 3, 'nombre' => 'Vacía', 'estado' => false, 'temporada_id' => null]);
        $this->actingAs($this->admin())->get('/admin/dashboard?competencia_id=3', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('props.dashboard.inscripciones.total', 0)
            ->assertJsonPath('props.dashboard.analisis.porcentaje', 0)
            ->assertJsonPath('props.dashboard.analisis.estado', 'sin_datos')
            ->assertJsonPath('props.dashboard.enlaces.analisis', null);
        DB::table('catalogo.competencias')->delete();
        $this->get('/admin/dashboard', ['X-Inertia' => 'true'])->assertOk()
            ->assertJsonPath('props.competencia', null)
            ->assertJsonPath('props.dashboard.enlaces.inscripciones', null)
            ->assertJsonPath('props.dashboard.enlaces.usuarios', '/admin/control-acceso');
    }

    public function test_invalid_selection_does_not_fall_back_to_another_competition(): void
    {
        $this->actingAs($this->admin())->getJson('/admin/dashboard?competencia_id=999')->assertNotFound();
        $this->getJson('/admin/dashboard?competencia_id=invalid')->assertUnprocessable()->assertJsonValidationErrors('competencia_id');
        $this->getJson('/admin/dashboard?rondas_page=-1')->assertUnprocessable()->assertJsonValidationErrors('rondas_page');
    }

    public function test_dashboard_requires_authentication_and_admin_role(): void
    {
        $this->getJson('/admin/dashboard')->assertUnauthorized();
        $user = $this->admin();
        $user->role_id = 3;
        $this->actingAs($user)->getJson('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_panel_entry_points_redirect_to_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin')->assertRedirect('/admin/dashboard');
        $this->get('/dashboard')->assertRedirect('/admin/dashboard');

        $user = $this->admin();
        $user->role_id = 3;
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('competidor.mis-inscripciones'));
        $this->getJson('/admin')->assertForbidden();
    }

    public function test_successful_admin_login_opens_dashboard(): void
    {
        DB::table('seguridad.users')->insert([
            'id' => 1, 'name' => 'Administrador', 'email' => 'admin@example.test',
            'role_id' => 1, 'estado' => true,
            'password' => \Illuminate\Support\Facades\Hash::make('Dashboard-Prueba-2026!'),
        ]);
        $this->mock(\App\Services\AuditoriaService::class, function ($mock) {
            $mock->shouldReceive('registrar')->once();
        });

        $this->post('/login', ['email' => 'admin@example.test', 'password' => 'Dashboard-Prueba-2026!'])
            ->assertRedirect('/admin/dashboard');
        $this->assertAuthenticated();
    }

    public function test_inscriptions_access_and_export_keep_the_selected_competition(): void
    {
        $this->actingAs($this->admin())->get('/admin/inscripciones?competencia_id=2', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('props.competenciaSeleccionada.id', 2)
            ->assertJsonCount(1, 'props.inscriptions')->assertJsonPath('props.inscriptions.0.categoryId', 21);
        $response = $this->get('/admin/inscripciones/export?competencia_id=2&format=csv')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Categoría 21', $csv);
        $this->assertStringNotContainsString('Categoría 11', $csv);
        $this->get('/admin/inscripciones', ['X-Inertia' => 'true'])->assertOk()->assertJsonCount(8, 'props.inscriptions');
    }

    public function test_judge_assignment_access_and_save_return_to_the_selected_competition(): void
    {
        $this->actingAs($this->admin())->get('/admin/asignacion-jueces?competencia_id=2', ['X-Inertia' => 'true'])
            ->assertOk()->assertJsonPath('props.competenciaId', 2)
            ->assertJsonCount(1, 'props.categorias')->assertJsonPath('props.categorias.0.id', 21);
        DB::table('vinculaciones.asignaciones_juez_categoria')->where('categoria_id', 21)->delete();
        DB::table('catalogo.config_jueces_competencia')->insert(['competencia_id' => 2, 'jueces_principales_requeridos' => 1, 'jueces_apoyo_requeridos' => 0]);
        $this->post('/admin/asignaciones-jueces', ['categoria_id' => 21, 'juez_principal_ids' => [2], 'jueces_apoyo_ids' => []])
            ->assertRedirect('/admin/asignacion-jueces?competencia_id=2');
        $this->assertSame(2, (int) DB::table('vinculaciones.asignaciones_juez_categoria')->where('categoria_id', 21)->value('juez_user_id'));
    }

    private function panel(): array
    {
        return app(DashboardAdminService::class)->obtenerPanel($this->competition(), 1);
    }

    private function competition(): array
    {
        return app(DashboardAdminService::class)->competencias()->firstWhere('id', 1);
    }

    private function admin(): User
    {
        $user = new User;
        $user->id = 1;
        $user->name = 'Administrador';
        $user->email = 'admin@example.test';
        $user->role_id = 1;

        return $user;
    }

    private function createTables(): void
    {
        Schema::create('catalogo.temporadas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->integer('anio');
        });
        Schema::create('catalogo.competencias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->boolean('estado');
            $table->integer('temporada_id')->nullable();
        });
        Schema::create('catalogo.categorias', function (Blueprint $table) {
            $table->id();
            $table->integer('competencia_id');
            $table->string('nombre');
            $table->boolean('estado');
            $table->string('estado_resultados')->default('pendiente');
        });
        Schema::create('catalogo.rondas', function (Blueprint $table) {
            $table->id();
            $table->integer('categoria_id');
            $table->string('nombre');
            $table->integer('orden');
            $table->string('estado');
            $table->boolean('es_final');
        });
        Schema::create('catalogo.config_jueces_competencia', function (Blueprint $table) {
            $table->integer('competencia_id');
            $table->integer('jueces_principales_requeridos');
            $table->integer('jueces_apoyo_requeridos');
        });
        Schema::create('vinculaciones.inscripciones', function (Blueprint $table) {
            $table->id();
            $table->integer('competencia_id');
            $table->integer('categoria_id');
            $table->string('estado');
            $table->string('estado_comprobante')->nullable();
        });
        Schema::create('vinculaciones.asignaciones_juez_categoria', function (Blueprint $table) {
            $table->id();
            $table->integer('categoria_id');
            $table->integer('juez_user_id');
            $table->string('rol');
        });
        Schema::create('seguridad.roles', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('seguridad.users', function (Blueprint $table) {
            $table->id();
            $table->integer('role_id');
            $table->boolean('estado');
            $table->string('password')->nullable();
        });
        Schema::create('resultados.clasificaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('competencia_id');
            $table->integer('categoria_id');
            $table->integer('ronda_id')->nullable();
            $table->string('estado_publicacion');
        });
        Schema::create('resultados.actas_reportes', function (Blueprint $table) {
            $table->id();
            $table->integer('competencia_id');
            $table->string('estado');
        });
        Schema::create('resultados.analisis_historico_cierres', function (Blueprint $table) {
            $table->id();
            $table->integer('competencia_id');
            $table->string('tipo_cierre');
        });
        Schema::create('comunicacion.notificaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->boolean('leido');
        });
        Schema::create('comunicacion.configuracion_pagos', function (Blueprint $table) {
            $table->id();
            $table->string('informacion_pago')->nullable();
            $table->boolean('activo');
            $table->timestamps();
        });
        Schema::create('catalogo.config_calificacion', function (Blueprint $table) {
            $table->id();
            $table->integer('categoria_id');
            $table->integer('mecanismo_calificacion_id')->nullable();
        });
        Schema::table('catalogo.categorias', function (Blueprint $table) {
            $table->decimal('costo_inscripcion')->default(0);
        });
        Schema::table('vinculaciones.inscripciones', function (Blueprint $table) {
            $table->integer('equipo_id')->nullable();
            $table->integer('user_id')->nullable();
        });
        Schema::table('seguridad.users', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->string('photo_path')->nullable();
        });
        Schema::table('vinculaciones.asignaciones_juez_categoria', fn (Blueprint $table) => $table->timestamps());
    }

    private function seedScope(): void
    {
        DB::table('catalogo.temporadas')->insert(['id' => 1, 'nombre' => 'Temporada 2026', 'anio' => 2026]);
        DB::table('catalogo.competencias')->insert([
            ['id' => 1, 'nombre' => 'Principal', 'estado' => true, 'temporada_id' => 1],
            ['id' => 2, 'nombre' => 'Otra competencia', 'estado' => false, 'temporada_id' => 1],
        ]);
        foreach ([[11, 1, true], [12, 1, true], [13, 1, false], [14, 1, true], [21, 2, true]] as [$id, $competition, $active]) {
            DB::table('catalogo.categorias')->insert(['id' => $id, 'competencia_id' => $competition, 'nombre' => 'Categoría '.$id, 'estado' => $active]);
        }
        foreach ([[111, 11, 'cerrada', 1], [121, 12, 'cerrada', 1], [122, 12, 'activa', 2]] as [$id, $category, $state, $order]) {
            DB::table('catalogo.rondas')->insert(['id' => $id, 'categoria_id' => $category, 'nombre' => 'Ronda '.$order, 'estado' => $state, 'orden' => $order, 'es_final' => $id === 111]);
        }
        DB::table('catalogo.config_jueces_competencia')->insert(['competencia_id' => 1, 'jueces_principales_requeridos' => 1, 'jueces_apoyo_requeridos' => 1]);
        DB::table('seguridad.roles')->insert([['id' => 1, 'nombre' => 'admin'], ['id' => 2, 'nombre' => 'juez'], ['id' => 3, 'nombre' => 'competidor']]);
        DB::table('seguridad.users')->insert([['id' => 2, 'role_id' => 2, 'estado' => true], ['id' => 3, 'role_id' => 2, 'estado' => true], ['id' => 4, 'role_id' => 2, 'estado' => false], ['id' => 5, 'role_id' => 3, 'estado' => true]]);
        foreach ([[11, 2, 'principal'], [11, 3, 'apoyo'], [12, 2, 'principal'], [12, 4, 'apoyo'], [21, 5, 'principal']] as [$category, $user, $role]) {
            DB::table('vinculaciones.asignaciones_juez_categoria')->insert(['categoria_id' => $category, 'juez_user_id' => $user, 'rol' => $role]);
        }
        foreach ([[1, 11, 'confirmado', 'aprobado'], [1, 11, 'confirmado', 'aprobado'], [1, 12, 'confirmado', 'aprobado'], [1, 13, 'confirmado', 'aprobado'], [1, 12, 'pendiente_pago', 'revision'], [1, 11, 'pendiente_pago', 'rechazado'], [1, 11, 'confirmado', 'rechazado'], [2, 21, 'pendiente_pago', null]] as [$competition, $category, $state, $payment]) {
            DB::table('vinculaciones.inscripciones')->insert(['competencia_id' => $competition, 'categoria_id' => $category, 'estado' => $state, 'estado_comprobante' => $payment]);
        }
        DB::table('resultados.clasificaciones')->insert([['id' => 1, 'competencia_id' => 1, 'categoria_id' => 11, 'ronda_id' => 111, 'estado_publicacion' => 'cerrado'], ['id' => 2, 'competencia_id' => 1, 'categoria_id' => 12, 'ronda_id' => 121, 'estado_publicacion' => 'visible']]);
        foreach ([[1, 'generado'], [1, 'generado'], [1, 'firmado'], [1, 'anulado'], [2, 'generado']] as [$competition, $state]) {
            DB::table('resultados.actas_reportes')->insert(['competencia_id' => $competition, 'estado' => $state]);
        }
        DB::table('resultados.analisis_historico_cierres')->insert(['competencia_id' => 1, 'tipo_cierre' => 'competencia']);
        foreach ([[1, false], [1, false], [1, true], [2, false]] as [$user, $read]) {
            DB::table('comunicacion.notificaciones')->insert(['user_id' => $user, 'leido' => $read]);
        }
    }
}
