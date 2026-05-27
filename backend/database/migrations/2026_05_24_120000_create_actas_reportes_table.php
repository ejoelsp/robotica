<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.actas_reportes')) {
            Schema::create('resultados.actas_reportes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('competencia_id');
                $table->unsignedBigInteger('categoria_id');
                $table->unsignedBigInteger('ronda_id')->nullable();
                $table->string('tipo_reporte', 40);
                $table->string('estado', 30)->default('generado');
                $table->string('archivo_generado_path', 500);
                $table->string('archivo_firmado_path', 500)->nullable();
                $table->unsignedBigInteger('generado_por');
                $table->unsignedBigInteger('archivo_firmado_subido_por')->nullable();
                $table->timestamp('generado_at');
                $table->timestamp('archivo_firmado_subido_at')->nullable();
                $table->jsonb('snapshot_json');
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->index(['competencia_id', 'categoria_id', 'tipo_reporte'], 'actas_reportes_scope_index');
                $table->index(['estado', 'generado_at'], 'actas_reportes_estado_generado_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_tipo_reporte_check'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_tipo_reporte_check
                    CHECK (tipo_reporte IN ('inscritos', 'tabla_resultados', 'acta_final'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_estado_check'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_estado_check
                    CHECK (estado IN ('generado', 'firmado', 'anulado'));
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_competencia_id_foreign'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_competencia_id_foreign
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_categoria_id_foreign'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_categoria_id_foreign
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_ronda_id_foreign'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_ronda_id_foreign
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
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_generado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_generado_por_foreign
                    FOREIGN KEY (generado_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT;
                END IF;
            END
            $$;
        ");

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'actas_reportes_firmado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.actas_reportes
                    ADD CONSTRAINT actas_reportes_firmado_por_foreign
                    FOREIGN KEY (archivo_firmado_subido_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados.actas_reportes');
    }
};
