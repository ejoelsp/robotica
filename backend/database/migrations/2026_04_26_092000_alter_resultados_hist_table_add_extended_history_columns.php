<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resultados.resultados_hist')) {
            return;
        }

        Schema::table('resultados.resultados_hist', function (Blueprint $table) {
            if (! Schema::hasColumn('resultados.resultados_hist', 'version_anterior')) {
                $table->integer('version_anterior')->nullable()->after('version');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'version_nueva')) {
                $table->integer('version_nueva')->nullable()->after('version_anterior');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'estado_old')) {
                $table->string('estado_old', 20)->nullable()->after('penal_new');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'estado_new')) {
                $table->string('estado_new', 20)->nullable()->after('estado_old');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'payload_old')) {
                $table->jsonb('payload_old')->nullable()->after('estado_new');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'payload_new')) {
                $table->jsonb('payload_new')->nullable()->after('payload_old');
            }

            if (! Schema::hasColumn('resultados.resultados_hist', 'motivo_cambio')) {
                $table->string('motivo_cambio', 255)->nullable()->after('payload_new');
            }
        });

        DB::statement("
            UPDATE resultados.resultados_hist
            SET version_anterior = GREATEST(version - 1, 0)
            WHERE version_anterior IS NULL
        ");

        DB::statement("
            UPDATE resultados.resultados_hist
            SET version_nueva = version
            WHERE version_nueva IS NULL
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_hist_resultado_version_nueva_index
            ON resultados.resultados_hist (resultado_id, version_nueva)
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS resultados_hist_editado_por_created_at_index
            ON resultados.resultados_hist (editado_por, created_at)
        ");
    }

    public function down(): void
    {
        if (! Schema::hasTable('resultados.resultados_hist')) {
            return;
        }

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_hist_editado_por_created_at_index
        ");

        DB::statement("
            DROP INDEX IF EXISTS resultados.resultados_hist_resultado_version_nueva_index
        ");

        Schema::table('resultados.resultados_hist', function (Blueprint $table) {
            $columns = [
                'version_anterior',
                'version_nueva',
                'estado_old',
                'estado_new',
                'payload_old',
                'payload_new',
                'motivo_cambio',
            ];

            $existingColumns = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn('resultados.resultados_hist', $column)
            ));

            if (! empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
