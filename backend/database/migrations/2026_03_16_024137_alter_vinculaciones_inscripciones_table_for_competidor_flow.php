<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vinculaciones.inscripciones', function (Blueprint $table) {
            if (! Schema::hasColumn('vinculaciones.inscripciones', 'categoria_id')) {
                $table->unsignedBigInteger('categoria_id')->nullable()->after('competencia_id');
            }

            if (! Schema::hasColumn('vinculaciones.inscripciones', 'nombre_prototipo')) {
                $table->string('nombre_prototipo', 255)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('vinculaciones.inscripciones', 'telefono_contacto')) {
                $table->string('telefono_contacto', 20)->nullable()->after('nombre_prototipo');
            }
        });

        // Eliminar unique vieja si existe
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_competencia_id_equipo_id_unique
        ");

        // Crear FK solo si no existe
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'inscripciones_categoria_id_foreign'
                ) THEN
                    ALTER TABLE vinculaciones.inscripciones
                    ADD CONSTRAINT inscripciones_categoria_id_foreign
                    FOREIGN KEY (categoria_id)
                    REFERENCES catalogo.categorias(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;
            END
            $$;
        ");

        // Crear índice solo si no existe
        DB::statement("
            CREATE INDEX IF NOT EXISTS inscripciones_categoria_id_index
            ON vinculaciones.inscripciones (categoria_id)
        ");

        // Crear unique nueva solo si no existe
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

    public function down(): void
    {
        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_competencia_categoria_equipo_unique
        ");

        DB::statement("
            DROP INDEX IF EXISTS vinculaciones.inscripciones_categoria_id_index
        ");

        DB::statement("
            ALTER TABLE vinculaciones.inscripciones
            DROP CONSTRAINT IF EXISTS inscripciones_categoria_id_foreign
        ");

        Schema::table('vinculaciones.inscripciones', function (Blueprint $table) {
            if (Schema::hasColumn('vinculaciones.inscripciones', 'telefono_contacto')) {
                $table->dropColumn('telefono_contacto');
            }

            if (Schema::hasColumn('vinculaciones.inscripciones', 'nombre_prototipo')) {
                $table->dropColumn('nombre_prototipo');
            }

            if (Schema::hasColumn('vinculaciones.inscripciones', 'categoria_id')) {
                $table->dropColumn('categoria_id');
            }
        });

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'inscripciones_competencia_id_equipo_id_unique'
                ) THEN
                    ALTER TABLE vinculaciones.inscripciones
                    ADD CONSTRAINT inscripciones_competencia_id_equipo_id_unique
                    UNIQUE (competencia_id, equipo_id);
                END IF;
            END
            $$;
        ");
    }
};