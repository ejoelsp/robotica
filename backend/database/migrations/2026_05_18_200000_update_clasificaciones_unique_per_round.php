<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')) {
            return;
        }

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_categoria_id_equipo_id_unique
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_categoria_ronda_equipo_unique
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            ADD CONSTRAINT clasificaciones_categoria_ronda_equipo_unique
            UNIQUE (categoria_id, ronda_id, equipo_id)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')) {
            return;
        }

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_categoria_ronda_equipo_unique
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            ADD CONSTRAINT clasificaciones_categoria_id_equipo_id_unique
            UNIQUE (categoria_id, equipo_id)
        ");
    }
};
