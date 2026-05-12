<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.config_calificacion')) {
            Schema::create('catalogo.config_calificacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('categoria_id');
                $table->unsignedBigInteger('mecanismo_calificacion_id');
                $table->string('unidad_resultado', 30)->nullable();
                $table->string('orden_ranking', 10)->default('desc');
                $table->boolean('requiere_aprobacion_admin')->default(true);
                $table->boolean('visible_publico_en_vivo')->default(false);
                $table->boolean('permite_edicion_juez')->default(true);
                $table->jsonb('campos_json')->nullable();
                $table->jsonb('reglas_json')->nullable();
                $table->timestamps();

                $table->unique('categoria_id', 'config_calificacion_categoria_id_unique');
                $table->index('mecanismo_calificacion_id', 'config_calificacion_mecanismo_id_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'config_calificacion_orden_ranking_check'
                ) THEN
                    ALTER TABLE catalogo.config_calificacion
                    ADD CONSTRAINT config_calificacion_orden_ranking_check
                    CHECK (orden_ranking IN ('asc', 'desc'));
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
                    WHERE conname = 'config_calificacion_categoria_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.config_calificacion
                    ADD CONSTRAINT config_calificacion_categoria_id_foreign
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
                    WHERE conname = 'config_calificacion_mecanismo_calificacion_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.config_calificacion
                    ADD CONSTRAINT config_calificacion_mecanismo_calificacion_id_foreign
                    FOREIGN KEY (mecanismo_calificacion_id)
                    REFERENCES catalogo.mecanismos_calificacion(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT;
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE catalogo.config_calificacion
            DROP CONSTRAINT IF EXISTS config_calificacion_mecanismo_calificacion_id_foreign
        ");

        DB::statement("
            ALTER TABLE catalogo.config_calificacion
            DROP CONSTRAINT IF EXISTS config_calificacion_categoria_id_foreign
        ");

        DB::statement("
            ALTER TABLE catalogo.config_calificacion
            DROP CONSTRAINT IF EXISTS config_calificacion_orden_ranking_check
        ");

        Schema::dropIfExists('catalogo.config_calificacion');
    }
};
