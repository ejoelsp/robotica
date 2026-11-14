<?php

namespace Tests\Feature\Competidor;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificacionCounterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::statement("ATTACH DATABASE ':memory:' AS comunicacion");
        Schema::create('comunicacion.notificaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('leido')->default(false);
            $table->timestamp('leido_en')->nullable();
            $table->timestamps();
        });
        DB::table('comunicacion.notificaciones')->insert([
            ['id' => 1, 'user_id' => 10, 'leido' => false],
            ['id' => 2, 'user_id' => 10, 'leido' => false],
            ['id' => 3, 'user_id' => 20, 'leido' => false],
            ['id' => 4, 'user_id' => 10, 'leido' => true],
        ]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    public function test_counter_requires_authentication(): void
    {
        $this->getJson('/competidor/notificaciones/contador')->assertUnauthorized();
    }

    public function test_counter_only_counts_notifications_of_the_authenticated_user(): void
    {
        $this->actingAs($this->competidor(10))->getJson('/competidor/notificaciones/contador?user_id=20')
            ->assertOk()->assertExactJson(['no_leidas' => 2]);
        $this->actingAs($this->competidor(20))->getJson('/competidor/notificaciones/contador')
            ->assertOk()->assertExactJson(['no_leidas' => 1]);
    }

    public function test_reading_a_notification_updates_the_counter_without_affecting_other_users(): void
    {
        $this->actingAs($this->competidor(10))->patchJson('/notificaciones/1/leer')->assertOk();
        $this->getJson('/competidor/notificaciones/contador')->assertExactJson(['no_leidas' => 1]);
        $this->patchJson('/notificaciones/3/leer')->assertForbidden();
        $this->assertFalse((bool) DB::table('comunicacion.notificaciones')->where('id', 3)->value('leido'));
    }

    private function competidor(int $id): User
    {
        $user = new User;
        $user->id = $id;
        $user->name = 'Competidor de prueba';
        $user->role_id = 3;

        return $user;
    }
}
