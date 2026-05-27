<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('catalogo.sorteo_detalles')) {
            return;
        }

        DB::statement("
            UPDATE catalogo.sorteo_detalles
            SET estado = 'directo', lado = NULL
            WHERE estado = 'bye' OR lado = 'BYE'
        ");

        DB::statement('ALTER TABLE catalogo.sorteo_detalles DROP CONSTRAINT IF EXISTS sorteo_detalles_estado_check');
        DB::statement("
            ALTER TABLE catalogo.sorteo_detalles
            ADD CONSTRAINT sorteo_detalles_estado_check
            CHECK (estado IN ('pendiente', 'directo', 'completado'))
        ");

        DB::statement('ALTER TABLE catalogo.sorteo_detalles DROP CONSTRAINT IF EXISTS sorteo_detalles_lado_check');
        DB::statement("
            ALTER TABLE catalogo.sorteo_detalles
            ADD CONSTRAINT sorteo_detalles_lado_check
            CHECK (lado IS NULL OR lado IN ('A', 'B'))
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('catalogo.sorteo_detalles')) {
            return;
        }

        DB::statement('ALTER TABLE catalogo.sorteo_detalles DROP CONSTRAINT IF EXISTS sorteo_detalles_estado_check');
        DB::statement("
            ALTER TABLE catalogo.sorteo_detalles
            ADD CONSTRAINT sorteo_detalles_estado_check
            CHECK (estado IN ('pendiente', 'bye', 'completado'))
        ");

        DB::statement('ALTER TABLE catalogo.sorteo_detalles DROP CONSTRAINT IF EXISTS sorteo_detalles_lado_check');
        DB::statement("
            ALTER TABLE catalogo.sorteo_detalles
            ADD CONSTRAINT sorteo_detalles_lado_check
            CHECK (lado IS NULL OR lado IN ('A', 'B', 'BYE'))
        ");
    }
};
