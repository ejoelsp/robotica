<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_competencia_categoria_equipo_unique
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'inscripciones_categoria_equipo_prototipo_unique'
                ) THEN
                    ALTER TABLE vinculaciones.inscripciones
                    ADD CONSTRAINT inscripciones_categoria_equipo_prototipo_unique
                    UNIQUE (categoria_id, equipo_id, nombre_prototipo);
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_categoria_equipo_prototipo_unique
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'inscripciones_competencia_categoria_equipo_unique'
                ) THEN
                    ALTER TABLE vinculaciones.inscripciones
                    ADD CONSTRAINT inscripciones_competencia_categoria_equipo_unique
                    UNIQUE (competencia_id, categoria_id, equipo_id);
                END IF;
            END
            $$;
        ");
    }
};
