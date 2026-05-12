<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Crear extensión (si no tienes permisos, comenta esta línea)
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');

        // 2) Agregar columna solo si NO existe (en schema catalogo)
        $col = DB::selectOne("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'catalogo'
            AND table_name = 'categorias'
            AND column_name = 'nombre_key'
            LIMIT 1
        ");

        if (!$col) {
            DB::statement("ALTER TABLE catalogo.categorias ADD COLUMN nombre_key VARCHAR(200) NULL;");
        }

        // 3) Rellenar nombre_key para registros existentes (si está null o vacío)
        DB::statement("
            UPDATE catalogo.categorias
            SET nombre_key = regexp_replace(lower(unaccent(trim(nombre))), '\\s+', ' ', 'g')
            WHERE nombre_key IS NULL OR nombre_key = ''
        ");

        // 4) Forzar NOT NULL
        DB::statement("ALTER TABLE catalogo.categorias ALTER COLUMN nombre_key SET NOT NULL;");

        // 5) ÚNICO por (competencia_id, nombre_key) solo si NO existe
        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'categorias_competencia_id_nombre_key_unique'
                ) THEN
                    ALTER TABLE catalogo.categorias
                    ADD CONSTRAINT categorias_competencia_id_nombre_key_unique
                    UNIQUE (competencia_id, nombre_key);
                END IF;
            END
            $$;
        ");
    }



    public function down(): void
    {
        DB::statement("
            ALTER TABLE catalogo.categorias
            DROP CONSTRAINT IF EXISTS categorias_competencia_id_nombre_key_unique
        ");

        DB::statement("
            ALTER TABLE catalogo.categorias
            DROP COLUMN IF EXISTS nombre_key
        ");
    }

};
