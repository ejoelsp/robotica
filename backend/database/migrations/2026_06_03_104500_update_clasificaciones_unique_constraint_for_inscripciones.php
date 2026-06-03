<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')) {
            return;
        }

        Schema::table('resultados.clasificaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('resultados.clasificaciones', 'inscripcion_id')) {
                $table->unsignedBigInteger('inscripcion_id')->nullable()->after('equipo_id');
            }
        });

        DB::statement("
            UPDATE resultados.clasificaciones c
            SET inscripcion_id = r.inscripcion_id
            FROM resultados.resultados r
            WHERE c.ronda_id = r.ronda_id
              AND c.equipo_id = r.equipo_id
              AND c.inscripcion_id IS NULL
              AND r.inscripcion_id IS NOT NULL
        ");

        DB::statement("
            UPDATE resultados.clasificaciones
            SET inscripcion_id = NULLIF(detalle_json #>> '{evaluaciones,0,inscripcion_id}', '')::bigint
            WHERE inscripcion_id IS NULL
              AND NULLIF(detalle_json #>> '{evaluaciones,0,inscripcion_id}', '') IS NOT NULL
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_categoria_ronda_equipo_unique
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_categoria_ronda_inscripcion_unique
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasificaciones_inscripcion_id_index
            ON resultados.clasificaciones (inscripcion_id)
        ");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS clasificaciones_categoria_ronda_inscripcion_unique
            ON resultados.clasificaciones (categoria_id, ronda_id, inscripcion_id)
            WHERE inscripcion_id IS NOT NULL
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'clasificaciones_inscripcion_id_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones
                    ADD CONSTRAINT clasificaciones_inscripcion_id_foreign
                    FOREIGN KEY (inscripcion_id)
                    REFERENCES vinculaciones.inscripciones(id)
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')) {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_categoria_ronda_inscripcion_unique
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_inscripcion_id_index
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_inscripcion_id_foreign
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

        Schema::table('resultados.clasificaciones', function (Blueprint $table) {
            if (Schema::hasColumn('resultados.clasificaciones', 'inscripcion_id')) {
                $table->dropColumn('inscripcion_id');
            }
        });
    }
};
