<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')
            || ! Schema::hasColumn('resultados.clasificaciones', 'penal_total')) {
            return;
        }

        DB::statement('
            ALTER TABLE resultados.clasificaciones
            ALTER COLUMN penal_total TYPE DECIMAL(10, 3)
            USING penal_total::DECIMAL(10, 3)
        ');
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.clasificaciones')
            || ! Schema::hasColumn('resultados.clasificaciones', 'penal_total')) {
            return;
        }

        DB::statement('
            ALTER TABLE resultados.clasificaciones
            ALTER COLUMN penal_total TYPE INTEGER
            USING ROUND(penal_total)::INTEGER
        ');
    }
};
