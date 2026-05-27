<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.analisis_historico_cierres')) {
            Schema::create('resultados.analisis_historico_cierres', function (Blueprint $table) {
                $table->id();
                $table->string('tipo_cierre', 20);
                $table->unsignedBigInteger('temporada_id');
                $table->unsignedBigInteger('competencia_id')->nullable();
                $table->unsignedInteger('anio');
                $table->string('estado', 30)->default('cerrado');
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->unsignedInteger('total_competencias')->default(0);
                $table->unsignedInteger('total_categorias')->default(0);
                $table->unsignedInteger('total_participantes')->default(0);
                $table->unsignedInteger('total_equipos')->default(0);
                $table->unsignedInteger('total_instituciones')->default(0);
                $table->unsignedInteger('total_inscripciones_aprobadas')->default(0);
                $table->decimal('tasa_crecimiento_participantes', 8, 2)->nullable();
                $table->decimal('tasa_crecimiento_equipos', 8, 2)->nullable();
                $table->decimal('tasa_crecimiento_instituciones', 8, 2)->nullable();
                $table->jsonb('metricas_json');
                $table->unsignedBigInteger('generado_por')->nullable();
                $table->timestamp('generado_at');
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->timestamp('cerrado_at')->nullable();
                $table->timestamps();

                $table->index(['temporada_id', 'anio'], 'analisis_cierres_temporada_index');
                $table->index(['competencia_id'], 'analisis_cierres_competencia_index');
                $table->index(['tipo_cierre', 'estado'], 'analisis_cierres_tipo_estado_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_tipo_check'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_tipo_check
                    CHECK (tipo_cierre IN ('temporada', 'competencia'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_estado_check'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_estado_check
                    CHECK (estado IN ('generado', 'cerrado', 'reemplazado'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_temporada_foreign'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_temporada_foreign
                    FOREIGN KEY (temporada_id)
                    REFERENCES catalogo.temporadas(id)
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_competencia_foreign'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_competencia_foreign
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_generado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_generado_por_foreign
                    FOREIGN KEY (generado_por)
                    REFERENCES seguridad.users(id)
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'analisis_cierres_cerrado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.analisis_historico_cierres
                    ADD CONSTRAINT analisis_cierres_cerrado_por_foreign
                    FOREIGN KEY (cerrado_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS analisis_cierres_temporada_unique
            ON resultados.analisis_historico_cierres (temporada_id)
            WHERE tipo_cierre = 'temporada' AND competencia_id IS NULL
        ");

        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS analisis_cierres_competencia_unique
            ON resultados.analisis_historico_cierres (competencia_id)
            WHERE tipo_cierre = 'competencia' AND competencia_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS resultados.analisis_cierres_competencia_unique');
        DB::statement('DROP INDEX IF EXISTS resultados.analisis_cierres_temporada_unique');

        Schema::dropIfExists('resultados.analisis_historico_cierres');
    }
};
