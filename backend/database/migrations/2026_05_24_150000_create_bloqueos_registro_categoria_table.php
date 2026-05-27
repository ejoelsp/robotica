<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vinculaciones.bloqueos_registro_categoria')) {
            Schema::create('vinculaciones.bloqueos_registro_categoria', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('categoria_id');
                $table->unsignedBigInteger('juez_user_id');
                $table->unsignedBigInteger('asignacion_juez_id')->nullable();
                $table->string('session_id', 120)->nullable();
                $table->string('estado', 30)->default('activo');
                $table->timestamp('bloqueado_desde')->useCurrent();
                $table->timestamp('ultimo_ping_at')->nullable();
                $table->timestamp('liberado_at')->nullable();
                $table->string('motivo_liberacion', 50)->nullable();
                $table->timestamps();

                $table->foreign('categoria_id')
                    ->references('id')
                    ->on('catalogo.categorias')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table->foreign('juez_user_id')
                    ->references('id')
                    ->on('seguridad.users')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();

                $table->foreign('asignacion_juez_id')
                    ->references('id')
                    ->on('vinculaciones.asignaciones_juez_categoria')
                    ->cascadeOnUpdate()
                    ->nullOnDelete();

                $table->index(['categoria_id', 'estado'], 'bloqueos_registro_categoria_estado_index');
                $table->index(['juez_user_id', 'estado'], 'bloqueos_registro_juez_estado_index');
            });
        }

        DB::statement('ALTER TABLE vinculaciones.bloqueos_registro_categoria DROP CONSTRAINT IF EXISTS bloqueos_registro_categoria_estado_check');
        DB::statement("
            ALTER TABLE vinculaciones.bloqueos_registro_categoria
            ADD CONSTRAINT bloqueos_registro_categoria_estado_check
            CHECK (estado IN ('activo', 'liberado', 'expirado'))
        ");

        DB::statement('DROP INDEX IF EXISTS vinculaciones.bloqueos_registro_categoria_activo_unique');
        DB::statement("
            CREATE UNIQUE INDEX bloqueos_registro_categoria_activo_unique
            ON vinculaciones.bloqueos_registro_categoria (categoria_id)
            WHERE estado = 'activo'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS vinculaciones.bloqueos_registro_categoria_activo_unique');
        Schema::dropIfExists('vinculaciones.bloqueos_registro_categoria');
    }
};
