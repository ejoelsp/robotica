<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('vinculaciones.inscripcion_integrantes')) {
            Schema::create('vinculaciones.inscripcion_integrantes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('inscripcion_id');
                $table->string('nombre_completo', 255);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->boolean('es_capitan')->default(false);
                $table->timestamps();

                $table->index('inscripcion_id', 'inscripcion_integrantes_inscripcion_id_index');
                $table->index('user_id', 'inscripcion_integrantes_user_id_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'inscripcion_integrantes_inscripcion_id_foreign'
                ) THEN
                    ALTER TABLE vinculaciones.inscripcion_integrantes
                    ADD CONSTRAINT inscripcion_integrantes_inscripcion_id_foreign
                    FOREIGN KEY (inscripcion_id)
                    REFERENCES vinculaciones.inscripciones(id)
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
                    WHERE conname = 'inscripcion_integrantes_user_id_foreign'
                ) THEN
                    ALTER TABLE vinculaciones.inscripcion_integrantes
                    ADD CONSTRAINT inscripcion_integrantes_user_id_foreign
                    FOREIGN KEY (user_id)
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
        DB::statement("
            ALTER TABLE vinculaciones.inscripcion_integrantes
            DROP CONSTRAINT IF EXISTS inscripcion_integrantes_user_id_foreign
        ");

        DB::statement("
            ALTER TABLE vinculaciones.inscripcion_integrantes
            DROP CONSTRAINT IF EXISTS inscripcion_integrantes_inscripcion_id_foreign
        ");

        Schema::dropIfExists('vinculaciones.inscripcion_integrantes');
    }
};