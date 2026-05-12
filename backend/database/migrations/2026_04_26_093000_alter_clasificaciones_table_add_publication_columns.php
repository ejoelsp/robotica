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
            if (! Schema::hasColumn('resultados.clasificaciones', 'competencia_id')) {
                $table->unsignedBigInteger('competencia_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'ronda_id')) {
                $table->unsignedBigInteger('ronda_id')->nullable()->after('equipo_id');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'estado_publicacion')) {
                $table->string('estado_publicacion', 20)->default('borrador')->after('posicion');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'publicado_at')) {
                $table->timestamp('publicado_at')->nullable()->after('estado_publicacion');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'publicado_por')) {
                $table->unsignedBigInteger('publicado_por')->nullable()->after('publicado_at');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'origen_version')) {
                $table->integer('origen_version')->nullable()->after('publicado_por');
            }

            if (! Schema::hasColumn('resultados.clasificaciones', 'detalle_json')) {
                $table->jsonb('detalle_json')->nullable()->after('origen_version');
            }
        });

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'clasificaciones_estado_publicacion_check'
                ) THEN
                    ALTER TABLE resultados.clasificaciones
                    ADD CONSTRAINT clasificaciones_estado_publicacion_check
                    CHECK (estado_publicacion IN ('borrador', 'visible', 'cerrado'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            UPDATE resultados.clasificaciones cl
            SET competencia_id = c.competencia_id
            FROM catalogo.categorias c
            WHERE cl.categoria_id = c.id
              AND cl.competencia_id IS NULL
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'clasificaciones_competencia_id_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones
                    ADD CONSTRAINT clasificaciones_competencia_id_foreign
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
                    WHERE conname = 'clasificaciones_ronda_id_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones
                    ADD CONSTRAINT clasificaciones_ronda_id_foreign
                    FOREIGN KEY (ronda_id)
                    REFERENCES catalogo.rondas(id)
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
                    WHERE conname = 'clasificaciones_publicado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones
                    ADD CONSTRAINT clasificaciones_publicado_por_foreign
                    FOREIGN KEY (publicado_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasificaciones_competencia_categoria_estado_posicion_index
            ON resultados.clasificaciones (competencia_id, categoria_id, estado_publicacion, posicion)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasificaciones_ronda_id_index
            ON resultados.clasificaciones (ronda_id)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasificaciones_publicado_at_index
            ON resultados.clasificaciones (publicado_at)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasificaciones_competencia_id_index
            ON resultados.clasificaciones (competencia_id)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')) {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_competencia_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_publicado_at_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_ronda_id_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasificaciones_competencia_categoria_estado_posicion_index
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_publicado_por_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_ronda_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_competencia_id_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones
            DROP CONSTRAINT IF EXISTS clasificaciones_estado_publicacion_check
        ");

        Schema::table('resultados.clasificaciones', function (Blueprint $table) {
            $columns = [
                'competencia_id',
                'ronda_id',
                'estado_publicacion',
                'publicado_at',
                'publicado_por',
                'origen_version',
                'detalle_json',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('resultados.clasificaciones', $column)
            ));

            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
