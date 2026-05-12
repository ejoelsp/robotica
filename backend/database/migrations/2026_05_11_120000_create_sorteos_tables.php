<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.sorteos')) {
            Schema::create('catalogo.sorteos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ronda_id');
                $table->string('tipo_sorteo', 30);
                $table->string('estado', 30)->default('generado');
                $table->jsonb('reglas_json')->nullable();
                $table->timestamps();

                $table->index('ronda_id', 'sorteos_ronda_id_index');
                $table->index('estado', 'sorteos_estado_index');
            });
        }

        if (! Schema::hasTable('catalogo.sorteo_detalles')) {
            Schema::create('catalogo.sorteo_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('sorteo_id');
                $table->unsignedBigInteger('inscripcion_id');
                $table->unsignedInteger('orden');
                $table->unsignedInteger('grupo')->nullable();
                $table->string('lado', 10)->nullable();
                $table->string('estado', 30)->default('pendiente');
                $table->timestamps();

                $table->unique(['sorteo_id', 'inscripcion_id'], 'sorteo_detalles_sorteo_inscripcion_unique');
                $table->unique(['sorteo_id', 'orden'], 'sorteo_detalles_sorteo_orden_unique');
                $table->index(['sorteo_id', 'grupo'], 'sorteo_detalles_sorteo_grupo_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteos_tipo_sorteo_check'
                ) THEN
                    ALTER TABLE catalogo.sorteos
                    ADD CONSTRAINT sorteos_tipo_sorteo_check
                    CHECK (tipo_sorteo IN ('individual', 'enfrentamiento'));
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteos_estado_check'
                ) THEN
                    ALTER TABLE catalogo.sorteos
                    ADD CONSTRAINT sorteos_estado_check
                    CHECK (estado IN ('generado', 'confirmado', 'anulado'));
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteos_ronda_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.sorteos
                    ADD CONSTRAINT sorteos_ronda_id_foreign
                    FOREIGN KEY (ronda_id)
                    REFERENCES catalogo.rondas(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteo_detalles_estado_check'
                ) THEN
                    ALTER TABLE catalogo.sorteo_detalles
                    ADD CONSTRAINT sorteo_detalles_estado_check
                    CHECK (estado IN ('pendiente', 'bye', 'completado'));
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteo_detalles_lado_check'
                ) THEN
                    ALTER TABLE catalogo.sorteo_detalles
                    ADD CONSTRAINT sorteo_detalles_lado_check
                    CHECK (lado IS NULL OR lado IN ('A', 'B', 'BYE'));
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteo_detalles_sorteo_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.sorteo_detalles
                    ADD CONSTRAINT sorteo_detalles_sorteo_id_foreign
                    FOREIGN KEY (sorteo_id)
                    REFERENCES catalogo.sorteos(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;

                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'sorteo_detalles_inscripcion_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.sorteo_detalles
                    ADD CONSTRAINT sorteo_detalles_inscripcion_id_foreign
                    FOREIGN KEY (inscripcion_id)
                    REFERENCES vinculaciones.inscripciones(id)
                    ON UPDATE CASCADE
                    ON DELETE RESTRICT;
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('catalogo.sorteo_detalles');
        Schema::dropIfExists('catalogo.sorteos');
    }
};
