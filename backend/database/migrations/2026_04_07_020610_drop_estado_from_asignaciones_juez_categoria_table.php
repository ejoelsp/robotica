<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.asignaciones_juez_categoria
            DROP CONSTRAINT IF EXISTS asignaciones_juez_categoria_estado_check
        ");

        Schema::table('vinculaciones.asignaciones_juez_categoria', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }

    public function down(): void
    {
        Schema::table('vinculaciones.asignaciones_juez_categoria', function (Blueprint $table) {
            $table->string('estado', 20)->default('activa');
        });

        DB::statement("
            ALTER TABLE vinculaciones.asignaciones_juez_categoria
            ADD CONSTRAINT asignaciones_juez_categoria_estado_check
            CHECK (estado IN ('activa', 'inactiva'))
        ");
    }
};