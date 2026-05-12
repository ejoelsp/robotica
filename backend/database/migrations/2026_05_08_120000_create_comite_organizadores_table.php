<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.comite_organizadores')) {
            Schema::create('catalogo.comite_organizadores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('competencia_id');
                $table->string('nombres');
                $table->string('apellidos');
                $table->string('correo')->nullable();
                $table->string('rol_comite');
                $table->string('foto')->nullable();
                $table->integer('orden')->default(0);
                $table->boolean('estado')->default(true);
                $table->timestamps();

                $table->index('competencia_id', 'comite_organizadores_competencia_id_index');
                $table->index(['competencia_id', 'estado', 'orden'], 'comite_organizadores_public_index');
            });
        }

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'comite_organizadores_competencia_id_foreign'
                ) THEN
                    ALTER TABLE catalogo.comite_organizadores
                    ADD CONSTRAINT comite_organizadores_competencia_id_foreign
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
            ALTER TABLE catalogo.comite_organizadores
            DROP CONSTRAINT IF EXISTS comite_organizadores_competencia_id_foreign
        ");

        Schema::dropIfExists('catalogo.comite_organizadores');
    }
};
