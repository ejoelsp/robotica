<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.config_jueces_competencia')) {
            Schema::create('catalogo.config_jueces_competencia', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('competencia_id');
                $table->unsignedSmallInteger('jueces_principales_requeridos')->default(1);
                $table->unsignedSmallInteger('jueces_apoyo_requeridos')->default(2);
                $table->timestamps();

                $table->unique('competencia_id', 'config_jueces_competencia_competencia_id_unique');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'config_jueces_competencia_competencia_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.config_jueces_competencia
                    ADD CONSTRAINT config_jueces_competencia_competencia_id_foreign
                    FOREIGN KEY (competencia_id)
                    REFERENCES catalogo.competencias(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE;
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE catalogo.config_jueces_competencia
            DROP CONSTRAINT IF EXISTS config_jueces_competencia_competencia_id_foreign
        ");

        Schema::dropIfExists('catalogo.config_jueces_competencia');
    }
};
