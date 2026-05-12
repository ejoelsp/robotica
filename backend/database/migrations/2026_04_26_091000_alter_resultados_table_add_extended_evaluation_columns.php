<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.resultados')) {
            return;
        }

        Schema::table('resultados.resultados', function (Blueprint $table) {
            if (! Schema::hasColumn('resultados.resultados', 'competencia_id')) {
                $table->unsignedBigInteger('competencia_id')->nullable()->after('juez_user_id');
            }

            if (! Schema::hasColumn('resultados.resultados', 'categoria_id')) {
                $table->unsignedBigInteger('categoria_id')->nullable()->after('competencia_id');
            }

            if (! Schema::hasColumn('resultados.resultados', 'inscripcion_id')) {
                $table->unsignedBigInteger('inscripcion_id')->nullable()->after('categoria_id');
            }

            if (! Schema::hasColumn('resultados.resultados', 'asignacion_juez_id')) {
                $table->unsignedBigInteger('asignacion_juez_id')->nullable()->after('inscripcion_id');
            }

            if (! Schema::hasColumn('resultados.resultados', 'estado')) {
                $table->string('estado', 20)->default('registrado')->after('penalizaciones');
            }

            if (! Schema::hasColumn('resultados.resultados', 'valor_principal')) {
                $table->decimal('valor_principal', 12, 3)->nullable()->after('estado');
            }

            if (! Schema::hasColumn('resultados.resultados', 'valor_secundario')) {
                $table->decimal('valor_secundario', 12, 3)->nullable()->after('valor_principal');
            }

            if (! Schema::hasColumn('resultados.resultados', 'payload_json')) {
                $table->jsonb('payload_json')->nullable()->after('valor_secundario');
            }

            if (! Schema::hasColumn('resultados.resultados', 'observaciones')) {
                $table->text('observaciones')->nullable()->after('payload_json');
            }

            if (! Schema::hasColumn('resultados.resultados', 'publicado_at')) {
                $table->timestamp('publicado_at')->nullable()->after('observaciones');
            }

            if (! Schema::hasColumn('resultados.resultados', 'publicado_por')) {
                $table->unsignedBigInteger('publicado_por')->nullable()->after('publicado_at');
            }
        });

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_estado_check'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_estado_check
                    CHECK (estado IN ('borrador', 'registrado', 'publicado', 'anulado'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            UPDATE resultados.resultados r
            SET categoria_id = ro.categoria_id
            FROM catalogo.rondas ro
            WHERE r.ronda_id = ro.id
              AND r.categoria_id IS NULL
        ");

        DB::statement("
            UPDATE resultados.resultados r
            SET competencia_id = c.competencia_id
            FROM catalogo.categorias c
            WHERE r.categoria_id = c.id
              AND r.competencia_id IS NULL
        ");

        DB::statement("
            UPDATE resultados.resultados r
            SET inscripcion_id = i.id
            FROM vinculaciones.inscripciones i
            WHERE r.competencia_id = i.competencia_id
              AND r.categoria_id = i.categoria_id
              AND r.equipo_id = i.equipo_id
              AND r.inscripcion_id IS NULL
        ");

        DB::statement("
            UPDATE resultados.resultados r
            SET asignacion_juez_id = ajc.id
            FROM vinculaciones.asignaciones_juez_categoria ajc
            WHERE r.categoria_id = ajc.categoria_id
              AND r.juez_user_id = ajc.juez_user_id
              AND r.asignacion_juez_id IS NULL
        ");

        DB::statement("
            UPDATE resultados.resultados
            SET valor_principal = COALESCE(tiempo, puntaje)
            WHERE valor_principal IS NULL
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_ronda_id_equipo_id_unique
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_ronda_id_equipo_id_juez_user_id_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_id_equipo_id_juez_user_id_unique
                    UNIQUE (ronda_id, equipo_id, juez_user_id);
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_competencia_id_foreign'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_competencia_id_foreign
                    FOREIGN KEY (competencia_id)
                    REFERENCES catalogo.competencias(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_categoria_id_foreign'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_categoria_id_foreign
                    FOREIGN KEY (categoria_id)
                    REFERENCES catalogo.categorias(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_inscripcion_id_foreign'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_inscripcion_id_foreign
                    FOREIGN KEY (inscripcion_id)
                    REFERENCES vinculaciones.inscripciones(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_asignacion_juez_id_foreign'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_asignacion_juez_id_foreign
                    FOREIGN KEY (asignacion_juez_id)
                    REFERENCES vinculaciones.asignaciones_juez_categoria(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_publicado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_publicado_por_foreign
                    FOREIGN KEY (publicado_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_competencia_categoria_ronda_index
            ON resultados.resultados (competencia_id, categoria_id, ronda_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_equipo_estado_index
            ON resultados.resultados (equipo_id, estado)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_juez_updated_at_index
            ON resultados.resultados (juez_user_id, updated_at)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_categoria_estado_publicado_at_index
            ON resultados.resultados (categoria_id, estado, publicado_at)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_inscripcion_id_index
            ON resultados.resultados (inscripcion_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_asignacion_juez_id_index
            ON resultados.resultados (asignacion_juez_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_competencia_id_index
            ON resultados.resultados (competencia_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_categoria_id_index
            ON resultados.resultados (categoria_id)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.resultados')) {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_categoria_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_competencia_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_asignacion_juez_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_inscripcion_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_categoria_estado_publicado_at_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_juez_updated_at_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_equipo_estado_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_competencia_categoria_ronda_index
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_publicado_por_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_asignacion_juez_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_inscripcion_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_categoria_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_competencia_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_ronda_id_equipo_id_juez_user_id_unique
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'resultados_ronda_id_equipo_id_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_id_equipo_id_unique
                    UNIQUE (ronda_id, equipo_id);
                END IF;
            END
            $$;
        ");

        DB::statement("
            ALTER TABLE resultados.resultados
            DROP CONSTRAINT IF EXISTS resultados_estado_check
        ");

        Schema::table('resultados.resultados', function (Blueprint $table) {
            $columns = [
                'competencia_id',
                'categoria_id',
                'inscripcion_id',
                'asignacion_juez_id',
                'estado',
                'valor_principal',
                'valor_secundario',
                'payload_json',
                'observaciones',
                'publicado_at',
                'publicado_por',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('resultados.resultados', $column)
            ));

            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
