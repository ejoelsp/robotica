<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.resultados')) {
            return;
        }

        Schema::table('resultados.resultados', function (Blueprint $table) {
            if (! Schema::hasColumn('resultados.resultados', 'intento_numero')) {
                $table->unsignedInteger('intento_numero')->default(1)->after('asignacion_juez_id');
            }
        });

        DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_ronda_id_equipo_id_juez_user_id_unique');

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'resultados_ronda_equipo_juez_intento_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_equipo_juez_intento_unique
                    UNIQUE (ronda_id, equipo_id, juez_user_id, intento_numero);
                END IF;
            END
            $$;
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.resultados')) {
            return;
        }

        DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_ronda_equipo_juez_intento_unique');

        DB::statement("
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'resultados_ronda_id_equipo_id_juez_user_id_unique'
                ) THEN
                    ALTER TABLE resultados.resultados
                    ADD CONSTRAINT resultados_ronda_id_equipo_id_juez_user_id_unique
                    UNIQUE (ronda_id, equipo_id, juez_user_id);
                END IF;
            END
            $$;
        ");

        Schema::table('resultados.resultados', function (Blueprint $table) {
            if (Schema::hasColumn('resultados.resultados', 'intento_numero')) {
                $table->dropColumn('intento_numero');
            }
        });
    }
};
