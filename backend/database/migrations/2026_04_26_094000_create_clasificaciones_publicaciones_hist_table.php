<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resultados.clasificaciones_publicaciones_hist')) {
            return;
        }

        Schema::create('resultados.clasificaciones_publicaciones_hist', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('competencia_id');
            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('ronda_id');
            $table->string('accion', 30);
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);
            $table->unsignedInteger('clasificaciones_count')->default(0);
            $table->unsignedBigInteger('ejecutado_por')->nullable();
            $table->timestamp('ejecutado_at')->nullable();
            $table->jsonb('detalle_json')->nullable();
            $table->timestamps();
        });

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'clasif_pub_hist_accion_check'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_accion_check
                    CHECK (accion IN ('borrador', 'publicar', 'cerrar'));
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
                    WHERE conname = 'clasif_pub_hist_estado_anterior_check'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_estado_anterior_check
                    CHECK (
                        estado_anterior IS NULL
                        OR estado_anterior IN ('borrador', 'visible', 'cerrado')
                    );
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
                    WHERE conname = 'clasif_pub_hist_estado_nuevo_check'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_estado_nuevo_check
                    CHECK (estado_nuevo IN ('borrador', 'visible', 'cerrado'));
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
                    WHERE conname = 'clasif_pub_hist_competencia_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_competencia_foreign
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
                    WHERE conname = 'clasif_pub_hist_categoria_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_categoria_foreign
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
                    WHERE conname = 'clasif_pub_hist_ronda_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_ronda_foreign
                    FOREIGN KEY (ronda_id)
                    REFERENCES catalogo.rondas(id)
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
                    WHERE conname = 'clasif_pub_hist_ejecutado_por_foreign'
                ) THEN
                    ALTER TABLE resultados.clasificaciones_publicaciones_hist
                    ADD CONSTRAINT clasif_pub_hist_ejecutado_por_foreign
                    FOREIGN KEY (ejecutado_por)
                    REFERENCES seguridad.users(id)
                    ON UPDATE CASCADE
                    ON DELETE SET NULL;
                END IF;
            END
            $$;
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS clasif_pub_hist_scope_index
            ON resultados.clasificaciones_publicaciones_hist (competencia_id, categoria_id, ronda_id, ejecutado_at DESC)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones_publicaciones_hist')) {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS resultados.clasif_pub_hist_scope_index
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_ejecutado_por_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_ronda_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_categoria_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_competencia_foreign
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_estado_nuevo_check
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_estado_anterior_check
        ");

        DB::statement("
            ALTER TABLE resultados.clasificaciones_publicaciones_hist
            DROP CONSTRAINT IF EXISTS clasif_pub_hist_accion_check
        ");

        Schema::dropIfExists('resultados.clasificaciones_publicaciones_hist');
    }
};
