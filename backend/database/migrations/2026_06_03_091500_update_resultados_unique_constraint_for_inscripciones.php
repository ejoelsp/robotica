<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_ronda_equipo_juez_intento_unique');

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'resultados_ronda_inscripcion_juez_intento_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_inscripcion_juez_intento_unique
                    UNIQUE (ronda_id, inscripcion_id, juez_user_id, intento_numero);
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_ronda_inscripcion_juez_intento_unique');

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'resultados_ronda_equipo_juez_intento_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_equipo_juez_intento_unique
                    UNIQUE (ronda_id, equipo_id, juez_user_id, intento_numero);
                END IF;
            END
            $$;
        ");
    }
};
