<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('resultados.resultados')) {
            DB::statement('DROP INDEX IF EXISTS resultados.resultados_enfrentamiento_id_index');
            DB::statement('ALTER TABLE resultados.resultados DROP CONSTRAINT IF EXISTS resultados_enfrentamiento_id_foreign');

            Schema::table('resultados.resultados', function (Blueprint $table) {
                if (Schema::hasColumn('resultados.resultados', 'enfrentamiento_id')) {
                    $table->dropColumn('enfrentamiento_id');
                }
            });
        }

        Schema::dropIfExists('resultados.enfrentamientos');
    }

    public function down(): void
    {
        // La funcionalidad de enfrentamientos fue retirada por decision de producto.
    }
};
